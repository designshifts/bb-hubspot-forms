<?php
/**
 * REST controller for form submissions.
 *
 * @package BBHubspotForms
 */

namespace BBHubspotForms\REST;

use WP_REST_Request;
use WP_REST_Response;
use BBHubspotForms\Logger;
use BBHubspotForms\Security\Signer;
use BBHubspotForms\Security\RateLimiter;
use BBHubspotForms\HubSpot\Client;
use BBHubspotForms\Settings;
use BBHubspotForms\Spam\DomainBlocker;
use BBHubspotForms\Spam\Captcha;

/**
 * Handles form submission via REST API.
 */
final class SubmitController {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			'hubspotform/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'formId' => array(
						'required'          => true,
						'validate_callback' => static function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'token'  => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'fields' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
	}

	/**
	 * Handle form submission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_submit( WP_REST_Request $request ): WP_REST_Response {
		$params  = $request->get_json_params();
		$form_id = isset( $params['formId'] ) ? absint( $params['formId'] ) : 0;
		$token   = isset( $params['token'] ) ? sanitize_text_field( $params['token'] ) : '';

		if ( ! $form_id || empty( $token ) ) {
			return self::error_response( 'Missing formId or token.', 400 );
		}

		// Load new meta structure.
		$schema        = get_post_meta( $form_id, '_bbhs_schema', true );
		$overrides     = get_post_meta( $form_id, '_bbhs_overrides', true );
		$hubspot_guid  = get_post_meta( $form_id, '_bbhs_form_guid', true );
		$token_ttl     = (int) get_post_meta( $form_id, '_bbhs_token_ttl', true );
		$schema_ver    = 'v2';
		$schema_param  = isset( $params['schemaVersion'] ) ? sanitize_text_field( $params['schemaVersion'] ) : '';

		if ( $schema_param && $schema_param !== $schema_ver ) {
			Logger::log( 'Schema version mismatch.', array( 'form_id' => $form_id, 'schema_param' => $schema_param, 'schema_ver' => $schema_ver ) );
			return self::error_response( 'Schema version mismatch.', 403 );
		}

		if ( ! Signer::verify_token( $token, $form_id, $schema_ver ) ) {
			Logger::log( 'Invalid or expired token.', array( 'form_id' => $form_id ) );
			return self::error_response( 'Invalid or expired token.', 403 );
		}

		$ip = self::get_client_ip();
		if ( ! RateLimiter::check( $ip ) ) {
			Logger::log( 'Rate limit hit.', array( 'form_id' => $form_id, 'ip' => $ip ) );
			return self::field_errors( array( 'rate_limit' => 'Too many requests. Please try again later.' ), 429 );
		}

		// CAPTCHA verification.
		$captcha_provider = Settings::get( 'captcha_provider', '' );
		$captcha_secret   = Settings::get( 'captcha_secret_key', '' );
		$captcha_token    = isset( $params['captchaToken'] ) ? sanitize_text_field( $params['captchaToken'] ) : '';
		$captcha_action   = isset( $params['captchaAction'] ) ? sanitize_text_field( $params['captchaAction'] ) : '';

		if ( $captcha_provider && ! empty( $captcha_token ) ) {
			$expected_action = Settings::get( 'captcha_expected_action', 'hubspot_form_submit' );
			$min_score       = (float) Settings::get( 'captcha_min_score', 0.5 );
			$host            = wp_parse_url( home_url(), PHP_URL_HOST );
			$host            = is_string( $host ) ? $host : '';
			$host_whitelist  = $host ? array( $host ) : array();
			if ( $host && strpos( $host, 'www.' ) !== 0 ) {
				$host_whitelist[] = 'www.' . $host;
			}
			if ( $expected_action && $captcha_action && $captcha_action !== $expected_action ) {
				Logger::log( 'Captcha action mismatch.', array( 'form_id' => $form_id, 'action' => $captcha_action ) );
				return self::error_response( 'Captcha action mismatch.', 400 );
			}
			if ( ! Captcha::verify(
				$captcha_provider,
				$captcha_secret,
				$captcha_token,
				$ip,
				array(
				'expected_action'   => $expected_action,
				'min_score'         => $min_score,
				'expected_hostname' => $host_whitelist,
				)
			) ) {
				Logger::log( 'Captcha verification failed.', array( 'form_id' => $form_id ) );
				return self::error_response( 'Captcha verification failed.', 400 );
			}
		}

		if ( empty( $schema ) || empty( $schema['fields'] ) ) {
			Logger::log( 'Form schema missing.', array( 'form_id' => $form_id ) );
			return self::error_response( 'Form schema is missing.', 400 );
		}

		if ( empty( $hubspot_guid ) ) {
			Logger::log( 'HubSpot form GUID missing.', array( 'form_id' => $form_id ) );
			return self::error_response( 'HubSpot form GUID is missing.', 400 );
		}

		// Parse overrides.
		$overrides = is_array( $overrides ) ? $overrides : array();
		$hidden    = isset( $overrides['hidden'] ) && is_array( $overrides['hidden'] ) ? $overrides['hidden'] : array();

		// Build schema map (only visible fields).
		$schema_map = array();
		foreach ( $schema['fields'] as $field ) {
			$field_name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
			if ( ! $field_name ) {
				continue;
			}
			// Skip hidden fields.
			if ( in_array( $field_name, $hidden, true ) ) {
				continue;
			}
			$schema_map[ $field_name ] = $field;
		}

		$raw_fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
		$errors     = array();
		$fields     = array();

		// Validate and sanitize incoming fields.
		foreach ( $schema_map as $name => $field_def ) {
			$type = isset( $field_def['type'] ) ? $field_def['type'] : 'text';
			if ( ! array_key_exists( $name, $raw_fields ) ) {
				$fields[ $name ] = '';
				continue;
			}
			$value = $raw_fields[ $name ];
			if ( is_array( $value ) || is_object( $value ) ) {
				$errors[ $name ] = 'Invalid value.';
				continue;
			}
			$value           = trim( (string) $value );
			$fields[ $name ] = self::sanitize_value_by_type( $value, $type );
		}

		// Check for unexpected fields.
		foreach ( $raw_fields as $name => $value ) {
			$key = sanitize_key( $name );
			if ( ! isset( $schema_map[ $key ] ) ) {
				$errors[ $key ] = 'Unexpected field.';
			}
		}

		if ( ! empty( $errors ) ) {
			Logger::log( 'Field validation errors (schema map).', array( 'form_id' => $form_id, 'errors' => $errors ) );
			return self::field_errors( $errors, 400 );
		}

		// Validate required fields.
		$errors = self::validate_fields( $schema_map, $fields );
		if ( ! empty( $errors ) ) {
			Logger::log( 'Field validation errors (rules).', array( 'form_id' => $form_id, 'errors' => $errors ) );
			return self::field_errors( $errors, 400 );
		}

		// Build HubSpot payload.
		$payload_fields = array();
		foreach ( $fields as $name => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$payload_fields[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}

		$page_uri  = isset( $params['context']['pageUri'] ) ? esc_url_raw( $params['context']['pageUri'] ) : '';
		$page_name = isset( $params['context']['pageName'] ) ? sanitize_text_field( $params['context']['pageName'] ) : '';
		if ( '' === $page_uri ) {
			$page_uri = wp_get_referer() ? esc_url_raw( wp_get_referer() ) : '';
		}
		if ( '' === $page_name ) {
			$page_name = get_bloginfo( 'name' );
		}

		$context = array(
			'pageUri'  => $page_uri,
			'pageName' => $page_name,
		);
		if ( ! empty( $_COOKIE['hubspotutk'] ) ) {
			$context['hutk'] = sanitize_text_field( wp_unslash( $_COOKIE['hubspotutk'] ) );
		}

		// Get user consent values from request.
		$user_consent = isset( $params['consent'] ) && is_array( $params['consent'] ) ? $params['consent'] : array();
		$user_consent_to_process = ! empty( $user_consent['consentToProcess'] );
		$user_marketing_consent  = ! empty( $user_consent['marketingConsent'] );

		// Build consent payload from settings + user input.
		$consent = self::build_consent_payload( $user_consent_to_process, $user_marketing_consent );

		$payload = array(
			'fields'  => $payload_fields,
			'context' => $context,
		);
		if ( ! empty( $consent ) ) {
			$payload['legalConsentOptions'] = $consent;
		}

		$payload = apply_filters( 'bb_hs_submission_payload', $payload, $form_id, $fields );

		$result = Client::submit_form(
			$hubspot_guid,
			$payload['fields'],
			$payload['context'] ?? array(),
			$payload['legalConsentOptions'] ?? array()
		);

		if ( ! $result['success'] ) {
			Logger::log( 'HubSpot submission failed.', array( 'form_id' => $form_id, 'error' => $result['error'] ?? '' ) );
			return self::field_errors( array( 'submission' => $result['error'] ), 500 );
		}

		self::post_attribution_capture( $fields, $form_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Form submitted successfully.',
			),
			200
		);
	}

	/**
	 * Post attribution capture to Thinkific Ops.
	 *
	 * @param array $fields Form fields.
	 * @param int   $form_id Form ID.
	 * @return void
	 */
	private static function post_attribution_capture( array $fields, int $form_id ): void {
		$secret = '';
		if ( defined( 'BB_TFO_ATTRIBUTION_SECRET' ) && '' !== BB_TFO_ATTRIBUTION_SECRET ) {
			$secret = BB_TFO_ATTRIBUTION_SECRET;
		} elseif ( defined( 'BB_HUBSPOT_ENCRYPTION_KEY' ) && '' !== BB_HUBSPOT_ENCRYPTION_KEY ) {
			$secret = hash_hmac( 'sha256', 'bb_tfo_attr', BB_HUBSPOT_ENCRYPTION_KEY );
		}

		if ( '' === $secret ) {
			Logger::log( 'Attribution capture skipped: missing BB_TFO_ATTRIBUTION_SECRET.', array( 'form_id' => $form_id ) );
			return;
		}

		$email = '';
		if ( isset( $fields['email'] ) && is_email( $fields['email'] ) ) {
			$email = $fields['email'];
		} else {
			foreach ( $fields as $value ) {
				if ( is_string( $value ) && is_email( $value ) ) {
					$email = $value;
					break;
				}
			}
		}
		if ( '' === $email ) {
			return;
		}

		$session_key = '';
		if ( ! empty( $_COOKIE['tcc_session_key'] ) ) {
			$session_key = sanitize_text_field( wp_unslash( $_COOKIE['tcc_session_key'] ) );
		}
		if ( '' === $session_key ) {
			return;
		}

		$context = array();
		if ( ! empty( $_COOKIE['bb_tfo_attribution'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw = rawurldecode( wp_unslash( $_COOKIE['bb_tfo_attribution'] ) );
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$context = $decoded;
			}
		}

		$payload = array(
			'email'        => $email,
			'session_key'  => $session_key,
			'source'       => 'hubspot',
			'source_id'    => (string) $form_id,
			'utm_source'   => isset( $context['utm_source'] ) ? sanitize_text_field( $context['utm_source'] ) : '',
			'utm_medium'   => isset( $context['utm_medium'] ) ? sanitize_text_field( $context['utm_medium'] ) : '',
			'utm_campaign' => isset( $context['utm_campaign'] ) ? sanitize_text_field( $context['utm_campaign'] ) : '',
			'utm_content'  => isset( $context['utm_content'] ) ? sanitize_text_field( $context['utm_content'] ) : '',
			'utm_term'     => isset( $context['utm_term'] ) ? sanitize_text_field( $context['utm_term'] ) : '',
			'landing_path' => isset( $context['landing_path'] ) ? sanitize_text_field( $context['landing_path'] ) : '',
			'referrer'     => isset( $context['referrer'] ) ? esc_url_raw( $context['referrer'] ) : '',
		);

		$body      = wp_json_encode( $payload );
		$signature = hash_hmac( 'sha256', $body, $secret );

		$response = wp_remote_post(
			rest_url( 'bb-tfo/v1/attribution/email-capture' ),
			array(
				'headers' => array(
					'Content-Type'   => 'application/json',
					'X-TCC-Signature' => $signature,
				),
				'body'    => $body,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Attempt internal dispatch fallback for local SSL issues.
			$error_message = $response->get_error_message();
			if ( false !== strpos( $error_message, 'SSL certificate problem' ) ) {
				$request = new \WP_REST_Request( 'POST', '/bb-tfo/v1/attribution/email-capture' );
				$request->set_header( 'Content-Type', 'application/json' );
				$request->set_header( 'X-TCC-Signature', $signature );
				$request->set_body( $body );
				$internal_response = rest_do_request( $request );

				if ( $internal_response && $internal_response->get_status() >= 200 && $internal_response->get_status() < 300 ) {
					return;
				}
			}
			Logger::log( 'Attribution capture request failed.', array( 'error' => $response->get_error_message() ) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			Logger::log( 'Attribution capture rejected.', array( 'status' => $code ) );
		}
	}

	/**
	 * Validate fields against schema.
	 *
	 * @param array $schema_map Field definitions (only visible).
	 * @param array $fields     Submitted fields.
	 * @return array
	 */
	private static function validate_fields( array $schema_map, array $fields ): array {
		$errors        = array();
		$block_domains = apply_filters( 'bb_hubspot_forms_block_email_domains', false );
		$can_block     = $block_domains && class_exists( '\BBHubspotForms\Spam\DomainBlocker' );

		foreach ( $schema_map as $name => $field_def ) {
			$type     = isset( $field_def['type'] ) ? $field_def['type'] : 'text';
			$required = ! empty( $field_def['required'] );
			$value    = isset( $fields[ $name ] ) ? $fields[ $name ] : '';

			if ( $required && '' === $value ) {
				$errors[ $name ] = 'This field is required.';
				continue;
			}

			if ( '' === $value ) {
				continue;
			}

			if ( 'email' === $type ) {
				if ( ! is_email( $value ) ) {
					$errors[ $name ] = 'Please enter a valid email address.';
					continue;
				}
				if ( $can_block && DomainBlocker::is_blocked( $value ) ) {
					$errors[ $name ] = 'Please enter a business email address.';
				}
			}

			if ( 'url' === $type && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$errors[ $name ] = 'Please enter a valid URL.';
			}
		}

		return $errors;
	}

	/**
	 * Sanitize value by type.
	 *
	 * @param string $value Field value.
	 * @param string $type  Field type.
	 * @return string
	 */
	private static function sanitize_value_by_type( string $value, string $type ): string {
		if ( '' === $value ) {
			return '';
		}
		if ( 'email' === $type ) {
			return sanitize_email( $value );
		}
		if ( 'url' === $type ) {
			return esc_url_raw( $value );
		}
		if ( 'tel' === $type ) {
			return sanitize_text_field( $value );
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Build consent payload from settings + user input.
	 *
	 * @param bool $user_consent_to_process User checked data processing consent.
	 * @param bool $user_marketing_consent  User checked marketing consent.
	 * @return array
	 */
	private static function build_consent_payload( bool $user_consent_to_process, bool $user_marketing_consent ): array {
		$consent_mode    = Settings::get( 'consent_mode', 'always' );
		$consent_text    = Settings::get( 'consent_text', '' );
		$marketing       = Settings::get( 'marketing_enabled', false );
		$marketing_text  = Settings::get( 'marketing_text', '' );
		$subscription_id = (int) Settings::get( 'subscription_type_id', 0 );

		// If consent mode is disabled, don't send consent payload.
		if ( 'disabled' === $consent_mode ) {
			return array();
		}

		// Default consent text if not set.
		if ( empty( $consent_text ) ) {
			$consent_text = __( 'I agree to allow this website to store and process my personal data.', 'bb-hubspot-forms' );
		}

		// Build base consent payload.
		$payload = array(
			'consent' => array(
				'consentToProcess' => $user_consent_to_process,
				'text'             => $consent_text,
			),
		);

		// Only add marketing consent if:
		// 1. Marketing is enabled in settings
		// 2. User actually checked the marketing box
		// 3. Subscription Type ID exists.
		if ( $marketing && $user_marketing_consent && $subscription_id > 0 ) {
			$payload['consent']['communications'] = array(
					array(
						'value'              => true,
						'subscriptionTypeId' => $subscription_id,
						'text'               => $marketing_text,
			),
		);
	}

		return $payload;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private static function get_client_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}

	/**
	 * Return error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return WP_REST_Response
	 */
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

	/**
	 * Return field errors response.
	 *
	 * @param array $errors Field errors.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
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
