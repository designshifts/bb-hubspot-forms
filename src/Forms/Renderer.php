<?php
/**
 * HubSpot Form shortcode renderer.
 *
 * @package BBHubspotForms
 */

namespace BBHubspotForms\Forms;

use BBHubspotForms\Security\Signer;
use BBHubspotForms\Settings;

/**
 * Renders the hubspot_form shortcode.
 */
final class Renderer {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'hubspot_form', array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( 'bb_hubspot_form', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public static function register_assets(): void {
		$frontend_js_path = BBHUBSPOT_FORMS_PLUGIN_DIR . 'assets/js/frontend.js';
		wp_register_script(
			'bb-hubspot-forms-frontend',
			BBHUBSPOT_FORMS_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			file_exists( $frontend_js_path ) ? filemtime( $frontend_js_path ) : BBHUBSPOT_FORMS_VERSION,
			true
		);

		// Add config as inline script BEFORE the main script.
		$captcha_provider = Settings::get( 'captcha_provider', '' );
		$captcha_site_key = Settings::get( 'captcha_site_key', '' );
		$config           = array(
			'restUrl'         => esc_url_raw( rest_url( 'hubspotform/v1/submit' ) ),
			'captchaProvider' => $captcha_provider,
			'captchaSiteKey'  => $captcha_site_key,
			'captchaAction'   => Settings::get( 'captcha_expected_action', 'hubspot_form_submit' ),
		);
		wp_add_inline_script(
			'bb-hubspot-forms-frontend',
			'window.bbHubspotFormsConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		// Register CSS (conditionally enqueued later if enabled).
		$frontend_css_path = BBHUBSPOT_FORMS_PLUGIN_DIR . 'assets/css/frontend.css';
		wp_register_style(
			'bb-hubspot-forms-frontend',
			BBHUBSPOT_FORMS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			file_exists( $frontend_css_path ) ? filemtime( $frontend_css_path ) : BBHUBSPOT_FORMS_VERSION
		);
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
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

		// Load new meta structure.
		$schema    = get_post_meta( $form_id, '_bbhs_schema', true );
		$overrides = get_post_meta( $form_id, '_bbhs_overrides', true );
		$token_ttl = (int) get_post_meta( $form_id, '_bbhs_token_ttl', true );

		if ( empty( $schema ) || empty( $schema['fields'] ) ) {
			return self::render_admin_notice( __( 'Form schema is missing or invalid.', 'bb-hubspot-forms' ) );
		}

		$overrides = is_array( $overrides ) ? $overrides : array(
			'order'  => array(),
			'hidden' => array(),
			'labels' => array(),
		);
		$token_ttl = $token_ttl > 0 ? $token_ttl : 600;
		$version   = 'v2';
		$token     = Signer::issue_token( $form_id, $version, $token_ttl );

		// Enqueue CAPTCHA if configured.
		$captcha_provider = Settings::get( 'captcha_provider', '' );
		$captcha_site_key = Settings::get( 'captcha_site_key', '' );
		if ( 'recaptcha_v3' === $captcha_provider && $captcha_site_key ) {
			wp_enqueue_script(
				'bb-hubspot-forms-recaptcha',
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $captcha_site_key ),
				array(),
				null,
				true
			);
		}

		wp_enqueue_script( 'bb-hubspot-forms-frontend' );

		// Enqueue default styles if enabled.
		if ( Settings::get( 'enable_default_styles', true ) ) {
			wp_enqueue_style( 'bb-hubspot-forms-frontend' );
		}

		$form_attrs = array(
			'data-form-id'        => (string) $form_id,
			'data-schema-version' => esc_attr( $version ),
			'data-token'          => esc_attr( $token ),
		);

		$fields_html = self::render_fields( $schema['fields'], $overrides );

		$attributes = '';
		foreach ( $form_attrs as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$attributes .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		return sprintf(
			'<form class="bb-hubspot-forms-form" method="post" novalidate%1$s>%2$s<button type="submit">%3$s</button><div class="bb-hubspot-forms-form__message" aria-live="polite"></div></form>',
			$attributes,
			$fields_html,
			esc_html__( 'Submit', 'bb-hubspot-forms' )
		);
	}

	/**
	 * Render form fields.
	 *
	 * @param array $fields    Schema fields.
	 * @param array $overrides User overrides.
	 * @return string
	 */
	private static function render_fields( array $fields, array $overrides ): string {
		$output = '';
		$allowed_types = apply_filters(
			'bb_hs_allowed_field_types',
			array( 'text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'radio', 'url' )
		);

		$order  = isset( $overrides['order'] ) && is_array( $overrides['order'] ) ? $overrides['order'] : array();
		$hidden = isset( $overrides['hidden'] ) && is_array( $overrides['hidden'] ) ? $overrides['hidden'] : array();
		$labels = isset( $overrides['labels'] ) && is_array( $overrides['labels'] ) ? $overrides['labels'] : array();

		// Build field map.
		$field_map = array();
		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}
			$field_map[ $field['name'] ] = $field;
		}

		// Build ordered list.
		$ordered = array();
		foreach ( $order as $name ) {
			if ( isset( $field_map[ $name ] ) ) {
				$ordered[] = $field_map[ $name ];
				unset( $field_map[ $name ] );
			}
		}
		// Add remaining fields not in order.
		foreach ( $fields as $field ) {
			if ( isset( $field_map[ $field['name'] ] ) ) {
				$ordered[] = $field;
			}
		}

		foreach ( $ordered as $field ) {
			$name = $field['name'];

			// Skip hidden fields.
			if ( in_array( $name, $hidden, true ) ) {
				continue;
			}

			$type = isset( $field['type'] ) ? $field['type'] : 'text';
			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}

			// Get label (override or original).
			$label = isset( $labels[ $name ] ) && '' !== trim( $labels[ $name ] )
				? esc_html( $labels[ $name ] )
				: ( isset( $field['label'] ) ? esc_html( $field['label'] ) : esc_html( $name ) );

			$required = ! empty( $field['required'] );
			$options  = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();

			$output .= '<label class="bb-hubspot-forms-form__field">';
			$output .= '<span class="bb-hubspot-forms-form__label">' . $label . '</span>';

			if ( 'select' === $type && ! empty( $options ) ) {
				$output .= '<select name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '>';
				$output .= '<option value="">' . esc_html__( '— Select —', 'bb-hubspot-forms' ) . '</option>';
				foreach ( $options as $option ) {
					$output .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
				}
				$output .= '</select>';
			} elseif ( 'radio' === $type && ! empty( $options ) ) {
				foreach ( $options as $option ) {
					$output .= '<label class="bb-hubspot-forms-form__option">';
					$output .= '<input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $option ) . '"' . ( $required ? ' required' : '' ) . ' />';
					$output .= '<span>' . esc_html( $option ) . '</span>';
					$output .= '</label>';
				}
			} elseif ( 'checkbox' === $type ) {
				$output .= '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . ( $required ? ' required' : '' ) . ' />';
			} elseif ( 'textarea' === $type ) {
				$output .= '<textarea name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '></textarea>';
			} else {
				$input_type = in_array( $type, array( 'text', 'email', 'tel', 'url' ), true ) ? $type : 'text';
				$output    .= '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . ' />';
			}

			$output .= '</label>';
		}

		return $output;
	}

	/**
	 * Render admin notice (only visible to editors).
	 *
	 * @param string $message Notice message.
	 * @return string
	 */
	private static function render_admin_notice( string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<div class="bb-hubspot-forms-form__notice">' . esc_html( $message ) . '</div>';
	}
}
