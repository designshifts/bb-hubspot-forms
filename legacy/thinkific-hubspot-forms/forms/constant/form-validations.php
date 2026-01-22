<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/blocked-domains.php';

/**
 * Validate Google's reCAPTCHA response.
 *
 * @param string $recaptcha_response The reCAPTCHA response token.
 * @param string $recaptcha_secret The reCAPTCHA secret key.
 * @param float  $threshold Minimum score threshold for reCAPTCHA v3.
 * @return array Validation result with success boolean and message.
 */
function hubspot_validate_recaptcha( $recaptcha_response, $recaptcha_secret, $threshold = 0.5 ) {
	// Allow form submission if reCAPTCHA is not available (for lazy loading scenarios)
	if ( empty( $recaptcha_response ) ) {
		error_log( '[HubSpot Form] reCAPTCHA response missing - allowing submission (lazy loading scenario)' );
		return array(
			'success' => true,
			'message' => __( 'reCAPTCHA not available - proceeding without validation.', 'think-blocks' ),
		);
	}

	// Google's reCAPTCHA verification URL.
	$verify_url = 'https://www.google.com/recaptcha/api/siteverify';

	// Send a POST request to verify the response.
	$response = wp_remote_post(
		$verify_url,
		array(
			'body' => array(
				'secret'   => $recaptcha_secret,
				'response' => $recaptcha_response,
			),
		)
	);

	// Handle errors in the API call.
	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'message' => __( 'Failed to verify reCAPTCHA. Please try again.', 'think-blocks' ),
		);
	}

	// Retrieve response and decode JSON.
	$response_body = wp_remote_retrieve_body( $response );
	$response_data = json_decode( $response_body, true );

	// Validate the reCAPTCHA response.
	if ( ! isset( $response_data['success'] ) || ! $response_data['success'] ) {
		return array(
			'success' => false,
			'message' => __( 'reCAPTCHA verification failed.', 'think-blocks' ),
		);
	}

	// Validate the score for reCAPTCHA v3.
	if ( isset( $response_data['score'] ) && $response_data['score'] < $threshold ) {
		return array(
			'success' => false,
			'message' => __( 'reCAPTCHA score is too low. Please try again.', 'think-blocks' ),
		);
	}

	// Success.
	return array(
		'success' => true,
		'message' => __( 'reCAPTCHA validation passed.', 'think-blocks' ),
	);
}

/**
 * Validate EU/UK Consent checkbox.
 * Covers EEA, UK and Switzerland. The EEA comprises the EU Member States and Iceland, Liechtenstein, and Norway.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return array Validation result with success boolean and optional errors.
 */
function hubspot_validate_eu_consent( $request ) {
	$geoip_country_code = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'not_identified_cloudflare_header_not_responding';
	$eu_countries       = array(
		'AT',
		'BE',
		'BG',
		'CY',
		'CZ',
		'DE',
		'DK',
		'EE',
		'ES',
		'FI',
		'FR',
		'GR',
		'HR',
		'HU',
		'IE',
		'IT',
		'LT',
		'LU',
		'LV',
		'MT',
		'NL',
		'PL',
		'PT',
		'RO',
		'SE',
		'SI',
		'SK',
		'IS',
		'LI',
		'NO',
		'GB',
		'CH',
	);
	$is_eu_user         = in_array( $geoip_country_code, $eu_countries, true );

	// Retrieve consent value.
	$legal_consent = $request->get_param( 'legalConsentOptions' )['consent']['consentToProcess'] ?? 'false';

	if ( $is_eu_user && $legal_consent !== 'true' ) {
		return array(
			'success' => false,
			'errors'  => array( 'legalConsentOptions' => __( 'You must agree to the terms and conditions to proceed.', 'think-blocks' ) ),
		);
	}

	return array( 'success' => true );
}

/**
 * Get the real IP address of the user.
 * Handles various proxy scenarios including Kinsta's setup.
 *
 * @return string The user's IP address.
 */
