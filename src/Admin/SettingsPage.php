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
				'description' => __( 'A secure access token generated from a HubSpot Private App. Create one in HubSpot under Settings -> Integrations -> Private Apps. Enter to replace the saved token; leave blank to keep the current token.', 'bb-hubspot-forms' ),
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
				'input_class' => 'bb-hubspot-forms-recaptcha-field',
			),
			'bb_hubspot_forms_settings_captcha'
		);

		self::add_text_field(
			'captcha_expected_action',
			__( 'Captcha Expected Action', 'bb-hubspot-forms' ),
			array(
				'placeholder' => __( 'hubspot_form_submit', 'bb-hubspot-forms' ),
				'description' => __( 'Expected reCAPTCHA v3 action name used during verification.', 'bb-hubspot-forms' ),
				'input_class' => 'bb-hubspot-forms-recaptcha-field',
			),
			'bb_hubspot_forms_settings_captcha'
		);

		self::add_text_field(
			'captcha_site_key',
			__( 'Captcha Site Key', 'bb-hubspot-forms' ),
			array(
				'description' => __( 'Your public CAPTCHA site key from your CAPTCHA provider.', 'bb-hubspot-forms' ),
				'input_class' => 'bb-hubspot-forms-captcha-field',
			),
			'bb_hubspot_forms_settings_captcha'
		);

		self::add_text_field(
			'captcha_secret_key',
			__( 'Captcha Secret Key', 'bb-hubspot-forms' ),
			array(
				'type'        => 'password',
				'description' => __( 'Your private CAPTCHA secret key. This key is used server-side and is never exposed publicly.', 'bb-hubspot-forms' ),
				'input_class' => 'bb-hubspot-forms-captcha-field',
			),
			'bb_hubspot_forms_settings_captcha'
		);

		add_settings_section(
			'bb_hubspot_forms_settings_consent',
			__( 'GDPR Consent', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_select_field(
			'consent_mode',
			__( 'Consent Display Mode', 'bb-hubspot-forms' ),
			array(
				'always'   => __( 'Show consent (recommended)', 'bb-hubspot-forms' ),
				'disabled' => __( 'Disable consent (advanced)', 'bb-hubspot-forms' ),
			),
			__( 'For compliance and safety, consent checkboxes are shown by default.', 'bb-hubspot-forms' ),
			'bb_hubspot_forms_settings_consent'
		);

		self::add_textarea_field(
			'consent_text',
			__( 'Data Processing Consent Text', 'bb-hubspot-forms' ),
			array(
				'placeholder' => __( 'I agree to allow this website to store and process my personal data.', 'bb-hubspot-forms' ),
				'description' => __( 'This text is sent to HubSpot as the primary GDPR consent statement.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_consent'
		);

		self::add_checkbox_field(
			'marketing_enabled',
			__( 'Enable Marketing Opt-in Checkbox', 'bb-hubspot-forms' ),
			'bb_hubspot_forms_settings_consent',
			__( 'Add a checkbox for users who want to receive marketing emails.', 'bb-hubspot-forms' )
		);

		self::add_textarea_field(
			'marketing_text',
			__( 'Marketing Consent Text', 'bb-hubspot-forms' ),
			array(
				'placeholder' => __( 'I agree to receive marketing communications.', 'bb-hubspot-forms' ),
				'description' => __( 'Shown when marketing opt-in is enabled.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_consent'
		);

		self::add_text_field(
			'subscription_type_id',
			__( 'Subscription Type ID (optional)', 'bb-hubspot-forms' ),
			array(
				'type'        => 'number',
				'placeholder' => __( 'e.g. 466761704', 'bb-hubspot-forms' ),
				'description' => __( 'Optional. If provided, HubSpot can associate the marketing opt-in with a specific subscription category. If your HubSpot account exposes subscription type IDs, you can find them in the subscription type details page. If you can\'t find it, leave this blank — form submissions will still work correctly.', 'bb-hubspot-forms' ),
			),
			'bb_hubspot_forms_settings_consent'
		);

		add_settings_section(
			'bb_hubspot_forms_settings_appearance',
			__( 'Appearance', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_checkbox_field(
			'enable_default_styles',
			__( 'Enable Default Form Styles', 'bb-hubspot-forms' ),
			'bb_hubspot_forms_settings_appearance'
		);

		self::add_custom_field(
			'appearance_notes',
			__( 'Styling Notes', 'bb-hubspot-forms' ),
			array( __CLASS__, 'render_appearance_notes' ),
			'bb_hubspot_forms_settings_appearance'
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

		add_settings_section(
			'bb_hubspot_forms_settings_debug',
			__( 'Debug', 'bb-hubspot-forms' ),
			'__return_false',
			'bb-hubspot-forms-settings'
		);

		self::add_checkbox_field(
			'debug_enabled',
			__( 'Enable Debug Logging', 'bb-hubspot-forms' ),
			'bb_hubspot_forms_settings_debug'
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
			file_exists( BBHUBSPOT_FORMS_PLUGIN_DIR . 'assets/css/admin-settings.css' )
				? filemtime( BBHUBSPOT_FORMS_PLUGIN_DIR . 'assets/css/admin-settings.css' )
				: BBHUBSPOT_FORMS_VERSION
		);

		wp_enqueue_script(
			'bb-hubspot-forms-admin-settings',
			BBHUBSPOT_FORMS_PLUGIN_URL . 'assets/js/admin-settings.js',
			array(),
			file_exists( BBHUBSPOT_FORMS_PLUGIN_DIR . 'assets/js/admin-settings.js' )
				? filemtime( BBHUBSPOT_FORMS_PLUGIN_DIR . 'assets/js/admin-settings.js' )
				: BBHUBSPOT_FORMS_VERSION,
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

	private static function add_checkbox_field( string $key, string $label, string $section = 'bb_hubspot_forms_settings_main', string $description = '' ): void {
		add_settings_field(
			$key,
			$label,
			array( __CLASS__, 'render_checkbox_field' ),
			'bb-hubspot-forms-settings',
			$section,
			array(
				'key'         => $key,
				'description' => $description,
			)
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
			<?php
			if ( isset( $_GET['settings-updated'] ) ) {
				add_settings_error(
					'bb_hubspot_forms_settings_group',
					'settings_updated',
					__( 'Settings saved.', 'bb-hubspot-forms' ),
					'updated'
				);
			}

			if ( Settings::has_encryption_key() ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Encryption key detected. Tokens will be encrypted at rest.', 'bb-hubspot-forms' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-warning">';
				echo '<p><strong>' . esc_html__( 'Security setup required (one-time)', 'bb-hubspot-forms' ) . '</strong></p>';
				echo '<p>' . esc_html__( 'For security, HubSpot API tokens are encrypted before being stored.', 'bb-hubspot-forms' ) . '</p>';
				echo '<p>' . esc_html__( 'This prevents database leaks or backups from exposing sensitive credentials.', 'bb-hubspot-forms' ) . '</p>';
				echo '<p>' . esc_html__( 'A site administrator needs to add an encryption key to your WordPress configuration.', 'bb-hubspot-forms' ) . '</p>';
				echo '<p>' . esc_html__( 'If you’re not a developer: Send this message to your technical team or hosting provider.', 'bb-hubspot-forms' ) . '</p>';
				echo '<p><code>define( \'BB_HUBSPOT_ENCRYPTION_KEY\', \'generate-a-long-random-secret-key-here\' );</code></p>';
				echo '<p>' . esc_html__( 'After this is done, return here to save your HubSpot token.', 'bb-hubspot-forms' ) . '</p>';
				echo '</div>';
			}

			settings_errors( 'bb_hubspot_forms_settings_group' );
			?>
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
		$input_class = isset( $args['input_class'] ) ? $args['input_class'] : '';

		if ( $key === 'private_token' ) {
			$value = '';
			$stored = Settings::get_raw( 'private_token' );
			if ( $stored !== '' ) {
				$placeholder = __( 'Token saved — enter a new token to replace', 'bb-hubspot-forms' );
			}
		}
		printf(
			'<input type="%1$s" class="regular-text %2$s" name="%3$s[%4$s]" value="%5$s" placeholder="%6$s" autocomplete="off" />',
			esc_attr( $type ),
			esc_attr( $input_class ),
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
		if ( $note ) {
			$note_class = $key === 'private_token' ? 'bb-hubspot-forms-token-note' : '';
			printf( '<p class="description %1$s"><em>%2$s</em></p>', esc_attr( $note_class ), esc_html( $note ) );
		}
		if ( $key === 'private_token' ) {
			$stored = Settings::get_raw( 'private_token' );
			if ( $stored !== '' ) {
				printf( '<p class="bb-hubspot-forms-token-status">%s</p>', esc_html__( 'A token is currently saved.', 'bb-hubspot-forms' ) );
				if ( Settings::has_encryption_key() && ! Settings::is_encrypted_value( $stored ) ) {
					printf( '<p class="description">%s</p>', esc_html__( 'Saved token is not encrypted yet. Save settings to upgrade encryption.', 'bb-hubspot-forms' ) );
				}
			}
		}
	}

	public static function render_textarea_field( array $args ): void {
		$key         = $args['key'];
		$options     = get_option( Settings::OPTION_KEY, array() );
		$value       = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		$description = isset( $args['description'] ) ? $args['description'] : '';
		$input_class = isset( $args['input_class'] ) ? $args['input_class'] : '';

		printf(
			'<textarea class="large-text %1$s" rows="4" name="%2$s[%3$s]" placeholder="%4$s">%5$s</textarea>',
			esc_attr( $input_class ),
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $placeholder ),
			esc_textarea( is_string( $value ) ? $value : '' )
		);
		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	public static function render_checkbox_field( array $args ): void {
		$key         = $args['key'];
		$description = isset( $args['description'] ) ? $args['description'] : '';
		$options     = get_option( Settings::OPTION_KEY, array() );
		$checked     = ! empty( $options[ $key ] );
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /></label>',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $key ),
			checked( $checked, true, false )
		);
		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	public static function render_select_field( array $args ): void {
		$key         = $args['key'];
		$options     = get_option( Settings::OPTION_KEY, array() );
		$value       = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$description = isset( $args['description'] ) ? $args['description'] : '';
		$input_class = isset( $args['input_class'] ) ? $args['input_class'] : '';
		echo '<select class="' . esc_attr( $input_class ) . '" name="' . esc_attr( Settings::OPTION_KEY ) . '[' . esc_attr( $key ) . ']">';
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
			<p><?php esc_html_e( 'Forms are fetched directly from your HubSpot account using the Private App Token.', 'bb-hubspot-forms' ); ?></p>
			<p><?php esc_html_e( 'When creating a HubSpot Form post, select a form from the dropdown and click "Sync fields from HubSpot".', 'bb-hubspot-forms' ); ?></p>
			<p><?php esc_html_e( 'Use the generated shortcode to embed the form on any page or post.', 'bb-hubspot-forms' ); ?></p>
		</div>
		<?php
	}

	public static function render_appearance_notes(): void {
		?>
		<div>
			<p><?php esc_html_e( 'When enabled, the plugin applies clean, modern styling to form elements including inputs, labels, buttons, and validation messages.', 'bb-hubspot-forms' ); ?></p>
			<p><?php esc_html_e( 'Disable this option if you want to use your theme\'s styles or write custom CSS. All form elements use BEM-style classes prefixed with "bb-hubspot-forms-form".', 'bb-hubspot-forms' ); ?></p>
			<p><strong><?php esc_html_e( 'Available CSS classes:', 'bb-hubspot-forms' ); ?></strong></p>
			<ul style="margin-left: 1.5em; list-style: disc;">
				<li><code>.bb-hubspot-forms-form</code> — <?php esc_html_e( 'Form container', 'bb-hubspot-forms' ); ?></li>
				<li><code>.bb-hubspot-forms-form__field</code> — <?php esc_html_e( 'Field wrapper', 'bb-hubspot-forms' ); ?></li>
				<li><code>.bb-hubspot-forms-form__label</code> — <?php esc_html_e( 'Field label', 'bb-hubspot-forms' ); ?></li>
				<li><code>.bb-hubspot-forms-form__option</code> — <?php esc_html_e( 'Checkbox/radio option wrapper', 'bb-hubspot-forms' ); ?></li>
				<li><code>.bb-hubspot-forms-form__message</code> — <?php esc_html_e( 'Success/error message area', 'bb-hubspot-forms' ); ?></li>
			</ul>
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
		$existing_token               = Settings::get_raw( 'private_token' );
		$new_token                    = isset( $input['private_token'] ) ? sanitize_text_field( $input['private_token'] ) : '';
		$new_token                    = trim( $new_token );
		if ( $new_token === '' ) {
			$output['private_token'] = $existing_token;
		} elseif ( ! Settings::has_encryption_key() ) {
			add_settings_error(
				'bb_hubspot_forms_settings_group',
				'missing_encryption_key',
				__( 'Encryption key missing. Token was not saved.', 'bb-hubspot-forms' )
			);
			$output['private_token'] = $existing_token;
		} elseif ( Settings::is_encrypted_value( $new_token ) ) {
			add_settings_error(
				'bb_hubspot_forms_settings_group',
				'invalid_token_input',
				__( 'Token input appears encrypted. Please paste the plain token value.', 'bb-hubspot-forms' )
			);
			$output['private_token'] = $existing_token;
		} else {
			$output['private_token'] = Settings::encrypt_for_storage( $new_token );
		}
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
		$consent_mode                 = isset( $input['consent_mode'] ) ? sanitize_text_field( $input['consent_mode'] ) : 'always';
		$allowed_modes                = array( 'always', 'eu_only', 'disabled' );
		$output['consent_mode']       = in_array( $consent_mode, $allowed_modes, true ) ? $consent_mode : 'always';
		$output['consent_text']       = isset( $input['consent_text'] ) ? sanitize_textarea_field( $input['consent_text'] ) : '';
		$output['marketing_enabled']  = ! empty( $input['marketing_enabled'] );
		$output['marketing_text']     = isset( $input['marketing_text'] ) ? sanitize_textarea_field( $input['marketing_text'] ) : '';
		$output['subscription_type_id']   = isset( $input['subscription_type_id'] ) ? absint( $input['subscription_type_id'] ) : 0;
		$output['enable_default_styles']  = ! empty( $input['enable_default_styles'] );
		$output['debug_enabled']          = ! empty( $input['debug_enabled'] );

		return $output;
	}

}

