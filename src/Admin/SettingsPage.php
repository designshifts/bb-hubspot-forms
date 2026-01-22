<?php

namespace BBHubspotForms\Admin;

use BBHubspotForms\Settings;

final class SettingsPage {
	private const CAPABILITY = 'manage_options';

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_menu(): void {
		add_options_page(
			__( 'BB HubSpot Forms', 'bb-hubspot-forms' ),
			__( 'BB HubSpot Forms', 'bb-hubspot-forms' ),
			self::CAPABILITY,
			'bb-hubspot-forms-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'bb_hubspot_forms_settings_group',
			Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'bb_hubspot_forms_settings_connection',
			__( 'HubSpot Connection', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_text_field(
			'portal_id',
			__( 'HubSpot Portal ID', 'bb-hubspot-forms' ),
			array(
				'placeholder' => __( 'e.g. 12345678', 'bb-hubspot-forms' ),
				'description' => __( 'Your HubSpot account ID. You can find this in HubSpot under Settings -> Account Setup -> Account Information.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_connection'
		);

		self::add_text_field(
			'private_token',
			__( 'Private App Access Token', 'bb-hubspot-forms' ),
			array(
				'type'        => 'password',
				'placeholder' => __( 'pat-na1-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'bb-hubspot-forms' ),
				'description' => __( 'A secure access token generated from a HubSpot Private App. Create one in HubSpot under Settings -> Integrations -> Private Apps. This plugin does not use OAuth or MCP Auth Apps.', 'bb-hubspot-forms' ),
				'note'        => __( 'This token is stored securely and is never exposed to the browser.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_connection'
		);

		self::add_custom_field(
			'test_connection',
			__( 'Test Connection', 'bb-hubspot-forms' ),
			array( __CLASS__, 'render_test_connection_field' ),
			'bb_hubspot_forms_settings_connection'
		);

		add_settings_section(
			'bb_hubspot_forms_settings_scopes',
			__( 'Required HubSpot Scopes', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_custom_field(
			'scopes_info',
			__( 'Scopes', 'bb-hubspot-forms' ),
			array( __CLASS__, 'render_scopes_info' ),
			'bb_hubspot_forms_settings_scopes'
		);

		add_settings_section(
			'bb_hubspot_forms_settings_captcha',
			__( 'CAPTCHA Settings', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_select_field(
			'captcha_provider',
			__( 'Captcha Provider', 'bb-hubspot-forms' ),
			array(
				''            => __( 'None (disabled)', 'bb-hubspot-forms' ),
				'recaptcha_v3' => __( 'reCAPTCHA v3', 'bb-hubspot-forms' ),
				'turnstile'    => __( 'Turnstile', 'bb-hubspot-forms' ),
				'hcaptcha'     => __( 'hCaptcha', 'bb-hubspot-forms' ),
			),
			__( 'Choose a CAPTCHA provider to help prevent spam submissions.', 'bb-hubspot-forms' ),
			'bb_hubspot_forms_settings_captcha'
		);

		self::add_text_field(
			'captcha_min_score',
			__( 'Captcha Min Score', 'bb-hubspot-forms' ),
			array(
				'placeholder' => __( '0.5', 'bb-hubspot-forms' ),
				'description' => __( 'Minimum reCAPTCHA v3 score required to accept a submission. Range: 0.0–1.0.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_captcha'
		);

		self::add_text_field(
			'captcha_expected_action',
			__( 'Captcha Expected Action', 'bb-hubspot-forms' ),
			array(
				'placeholder' => __( 'hubspot_form_submit', 'bb-hubspot-forms' ),
				'description' => __( 'Expected reCAPTCHA v3 action name used during verification.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_captcha'
		);

		self::add_text_field(
			'captcha_site_key',
			__( 'Captcha Site Key', 'bb-hubspot-forms' ),
			array(
				'description' => __( 'Your public CAPTCHA site key from your CAPTCHA provider.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_captcha'
		);

		self::add_text_field(
			'captcha_secret_key',
			__( 'Captcha Secret Key', 'bb-hubspot-forms' ),
			array(
				'type'        => 'password',
				'description' => __( 'Your private CAPTCHA secret key. This key is used server-side and is never exposed publicly.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_captcha'
		);

		add_settings_section(
			'bb_hubspot_forms_settings_form_ids',
			__( 'Form IDs', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_textarea_field(
			'form_ids',
			__( 'HubSpot Form IDs', 'bb-hubspot-forms' ),
			array(
				'placeholder' => __( "Newsletter Signup | 123e4567-e89b-12d3-a456-426614174000\nWebinar Registration | 123e4567-e89b-12d3-a456-426614174001", 'bb-hubspot-forms' ),
				'description' => __( 'Add one form per line. Use "Label | Form ID" for friendly names or just the Form ID. These will be available as a dropdown on each form.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_form_ids'
		);

		add_settings_section(
			'bb_hubspot_forms_settings_usage_notes',
			__( 'Form Usage Notes', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_custom_field(
			'form_usage_notes',
			__( 'How Forms Work', 'bb-hubspot-forms' ),
			array( __CLASS__, 'render_form_usage_notes' ),
			'bb_hubspot_forms_settings_usage_notes'
		);

		add_settings_section(
			'bb_hubspot_forms_settings_security',
			__( 'Security & Data Handling', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_custom_field(
			'security_notes',
			__( 'Security Notes', 'bb-hubspot-forms' ),
			array( __CLASS__, 'render_security_notes' ),
			'bb_hubspot_forms_settings_security'
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( $hook !== 'settings_page_bb-hubspot-forms-settings' ) {
			return;
		}

		wp_enqueue_style(
			'bb-hubspot-forms-admin-settings',
			BBHUBSPOT_FORMS_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			BBHUBSPOT_FORMS_VERSION
		);

		wp_enqueue_script(
			'bb-hubspot-forms-admin-settings',
			BBHUBSPOT_FORMS_PLUGIN_URL . 'assets/js/admin-settings.js',
			array(),
			BBHUBSPOT_FORMS_VERSION,
			true
		);
		wp_localize_script(
			'bb-hubspot-forms-admin-settings',
			'bbHubspotFormsSettings',
			array(
				'restUrl' => esc_url_raw( rest_url( 'hubspotform/v1/test-connection' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	private static function add_text_field( string $key, string $label, array $args = array(), string $section = 'bb_hubspot_forms_settings_main' ): void {
		add_settings_field(
			$key,
			$label,
			array( __CLASS__, 'render_text_field' ),
			'bb-hubspot-forms-settings',
			$section,
			array_merge( array( 'key' => $key ), $args )
		);
	}

	private static function add_textarea_field( string $key, string $label, array $args = array(), string $section = 'bb_hubspot_forms_settings_main' ): void {
		add_settings_field(
			$key,
			$label,
			array( __CLASS__, 'render_textarea_field' ),
			'bb-hubspot-forms-settings',
			$section,
			array_merge( array( 'key' => $key ), $args )
		);
	}

	private static function add_checkbox_field( string $key, string $label ): void {
		add_settings_field(
			$key,
			$label,
			array( __CLASS__, 'render_checkbox_field' ),
			'bb-hubspot-forms-settings',
			'bb_hubspot_forms_settings_main',
			array( 'key' => $key )
		);
	}

	private static function add_select_field( string $key, string $label, array $options, string $description = '', string $section = 'bb_hubspot_forms_settings_main' ): void {
		add_settings_field(
			$key,
			$label,
			array( __CLASS__, 'render_select_field' ),
			'bb-hubspot-forms-settings',
			$section,
			array(
				'key'         => $key,
				'options'     => $options,
				'description' => $description,
			)
		);
	}

	private static function add_custom_field( string $key, string $label, callable $render_callback, string $section ): void {
		add_settings_field(
			$key,
			$label,
			$render_callback,
			'bb-hubspot-forms-settings',
			$section,
			array( 'key' => $key )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BB HubSpot Forms', 'bb-hubspot-forms' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'bb_hubspot_forms_settings_group' );
				do_settings_sections( 'bb-hubspot-forms-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function render_text_field( array $args ): void {
		$key         = $args['key'];
		$options     = get_option( Settings::OPTION_KEY, array() );
		$value       = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$type        = isset( $args['type'] ) ? $args['type'] : 'text';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		$description = isset( $args['description'] ) ? $args['description'] : '';
		$note        = isset( $args['note'] ) ? $args['note'] : '';
		printf(
			'<input type="%1$s" class="regular-text" name="%2$s[%3$s]" value="%4$s" placeholder="%5$s" autocomplete="off" />',
			esc_attr( $type ),
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
		if ( $note ) {
			printf( '<p class="description"><em>%s</em></p>', esc_html( $note ) );
		}
	}

	public static function render_textarea_field( array $args ): void {
		$key         = $args['key'];
		$options     = get_option( Settings::OPTION_KEY, array() );
		$value       = isset( $options[ $key ] ) ? $options[ $key ] : array();
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		$description = isset( $args['description'] ) ? $args['description'] : '';
		$lines       = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $entry ) {
				if ( is_array( $entry ) ) {
					$id    = isset( $entry['id'] ) ? $entry['id'] : '';
					$label = isset( $entry['label'] ) ? $entry['label'] : '';
					if ( $id ) {
						$lines[] = ( $label && $label !== $id ) ? $label . ' | ' . $id : $id;
					}
				} elseif ( is_string( $entry ) && $entry !== '' ) {
					$lines[] = $entry;
				}
			}
		}

		printf(
			'<textarea class="large-text" rows="6" name="%1$s[%2$s]" placeholder="%3$s">%4$s</textarea>',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $placeholder ),
			esc_textarea( implode( "\n", $lines ) )
		);
		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	public static function render_checkbox_field( array $args ): void {
		$key     = $args['key'];
		$options = get_option( Settings::OPTION_KEY, array() );
		$checked = ! empty( $options[ $key ] );
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /></label>',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $key ),
			checked( $checked, true, false )
		);
	}

	public static function render_select_field( array $args ): void {
		$key         = $args['key'];
		$options     = get_option( Settings::OPTION_KEY, array() );
		$value       = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$description = isset( $args['description'] ) ? $args['description'] : '';
		echo '<select name="' . esc_attr( Settings::OPTION_KEY ) . '[' . esc_attr( $key ) . ']">';
		foreach ( $args['options'] as $option_value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	public static function render_scopes_info(): void {
		?>
		<div class="bb-hubspot-forms-scopes">
			<p class="bb-hubspot-forms-scopes__intro"><?php esc_html_e( 'Your Private App must include the following scopes to submit and validate forms securely.', 'bb-hubspot-forms' ); ?></p>
			<div class="bb-hubspot-forms-scopes__group">
				<h4><?php esc_html_e( 'Required', 'bb-hubspot-forms' ); ?></h4>
				<ul class="bb-hubspot-forms-scopes__list">
				<li><?php esc_html_e( 'forms', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'crm.objects.contacts.write', 'bb-hubspot-forms' ); ?></li>
				</ul>
				<p class="bb-hubspot-forms-scopes__note"><?php esc_html_e( 'These scopes are required to submit form data and create or update contacts in HubSpot.', 'bb-hubspot-forms' ); ?></p>
			</div>
			<div class="bb-hubspot-forms-scopes__group">
				<h4><?php esc_html_e( 'Recommended', 'bb-hubspot-forms' ); ?></h4>
				<ul class="bb-hubspot-forms-scopes__list">
				<li><?php esc_html_e( 'crm.objects.contacts.read', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'communication_preferences.read', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'communication_preferences.write', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'forms-uploaded-files (only if file uploads are used)', 'bb-hubspot-forms' ); ?></li>
				</ul>
				<p class="bb-hubspot-forms-scopes__note"><?php esc_html_e( 'Recommended scopes enable enhanced validation, consent handling, and advanced form features.', 'bb-hubspot-forms' ); ?></p>
			</div>
			<div class="bb-hubspot-forms-scopes__group">
				<h4><?php esc_html_e( 'Optional / Advanced', 'bb-hubspot-forms' ); ?></h4>
				<ul class="bb-hubspot-forms-scopes__list">
				<li><?php esc_html_e( 'crm.lists.read', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'crm.objects.companies.read', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'crm.objects.custom.read', 'bb-hubspot-forms' ); ?></li>
				</ul>
				<p class="bb-hubspot-forms-scopes__note"><?php esc_html_e( 'Optional scopes may be required for advanced integrations or future features. They are not required for basic form usage.', 'bb-hubspot-forms' ); ?></p>
			</div>
		</div>
		<?php
	}

	public static function render_form_usage_notes(): void {
		?>
		<div>
			<p><?php esc_html_e( 'Each HubSpot form used on your site requires a Form ID.', 'bb-hubspot-forms' ); ?></p>
			<p><?php esc_html_e( 'Form IDs are configured per form in the WordPress admin and selected from the list above.', 'bb-hubspot-forms' ); ?></p>
			<p><?php esc_html_e( 'You can find the Form ID in HubSpot under Marketing -> Forms -> Embed options.', 'bb-hubspot-forms' ); ?></p>
		</div>
		<?php
	}

	public static function render_security_notes(): void {
		?>
		<div>
			<ul>
				<li><?php esc_html_e( 'All form submissions are handled server-side.', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'Access tokens are never exposed to the browser.', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'CAPTCHA and consent checks are enforced before submission.', 'bb-hubspot-forms' ); ?></li>
				<li><?php esc_html_e( 'This plugin follows HubSpot\'s recommended integration practices.', 'bb-hubspot-forms' ); ?></li>
			</ul>
		</div>
		<?php
	}

	public static function render_test_connection_field(): void {
		?>
		<button type="button" class="button" id="bb-hubspot-forms-test-connection"><?php esc_html_e( 'Test HubSpot Connection', 'bb-hubspot-forms' ); ?></button>
		<span id="bb-hubspot-forms-test-result" style="margin-left: 10px;"></span>
		<?php
	}

	public static function sanitize( $input ): array {
		$output = array();
		if ( ! is_array( $input ) ) {
			return $output;
		}

		$output['portal_id']          = isset( $input['portal_id'] ) ? sanitize_text_field( $input['portal_id'] ) : '';
		$output['private_token']      = isset( $input['private_token'] ) ? sanitize_text_field( $input['private_token'] ) : '';
		$provider                     = isset( $input['captcha_provider'] ) ? sanitize_text_field( $input['captcha_provider'] ) : '';
		$provider                     = ( $provider === 'none' ) ? '' : $provider;
		$allowed_providers             = array( '', 'recaptcha_v3', 'turnstile', 'hcaptcha' );
		$output['captcha_provider']   = in_array( $provider, $allowed_providers, true ) ? $provider : '';
		$min_score                     = isset( $input['captcha_min_score'] ) ? (float) $input['captcha_min_score'] : 0.5;
		$min_score                     = max( 0.0, min( 1.0, $min_score ) );
		$output['captcha_min_score']  = $min_score;
		$expected_action               = isset( $input['captcha_expected_action'] ) ? sanitize_text_field( $input['captcha_expected_action'] ) : '';
		$output['captcha_expected_action'] = $expected_action ? $expected_action : 'hubspot_form_submit';
		$output['captcha_site_key']   = isset( $input['captcha_site_key'] ) ? sanitize_text_field( $input['captcha_site_key'] ) : '';
		$output['captcha_secret_key'] = isset( $input['captcha_secret_key'] ) ? sanitize_text_field( $input['captcha_secret_key'] ) : '';
		$output['form_ids']           = self::sanitize_form_ids( $input['form_ids'] ?? '' );

		return $output;
	}

	private static function sanitize_form_ids( $raw ): array {
		if ( ! is_string( $raw ) ) {
			return array();
		}

		$lines   = preg_split( '/\r\n|\r|\n/', $raw );
		$entries = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				continue;
			}

			$label = '';
			$id    = '';

			if ( strpos( $line, '|' ) !== false ) {
				$parts = array_map( 'trim', explode( '|', $line, 2 ) );
				$label = $parts[0] ?? '';
				$id    = $parts[1] ?? '';
			} else {
				$id = $line;
			}

			$id    = sanitize_text_field( $id );
			$label = sanitize_text_field( $label );

			if ( $id === '' ) {
				continue;
			}

			if ( $label === '' ) {
				$label = $id;
			}

			$entries[] = array(
				'id'    => $id,
				'label' => $label,
			);
		}

		return $entries;
	}
}

