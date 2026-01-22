<?php

namespace BBHubspotForms\Forms;

use BBHubspotForms\Security\Signer;
use BBHubspotForms\Settings;

final class Renderer {
	public static function register(): void {
		add_shortcode( 'hubspot_form', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		$frontend_path = BBHUBSPOT_FORMS_PLUGIN_DIR . 'assets/js/frontend.js';
		wp_register_script(
			'bb-hubspot-forms-frontend',
			BBHUBSPOT_FORMS_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			file_exists( $frontend_path ) ? filemtime( $frontend_path ) : BBHUBSPOT_FORMS_VERSION,
			true
		);
	}

	public static function render_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => '',
			),
			$atts,
			'hubspot_form'
		);

		$form_id = absint( $atts['id'] );
		if ( ! $form_id ) {
			return self::render_admin_notice( __( 'HubSpot form ID is missing.', 'bb-hubspot-forms' ) );
		}

		$schema         = get_post_meta( $form_id, 'hubspot_form_schema', true );
		$settings       = get_post_meta( $form_id, 'hubspot_form_settings', true );
		$hidden_fields  = get_post_meta( $form_id, 'hubspot_form_hidden_fields', true );
		$version        = get_post_meta( $form_id, 'hubspot_form_version', true );
		$token_ttl      = (int) get_post_meta( $form_id, 'hubspot_form_token_ttl', true );

		if ( empty( $schema ) || empty( $schema['fields'] ) ) {
			return self::render_admin_notice( __( 'Form schema is missing or invalid.', 'bb-hubspot-forms' ) );
		}

		$version   = $version ? $version : 'v1';
		$token_ttl = $token_ttl > 0 ? $token_ttl : 600;
		$token     = Signer::issue_token( $form_id, $version, $token_ttl );

		$captcha_provider = Settings::get( 'captcha_provider', '' );
		$captcha_site_key = Settings::get( 'captcha_site_key', '' );
		if ( $captcha_provider === 'recaptcha_v3' && $captcha_site_key ) {
			wp_enqueue_script(
				'bb-hubspot-forms-recaptcha',
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $captcha_site_key ),
				array(),
				null,
				true
			);
		}

		wp_enqueue_script( 'bb-hubspot-forms-frontend' );
		wp_localize_script(
			'bb-hubspot-forms-frontend',
			'bbHubspotFormsConfig',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'hubspotform/v1/submit' ) ),
				'captchaProvider' => $captcha_provider,
				'captchaSiteKey'  => $captcha_site_key,
				'captchaAction'   => Settings::get( 'captcha_expected_action', 'hubspot_form_submit' ),
			)
		);

		$form_attrs = array(
			'data-form-id'        => (string) $form_id,
			'data-schema-version' => esc_attr( $version ),
			'data-token'          => esc_attr( $token ),
			'data-redirect-url'   => isset( $settings['redirect_url'] ) ? esc_url( $settings['redirect_url'] ) : '',
			'data-append-email'   => ! empty( $settings['append_email_to_redirect'] ) ? '1' : '0',
		);

		$fields_html = self::render_fields( $schema['fields'] );
		$hidden_html = self::render_hidden_fields( $hidden_fields );

		$attributes = '';
		foreach ( $form_attrs as $key => $value ) {
			if ( $value === '' ) {
				continue;
			}
			$attributes .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		return sprintf(
			'<form class="bb-hubspot-forms-form"%1$s>%2$s%3$s<button type="submit">%4$s</button><div class="bb-hubspot-forms-form__message" aria-live="polite"></div></form>',
			$attributes,
			$fields_html,
			$hidden_html,
			esc_html__( 'Submit', 'bb-hubspot-forms' )
		);
	}

	private static function render_fields( array $fields ): string {
		$output = '';
		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
				continue;
			}

			$name     = sanitize_key( $field['name'] );
			$label    = isset( $field['label'] ) ? esc_html( $field['label'] ) : $name;
			$type     = $field['type'];
			$required = ! empty( $field['required'] );

			if ( $type === 'hidden' ) {
				continue;
			}

			$output .= '<label class="bb-hubspot-forms-form__field">';
			$output .= '<span class="bb-hubspot-forms-form__label">' . $label . '</span>';

			if ( $type === 'select' && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				$output .= '<select name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '>';
				foreach ( $field['options'] as $option ) {
					$output .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
				}
				$output .= '</select>';
			} elseif ( $type === 'textarea' ) {
				$output .= '<textarea name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '></textarea>';
			} else {
				$input_type = in_array( $type, array( 'text', 'email', 'tel', 'url' ), true ) ? $type : 'text';
				$output    .= '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . ' />';
			}

			$output .= '</label>';
		}

		return $output;
	}

	private static function render_hidden_fields( $hidden_fields ): string {
		if ( ! is_array( $hidden_fields ) ) {
			return '';
		}

		$output = '';
		foreach ( $hidden_fields as $field ) {
			if ( empty( $field['id'] ) ) {
				continue;
			}
			$output .= sprintf(
				'<input type="hidden" name="%1$s" value="%2$s" />',
				esc_attr( $field['id'] ),
				esc_attr( $field['value'] ?? '' )
			);
		}
		return $output;
	}

	private static function render_admin_notice( string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<div class="bb-hubspot-forms-form__notice">' . esc_html( $message ) . '</div>';
	}
}