function hubspot_get_user_ip() {
	if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		// Get the first IP address in the X-Forwarded-For header for Kinsta.
		$ip_list    = explode( ',', sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$ip_address = trim( $ip_list[0] );
	} elseif ( isset( $_SERVER['HTTP_CLIENT_IP'] ) && ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		$ip_address = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
	} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip_address = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
	} else {
		$ip_address = '0.0.0.0'; // Fallback IP.
	}

	return $ip_address;
}

/**
 * Check if a field is required in the given form HTML.
 * Note: This function may be unused - consider removal if not needed.
 *
 * @param string $field_name The field name to check.
 * @param string $form_html The form HTML to parse.
 * @return bool True if field is required, false otherwise.
 */
function is_field_required( $field_name, $form_html ) {
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( "<root>$form_html</root>" );

	if ( ! $xml ) {
		libxml_clear_errors();
		return false;
	}

	$inputs = $xml->xpath( "//*[@name='$field_name']" );

	foreach ( $inputs as $input ) {
		if ( isset( $input['required'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Validate a required field.
 *
 * @param string $field_value The field value to validate.
 * @param string $field_name The field name for error messages.
 * @return string|null Error message if validation fails, null if valid.
 */
function hubspot_validate_required_field( $field_value, $field_name ) {
	if ( empty( trim( $field_value ) ) ) {
		return sprintf( __( '%s is required.', 'think-blocks' ), $field_name );
	}
	return null;
}

/**
 * Validate an email field, checking both format and blocked domains.
 *
 * @param string $field_value The email address to validate.
 * @param bool   $block_email_domains Whether to check against blocked domains.
 * @return string|null Error message if validation fails, null if valid.
 */
function hubspot_validate_email_field( $field_value, $block_email_domains = true ) {
	// Check for valid email format.
	if ( ! filter_var( $field_value, FILTER_VALIDATE_EMAIL ) ) {
		return __( 'Invalid email format.', 'think-blocks' );
	}

	// If blockEmailDomains is OFF, skip domain validation.
	if ( ! $block_email_domains ) {
		return null;
	}

	// Extract the domain from the email.
	$domain = trim( strtolower( substr( strrchr( $field_value, '@' ), 1 ) ) );

	// Get blocked domains and check if the domain is blocked.
	$blocked_domains = hubspot_get_blocked_domains();
	if ( in_array( $domain, $blocked_domains, true ) ) {
		return __( 'Please provide a business email.', 'think-blocks' );
	}

	return null;
}

/**
 * Validate a website URL field.
 *
 * @param string $field_value The URL to validate.
 * @return string|null Error message if validation fails, null if valid.
 */
function hubspot_validate_website_field( $field_value ) {
	if ( ! filter_var( $field_value, FILTER_VALIDATE_URL ) ) {
		return __( 'Please enter a valid URL.', 'think-blocks' );
	}
	return null;
}

/**
 * Validate a phone number field.
 *
 * @param string $field_value The phone number to validate.
 * @return string|null Error message if validation fails, null if valid.
 */
function hubspot_validate_phone_field( $field_value ) {
	if ( empty( $field_value ) ) {
		return __( 'Phone number is required.', 'think-blocks' );
	}

	// Basic pattern: allows digits, spaces, parentheses, hyphens, plus sign etc.
	$pattern = '/^[0-9\-\(\)\+\s]+$/';
	if ( ! preg_match( $pattern, $field_value ) ) {
		return __( 'Please enter a valid phone number.', 'think-blocks' );
	}

	return null;
}

/**
 * Validate a numeric field.
 *
 * @param string $field_value The numeric value to validate.
 * @return string|null Error message if validation fails, null if valid.
 */
function hubspot_validate_numeric_field( $field_value ) {
	if ( empty( $field_value ) ) {
		return __( 'This field is required.', 'think-blocks' );
	}

	if ( ! is_numeric( $field_value ) ) {
		return __( 'Please enter a numeric value.', 'think-blocks' );
	}

	return null;
}

/**
 * Validate a social media handle field.
 *
 * @param string $field_value The handle value to validate.
 * @param string $platform The social media platform (instagram, youtube, tiktok, twitter, linkedin).
 * @return string|null Error message if validation fails, null if valid.
 */
function hubspot_validate_social_handle_field( $field_value, $platform = '' ) {
	// Check if field is empty
	if ( empty( trim( $field_value ) ) ) {
		return sprintf( __( '%s handle is required.', 'think-blocks' ), ucfirst( $platform ) );
	}

	// Remove @ symbol if present
	$handle = ltrim( trim( $field_value ), '@' );

	// Basic validation rules for social media handles
	$validation_rules = array(
		'instagram' => array(
			'pattern' => '/^[a-zA-Z0-9._]{1,30}$/',
			'message' => __( 'Instagram handle must be 1-30 characters and can only contain letters, numbers, periods, and underscores.', 'think-blocks' ),
		),
		'youtube'   => array(
			'pattern' => '/^[a-zA-Z0-9_-]{3,30}$/',
			'message' => __( 'YouTube handle must be 3-30 characters and can only contain letters, numbers, hyphens, and underscores.', 'think-blocks' ),
		),
		'tiktok'    => array(
			'pattern' => '/^[a-zA-Z0-9._]{2,24}$/',
			'message' => __( 'TikTok handle must be 2-24 characters and can only contain letters, numbers, periods, and underscores.', 'think-blocks' ),
		),
		'twitter'   => array(
			'pattern' => '/^[a-zA-Z0-9_]{1,15}$/',
			'message' => __( 'Twitter/X handle must be 1-15 characters and can only contain letters, numbers, and underscores.', 'think-blocks' ),
		),
		'linkedin'  => array(
			'pattern' => '/^[a-zA-Z0-9-]{3,100}$/',
			'message' => __( 'LinkedIn handle must be 3-100 characters and can only contain letters, numbers, and hyphens.', 'think-blocks' ),
		),
	);

	// If platform is specified, use platform-specific validation
	if ( ! empty( $platform ) && isset( $validation_rules[ $platform ] ) ) {
		$rule = $validation_rules[ $platform ];
		if ( ! preg_match( $rule['pattern'], $handle ) ) {
			return $rule['message'];
		}
	} else {
		// Generic validation for any social media handle
		if ( ! preg_match( '/^[a-zA-Z0-9._-]{1,100}$/', $handle ) ) {
			return __( 'Social media handle can only contain letters, numbers, periods, underscores, and hyphens.', 'think-blocks' );
		}
	}

	// Check for common invalid patterns
	$invalid_patterns = array(
		'/^[0-9]+$/', // Only numbers
		'/^[._-]+$/', // Only special characters
		'/^[._-]/',   // Starts with special character
		'/[._-]$/',   // Ends with special character
		'/\.{2,}/',   // Multiple consecutive periods
		'/_{2,}/',    // Multiple consecutive underscores
		'/-{2,}/',    // Multiple consecutive hyphens
	);

	foreach ( $invalid_patterns as $pattern ) {
		if ( preg_match( $pattern, $handle ) ) {
			return __( 'Invalid handle format. Please check your social media handle.', 'think-blocks' );
		}
	}

	return null;
}

/**
 * Validate social media platform selection.
 *
 * @param string $platform The selected platform.
 * @param array  $enabled_platforms Array of enabled platforms.
 * @return string|null Error message if validation fails, null if valid.
 */
function hubspot_validate_social_platform_selection( $platform, $enabled_platforms = array() ) {
	if ( empty( $platform ) ) {
		return __( 'Please select a social media platform.', 'think-blocks' );
	}

	$valid_platforms = array( 'instagram', 'youtube', 'tiktok', 'twitter', 'linkedin', 'other' );

	if ( ! in_array( $platform, $valid_platforms, true ) ) {
		return __( 'Invalid social media platform selected.', 'think-blocks' );
	}

	// If enabled platforms are specified, check if the selected platform is enabled
	if ( ! empty( $enabled_platforms ) && ! in_array( $platform, $enabled_platforms, true ) ) {
		return __( 'Selected platform is not available.', 'think-blocks' );
	}

	return null;
}
