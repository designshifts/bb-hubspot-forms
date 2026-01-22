<?php

namespace BBHubspotForms\REST;

use WP_REST_Request;
use WP_REST_Response;
use BBHubspotForms\Security\Signer;
use BBHubspotForms\Security\RateLimiter;
use BBHubspotForms\HubSpot\Client;
use BBHubspotForms\Settings;
use BBHubspotForms\Spam\DomainBlocker;
use BBHubspotForms\Spam\Captcha;

final class SubmitController {
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'hubspotform/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle_submit( WP_REST_Request $request ): WP_REST_Response {
		$params  = $request->get_json_params();
		$form_id = isset( $params['formId'] ) ? absint( $params['formId'] ) : 0;
		$token   = isset( $params['token'] ) ? sanitize_text_field( $params['token'] ) : '';

		if ( ! $form_id || empty( $token ) ) {
			return self::error_response( 'Missing formId or token.', 400 );
		}

		$schema       = get_post_meta( $form_id, 'hubspot_form_schema', true );
		$settings     = get_post_meta( $form_id, 'hubspot_form_settings', true );
		$version      = get_post_meta( $form_id, 'hubspot_form_version', true );
		$captcha_req  = ! empty( $settings['captcha_required'] );
		$schema_ver   = $version ? $version : 'v1';
		$schema_param = isset( $params['schemaVersion'] ) ? sanitize_text_field( $params['schemaVersion'] ) : '';

		if ( $schema_param && $schema_param !== $schema_ver ) {
			return self::error_response( 'Schema version mismatch.', 403 );
		}

		if ( ! Signer::verify_token( $token, $form_id, $schema_ver ) ) {
			return self::error_response( 'Invalid or expired token.', 403 );
		}

		$ip = self::get_client_ip();
		if ( ! RateLimiter::check( $ip ) ) {
			return self::field_errors( array( 'rate_limit' => 'Too many requests. Please try again later.' ), 429 );
		}

		$captcha_provider = Settings::get( 'captcha_provider', '' );
		$captcha_secret   = Settings::get( 'captcha_secret_key', '' );
		$captcha_token    = isset( $params['captchaToken'] ) ? sanitize_text_field( $params['captchaToken'] ) : '';
		$captcha_action   = isset( $params['captchaAction'] ) ? sanitize_text_field( $params['captchaAction'] ) : '';

		if ( $captcha_provider && ( $captcha_req || ! empty( $captcha_token ) ) ) {
			$expected_action = Settings::get( 'captcha_expected_action', 'hubspot_form_submit' );
			$min_score       = (float) Settings::get( 'captcha_min_score', 0.5 );
			$host            = wp_parse_url( home_url(), PHP_URL_HOST );
			$host            = is_string( $host ) ? $host : '';
			$host_whitelist  = $host ? array( $host ) : array();
			if ( $host && strpos( $host, 'www.' ) !== 0 ) {
				$host_whitelist[] = 'www.' . $host;
			}
			if ( $expected_action && $captcha_action && $captcha_action !== $expected_action ) {
				return self::error_response( 'Captcha action mismatch.', 400 );
			}
			if ( ! Captcha::verify( $captcha_provider, $captcha_secret, $captcha_token, $ip, array(
				'expected_action'   => $expected_action,
				'min_score'         => $min_score,
				'expected_hostname' => $host_whitelist,
			) ) ) {
				return self::error_response( 'Captcha verification failed.', 400 );
			}
		} elseif ( $captcha_req ) {
			return self::error_response( 'Captcha token is required.', 400 );
		}

		if ( empty( $schema ) || empty( $schema['fields'] ) ) {
			return self::error_response( 'Form schema is missing.', 400 );
		}

		$raw_fields  = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
		$hidden_meta = get_post_meta( $form_id, 'hubspot_form_hidden_fields', true );
		$hidden_meta = is_array( $hidden_meta ) ? $hidden_meta : array();

		$schema_map = array();
		foreach ( $schema['fields'] as $field ) {
			$field_name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
			if ( ! $field_name ) {
				continue;
			}
			$schema_map[ $field_name ] = isset( $field['type'] ) ? $field['type'] : 'text';
		}

		$errors = array();
		$fields = array();
		foreach ( $schema_map as $name => $type ) {
			if ( ! array_key_exists( $name, $raw_fields ) ) {
				$fields[ $name ] = '';
				continue;
			}
			$value = $raw_fields[ $name ];
			if ( is_array( $value ) || is_object( $value ) ) {
				$errors[ $name ] = 'Invalid value.';
				continue;
			}
			$value = trim( (string) $value );
			$fields[ $name ] = self::sanitize_value_by_type( $value, $type );
		}

		foreach ( $hidden_meta as $hidden_field ) {
			if ( empty( $hidden_field['id'] ) ) {
				continue;
			}
			$hidden_key = sanitize_key( $hidden_field['id'] );
			if ( ! isset( $schema_map[ $hidden_key ] ) ) {
				continue;
			}
			$hidden_value = $hidden_field['value'] ?? '';
			if ( is_array( $hidden_value ) || is_object( $hidden_value ) ) {
				$errors[ $hidden_key ] = 'Invalid value.';
				continue;
			}
			$hidden_value = trim( (string) $hidden_value );
			$fields[ $hidden_key ] = self::sanitize_value_by_type( $hidden_value, $schema_map[ $hidden_key ] );
		}

		if ( ! empty( $errors ) ) {
			return self::field_errors( $errors, 400 );
		}

		$errors = self::validate_fields( $schema['fields'], $fields, $settings );
		if ( ! empty( $errors ) ) {
			return self::field_errors( $errors, 400 );
		}

		$hubspot_form_id = isset( $settings['hubspot_form_id'] ) ? sanitize_text_field( $settings['hubspot_form_id'] ) : '';
		if ( empty( $hubspot_form_id ) ) {
			return self::error_response( 'HubSpot form ID is missing.', 400 );
		}

		$payload_fields = array();
		foreach ( $fields as $name => $value ) {
			$payload_fields[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}

		$context = array(
			'pageUri'  => isset( $params['context']['pageUri'] ) ? sanitize_text_field( $params['context']['pageUri'] ) : '',
			'pageName' => isset( $params['context']['pageName'] ) ? sanitize_text_field( $params['context']['pageName'] ) : '',
		);

		$consent = self::build_consent_payload( get_post_meta( $form_id, 'hubspot_form_consent', true ) );

		$result = Client::submit_form( $hubspot_form_id, $payload_fields, $context, $consent );
		if ( ! $result['success'] ) {
			return self::field_errors( array( 'submission' => $result['error'] ), 500 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Form submitted successfully.',
			),
			200
		);
	}

	private static function validate_fields( array $schema_fields, array $fields, array $settings ): array {
		$errors        = array();
		$block_domains = false;
		$block_domains = apply_filters( 'bb_hubspot_forms_block_email_domains', $block_domains, $settings );

		foreach ( $schema_fields as $field ) {
			$name     = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
			$type     = isset( $field['type'] ) ? $field['type'] : 'text';
			$required = ! empty( $field['required'] );
			$value    = isset( $fields[ $name ] ) ? $fields[ $name ] : '';

			if ( $required && $value === '' ) {
				$errors[ $name ] = 'This field is required.';
				continue;
			}

			if ( $value === '' ) {
				continue;
			}

			if ( $type === 'email' ) {
				if ( ! is_email( $value ) ) {
					$errors[ $name ] = 'Please enter a valid email address.';
					continue;
				}
				if ( $block_domains && DomainBlocker::is_blocked( $value ) ) {
					$errors[ $name ] = 'Please enter a business email address.';
				}
			}

			if ( $type === 'url' && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$errors[ $name ] = 'Please enter a valid URL.';
			}
		}

		return $errors;
	}

	private static function sanitize_value_by_type( string $value, string $type ): string {
		if ( $value === '' ) {
			return '';
		}
		if ( $type === 'email' ) {
			return sanitize_email( $value );
		}
		if ( $type === 'url' ) {
			return esc_url_raw( $value );
		}
		if ( $type === 'tel' ) {
			return sanitize_text_field( $value );
		}
		return sanitize_text_field( $value );
	}

	private static function build_consent_payload( $consent_meta ): array {
		if ( ! is_array( $consent_meta ) ) {
			return array();
		}

		$subscription_id = isset( $consent_meta['subscription_type_id'] ) ? (int) $consent_meta['subscription_type_id'] : 0;
		$consent_text    = isset( $consent_meta['consent_text'] ) ? $consent_meta['consent_text'] : '';
		$marketing_text  = isset( $consent_meta['marketing_text'] ) ? $consent_meta['marketing_text'] : '';

		if ( ! $subscription_id ) {
			return array();
		}

		return array(
			'consent' => array(
				'consentToProcess' => true,
				'text'             => $consent_text,
				'communications'   => array(
					array(
						'value'              => true,
						'subscriptionTypeId' => $subscription_id,
						'text'               => $marketing_text,
					),
				),
			),
		);
	}

	private static function get_client_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}

	private static function error_response( string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'errors'  => array(
					'submission' => $message,
				),
			),
			$status
		);
	}

	private static function field_errors( array $errors, int $status ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'errors'  => $errors,
			),
			$status
		);
	}
}
