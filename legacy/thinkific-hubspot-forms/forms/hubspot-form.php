<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * Permission callback for MasterForm REST API routes.
 */
function hubspotform_permissions_check( WP_REST_Request $request ) {
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'rest_forbidden', 'Invalid nonce.', array( 'status' => 403 ) );
	}

	// Enhanced rate limiting: 50 requests per minute per IP with burst allowance
	// Set to true to enable rate limiting for production
	$enable_rate_limiting = true;

	if ( $enable_rate_limiting ) {
		$ip               = hubspot_get_user_ip();
		$rate_limit_key   = 'hubspot_api_rate_limit_' . md5( $ip );
		$burst_key        = 'hubspot_api_burst_' . md5( $ip );
		$current_requests = get_transient( $rate_limit_key );
		$burst_count      = get_transient( $burst_key );

		// Check burst limit (10 requests within 15 seconds)
		if ( $burst_count === false ) {
			set_transient( $burst_key, 1, 15 ); // 15 seconds
		} elseif ( $burst_count >= 10 ) {
			error_log( 'HubSpot Forms: Burst limit exceeded - IP: ' . $ip . ', Burst Count: ' . $burst_count );
			return new WP_Error( 'rest_rate_limited', 'Too many rapid requests. Please wait a moment and try again.', array( 'status' => 429 ) );
		} else {
			set_transient( $burst_key, $burst_count + 1, 15 );
		}

		// Check overall rate limit (50 requests per minute)
		if ( $current_requests === false ) {
			set_transient( $rate_limit_key, 1, 60 ); // 1 minute
		} elseif ( $current_requests >= 50 ) {
			error_log( 'HubSpot Forms: Rate limit exceeded - IP: ' . $ip . ', Current Requests: ' . $current_requests );
			return new WP_Error( 'rest_rate_limited', 'Too many requests. Please try again later.', array( 'status' => 429 ) );
		} else {
			set_transient( $rate_limit_key, $current_requests + 1, 60 );
		}
	}

	return true;
}

/**
 * Handle REST API form submission.
 */
function hubspot_rest_handle_submission( WP_REST_Request $request ) {
	// Check if required constants are defined
	if ( ! defined( 'HUBSPOT_PRIVATE_TOKEN' ) || empty( HUBSPOT_PRIVATE_TOKEN ) ) {
		error_log( 'HubSpot Forms: HUBSPOT_PRIVATE_TOKEN is not defined or empty' );
		return new WP_REST_Response(
			array(
				'success' => false,
				'errors'  => array( 'submission' => 'Server configuration error. Please contact support.' ),
			),
			500
		);
	}

	if ( ! defined( 'HUBSPOT_PORTAL_ID' ) || empty( HUBSPOT_PORTAL_ID ) ) {
		error_log( 'HubSpot Forms: HUBSPOT_PORTAL_ID is not defined or empty' );
		return new WP_REST_Response(
			array(
				'success' => false,
				'errors'  => array( 'submission' => 'Server configuration error. Please contact support.' ),
			),
			500
		);
	}

	if ( ! defined( 'RECAPTCHA_SECRET_KEY' ) || empty( RECAPTCHA_SECRET_KEY ) ) {
		error_log( 'HubSpot Forms: RECAPTCHA_SECRET_KEY is not defined or empty' );
		return new WP_REST_Response(
			array(
				'success' => false,
				'errors'  => array( 'submission' => 'Server configuration error. Please contact support.' ),
			),
			500
		);
	}

	// Debug logging
	error_log( 'HubSpot Forms: Starting form submission processing' );

	// Get EU consent and validate.
	$consent_validation = hubspot_validate_eu_consent( $request );

	if ( ! $consent_validation['success'] ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'errors'  => $consent_validation['errors'],
			),
			400
		);
	}

	// Get the reCAPTCHA response and secret key.
	$recaptcha_response = $request->get_param( 'g-recaptcha-response' );
	$recaptcha_secret   = RECAPTCHA_SECRET_KEY;

	if ( empty( $recaptcha_secret ) ) {
		error_log( '[HubSpot Form] reCAPTCHA secret key not configured' );
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Server configuration error.',
			),
			500
		);
	}

	// Validate reCAPTCHA.
	$recaptcha_validation = hubspot_validate_recaptcha( $recaptcha_response, $recaptcha_secret );

	if ( ! $recaptcha_validation['success'] ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $recaptcha_validation['message'],
			),
			400
		);
	}

	// Retrieve parameters from the request.
	$params = $request->get_json_params();

	// Ensure the form ID is explicitly passed in the request.
	$form_id = ! empty( $params['formId'] ) ? sanitize_text_field( $params['formId'] ) : '';

	// A valid Form ID is required unless a redirect URL is specified.
	$redirect_url = sanitize_text_field( $params['redirectUrl'] ?? '' );

	if ( empty( $form_id ) && empty( $redirect_url ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'errors'  => array( 'formId' => 'A valid Form ID is required unless a redirect URL is specified.' ),
			),
			400
		);
	}

	// Extract and sanitize inputs.
	$name                        = sanitize_text_field( $params['firstname'] ?? '' );
	$last                        = sanitize_text_field( $params['lastname'] ?? '' );
	$email                       = sanitize_email( $params['email'] ?? '' );
	$company                     = sanitize_text_field( $params['company'] ?? '' );
	$raw_phone                   = $params['phone'] ?? '';
	$website                     = sanitize_url( $params['website'] ?? '' );
	$primary_social_media_handle = sanitize_text_field( $params['primary_social_media_handle'] ?? '' );
	$primary_social_platform     = sanitize_text_field( $params['primary_social_platform'] ?? '' );
	$instagram_handle            = sanitize_text_field( $params['instagram_handle'] ?? '' );
	$youtube_handle              = sanitize_text_field( $params['youtube_handle'] ?? '' );
	$tiktok_handle               = sanitize_text_field( $params['tiktok_handle'] ?? '' );
	$twitter_handle              = sanitize_text_field( $params['twitter_handle'] ?? '' );
	$linkedin                    = sanitize_text_field( $params['linkedin'] ?? '' );
	$employee_range__c           = sanitize_text_field( $params['employee_range__c'] ?? '' );
	$probable_use_case           = sanitize_text_field( $params['probable_use_case'] ?? '' );
	$consent                     = isset( $params['consent'] ) && $params['consent'] === 'true' ? 'true' : 'false';
	$gdpr_opted_in               = sanitize_text_field( $params['gdpr_opted_in'] ?? 'Not Applicable' ); // Default to 'Not Applicable' if not provided
	$hutk                        = sanitize_text_field( $params['hutk'] ?? '' );
	$page_uri                    = sanitize_text_field( $params['pageUri'] ?? '' );
	$page_name                   = sanitize_text_field( $params['pageName'] ?? '' );
	$block_email_domains         = isset( $params['blockEmailDomains'] ) ? filter_var( $params['blockEmailDomains'], FILTER_VALIDATE_BOOLEAN ) : false;
	$downloadable_id             = sanitize_text_field( $params['downloadable_id'] ?? '' );
	$page_url                    = sanitize_text_field( $params['page_url'] ?? '' );
	$event_trade_show_details__c = sanitize_text_field( $params['event_trade_show_details__c'] ?? '' );
	$partnerstack_xid            = sanitize_text_field( $params['partnerstack_xid'] ?? '' );
	$partnerstack_partner_key__c = sanitize_text_field( $params['partnerstack_partner_key__c'] ?? '' );
	// $mixpanel_id                 = sanitize_text_field( $params['mixpanel_id'] ?? '' );

	// $cookie_name  = 'mp_e9f85a260e22673665c335ea07907e45_mixpanel';

	// if (! empty($_COOKIE[$cookie_name]) && $mixpanel_id == '') {
	// $raw  = $_COOKIE[$cookie_name];
	// $data = json_decode(stripslashes($raw), true);
	// if (json_last_error() !== JSON_ERROR_NONE) {
	// $data = json_decode(urldecode($raw), true);
	// }
	// if (is_array($data)) {
	// $mixpanel_id = $data['distinct_id'] ?? ($data['$device_id'] ?? '');
	// }
	// }

	// Phone field: Keep + if it's the first character, then strip all other non-numeric characters.
	if ( str_starts_with( $raw_phone, '+' ) ) {
		$phone = '+' . preg_replace( '/[^0-9]/', '', substr( $raw_phone, 1 ) );
	} else {
		$phone = preg_replace( '/[^0-9]/', '', $raw_phone );
	}

	$errors = array();

	$visible_fields = json_decode( $request->get_param( 'visible_fields' ) ?? '', true );

	// If visible_fields is not a valid array (e.g. fresh form with no prefill), skip dynamic validation.
	if ( is_array( $visible_fields ) && ! empty( $visible_fields ) ) {
		foreach ( $visible_fields as $field ) {
			if ( isset( $$field ) ) {
				$value               = $$field;
				$validation_function = "hubspot_validate_{$field}_field";
				if ( function_exists( $validation_function ) ) {
					// Use block_email_domains specifically for the email field
					if ( $field === 'email' ) {
						$error = $validation_function( $value, $block_email_domains );
					} else {
						$error = $validation_function( $value, ucfirst( $field ) );
					}
					if ( $error ) {
						$errors[ $field ] = $error;
					}
				}
			}
		}
	} else {
		// No visible_fields provided — fallback to standard required fields
		if ( isset( $email ) ) {
			$validation_error = hubspot_validate_email_field( $email, $block_email_domains );
			if ( $validation_error ) {
				$errors['email'] = $validation_error;
			}
		} else {
			$errors['email'] = 'Email is missing.';
		}
	}

	$consent_error = hubspot_validate_required_field( $consent, 'Consent' );
	if ( $consent_error ) {
		$errors['consent'] = $consent_error;
	}

	// Validate social media platform selection and handle
	if ( ! empty( $social_platform ) ) {
		// Validate platform selection
		$platform_error = hubspot_validate_social_platform_selection( $social_platform );
		if ( $platform_error ) {
			$errors['social_platform'] = $platform_error;
		}

		// Validate handle if platform is not 'other'
		if ( $social_platform !== 'other' ) {
			$handle_field = $social_platform . '_handle';
			$handle_value = $$handle_field ?? '';

			$handle_error = hubspot_validate_social_handle_field( $handle_value, $social_platform );
			if ( $handle_error ) {
				$errors[ $handle_field ] = $handle_error;
			}
		}
	}

	if ( empty( $form_id ) && ! empty( $redirect_url ) && empty( $errors ) ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Redirect-only form submitted successfully.',
			),
			200
		);
	}

	// Get the user's IP address.
	$ip_address = hubspot_get_user_ip();

	// Only proceed if there are no errors.
	if ( empty( $errors ) ) {
		$url = 'https://api.hsforms.com/submissions/v3/integration/submit/' . HUBSPOT_PORTAL_ID . '/' . $form_id;

		// Build fields array dynamically to only include relevant social media handle
		$fields = array(
			array(
				'name'  => 'email',
				'value' => $email,
			),
			array(
				'name'  => 'firstname',
				'value' => $name,
			),
			array(
				'name'  => 'lastname',
				'value' => $last,
			),
			array(
				'name'  => 'company',
				'value' => $company,
			),
			array(
				'name'  => 'phone',
				'value' => $phone,
			),
			array(
				'name'  => 'website',
				'value' => $website,
			),
			array(
				'name'  => 'primary_social_media_handle',
				'value' => $primary_social_media_handle,
			),
			array(
				'name'  => 'primary_social_platform',
				'value' => $primary_social_platform,
			),
			// array(
			// 'name'  => 'mixpanel_id',
			// 'value' => $mixpanel_id,
			// )
		);

		// Only add the handle field for the selected platform
		if ( ! empty( $primary_social_platform ) ) {
			$platform_mapping = array(
				'LinkedIn'  => array(
					'name'  => 'linkedin',
					'value' => $linkedin,
				),
				'Instagram' => array(
					'name'  => 'instagram_handle',
					'value' => $instagram_handle,
				),
				'YouTube'   => array(
					'name'  => 'youtube_handle',
					'value' => $youtube_handle,
				),
				'Twitter/X' => array(
					'name'  => 'twitter_handle',
					'value' => $twitter_handle,
				),
				'TikTok'    => array(
					'name'  => 'tiktok_handle',
					'value' => $tiktok_handle,
				),
			);

			if ( isset( $platform_mapping[ $primary_social_platform ] ) ) {
				$handle_field = $platform_mapping[ $primary_social_platform ];
				if ( ! empty( $handle_field['value'] ) ) {
					$fields[] = $handle_field;
				}
			}
		}

		// Add remaining fields
		$fields[] = array(
			'name'  => 'employee_range__c',
			'value' => $employee_range__c,
		);
		$fields[] = array(
			'name'  => 'probable_use_case',
			'value' => $probable_use_case,
		);
		$fields[] = array(
			'name'  => 'downloadable_id',
			'value' => $downloadable_id,
		);
		$fields[] = array(
			'name'  => 'page_url',
			'value' => $page_url,
		);
		$fields[] = array(
			'name'  => 'event_trade_show_details__c',
			'value' => $event_trade_show_details__c,
		);
		$fields[] = array(
			'name'  => 'partnerstack_xid',
			'value' => $partnerstack_xid,
		);
		$fields[] = array(
			'name'  => 'partnerstack_partner_key__c',
			'value' => $partnerstack_partner_key__c,
		);
		// Add GDPR opted in field (value: Yes, No, or Not Applicable)
		$fields[] = array(
			'name'  => 'gdpr_opted_in',
			'value' => $gdpr_opted_in,
		);

		$body = array(
			'fields'              => $fields,
			'legalConsentOptions' => array(
				'consent' => array(
					'consentToProcess' => true,
					'text'             => 'I agree to allow Company Name to store and process my personal data.',
					'communications'   => array(
						array(
							'value'              => true,
							'subscriptionTypeId' => 466761704,
							'text'               => 'I agree to receive marketing communications.',
						),
					),
				),
			),
		);
		// Payload.
		$context = array(
			'ipAddress' => $ip_address,
			'pageUri'   => $page_uri,
			'pageName'  => $page_name,
		);

		// Only include hutk if it exists
		if ( ! empty( $hutk ) ) {
			$context['hutk'] = sanitize_text_field( $hutk );
		}

		$payload = array(
			'context'             => $context,
			'fields'              => $body['fields'],
			'legalConsentOptions' => $body['legalConsentOptions'],
		);

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . HUBSPOT_PRIVATE_TOKEN,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers'   => $headers,
				'body'      => wp_json_encode( $payload ),
				'timeout'   => 20,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'wp_remote_post error: ' . $response->get_error_message() );
		} else {
			$status_code   = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
		}

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'errors'  => array( 'submission' => 'There was an error submitting the form. Please try again later.' ),
				),
				500
			);
		} else {
			$status_code   = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			// Decode HubSpot's response.
			$hubspot_response = json_decode( $response_body, true );

			// Check if HubSpot indicates an error.
			if ( isset( $hubspot_response['status'] ) && strtolower( $hubspot_response['status'] ) === 'error' ) {
				$error_message = isset( $hubspot_response['message'] ) ? 'HubSpot Error: ' . $hubspot_response['message'] : 'There was an error submitting the form to HubSpot. Please try again.';

				return new WP_REST_Response(
					array(
						'success' => false,
						'errors'  => array( 'submission' => $error_message ),
					),
					500
				);
			}
			if ( $status_code >= 200 && $status_code < 300 ) {
				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => 'Form submitted successfully.',
					),
					200
				);
			} else {
				// Handle other non-2xx responses.
				$hubspot_error_message = isset( $hubspot_response['message'] ) ? $hubspot_response['message'] : 'There was an error submitting the form to HubSpot. Please try again.';
				// error_log( 'HubSpot API Error (' . $status_code . '): ' . $response_body );

				return new WP_REST_Response(
					array(
						'success' => false,
						'errors'  => array( 'submission' => $hubspot_error_message ),
					),
					500
				);
			}
		}
	}

	// Return field-specific errors if validation fails.
	return new WP_REST_Response(
		array(
			'success' => false,
			'errors'  => $errors,
		),
		400
	);
}

/**
 * Handle REST API request to get user data based on hutk.
 */
function handle_hubspotform_get_user_data( WP_REST_Request $request ) {
	// Retrieve email from request parameters.
	$email = sanitize_email( $request->get_param( 'email' ) );

	if ( empty( $email ) || ! is_email( $email ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'A valid email address is required.',
			),
			400
		);
	}

	// Construct the HubSpot Search API URL.
	$api_url = 'https://api.hubapi.com/crm/v3/objects/contacts/search';

	// Prepare the search payload to filter by email.
	$payload = array(
		'filterGroups' => array(
			array(
				'filters' => array(
					array(
						'propertyName' => 'email',
						'operator'     => 'EQ',
						'value'        => $email,
					),
				),
			),
		),
		'properties'   => array( 'firstname', 'lastname', 'email', 'company', 'phone', 'website', 'primary_social_media_handle', 'instagram_handle', 'youtube_handle', 'tiktok_handle', 'twitter_handle', 'linkedin_handle', 'employee_range__c', 'probable_use_case', 'consent', 'gdpr_opted_in', 'downloadable_id', 'page_url', 'event_trade_show_details__c', 'partnerstack_xid', 'partnerstack_partner_key__c' ),
		'limit'        => 1,
	);

	// Set up headers.
	$headers = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Bearer ' . HUBSPOT_PRIVATE_TOKEN,
	);

	// Make the API request to HubSpot.
	$response = wp_remote_post(
		$api_url,
		array(
			'headers'   => $headers,
			'body'      => wp_json_encode( $payload ),
			'timeout'   => 20,
			'sslverify' => true, // Enable SSL verification for security
		)
	);

	// Check for errors in the API request.
	if ( is_wp_error( $response ) ) {
		// error_log( 'HubSpot API Error: ' . $response->get_error_message() );
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Error fetching data from HubSpot.',
			),
			500
		);
	}

	// Retrieve the response code and body.
	$status_code   = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );

	// Handle non-200 responses.
	if ( $status_code !== 200 ) {
		// error_log( 'HubSpot API Response Code: ' . $status_code );
		// error_log( 'HubSpot API Response Body: ' . $response_body );
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Failed to fetch user data from HubSpot.',
			),
			500
		);
	}

	// Decode the JSON response.
	$hubspot_data = json_decode( $response_body, true );

	// Check for JSON decoding errors.
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		// error_log( 'JSON Decoding Error: ' . json_last_error_msg() );
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Invalid JSON response from HubSpot.',
			),
			500
		);
	}

	// Check if any contacts were found
	if ( empty( $hubspot_data['results'] ) || ! isset( $hubspot_data['results'][0] ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'No contact found with this email address.',
			),
			404
		);
	}

	// Extract the first contact's properties.
	$contact = $hubspot_data['results'][0]['properties'] ?? array();

	// Initialize the fields array.
	$hubspot_form_fields = array(
		'firstname'                   => sanitize_text_field( $contact['firstname'] ?? '' ),
		'lastname'                    => sanitize_text_field( $contact['lastname'] ?? '' ),
		'email'                       => sanitize_email( $contact['email'] ?? '' ),
		'company'                     => sanitize_text_field( $contact['company'] ?? '' ),
		'phone'                       => preg_replace( '/[^0-9]/', '', $contact['phone'] ?? '' ),
		'website'                     => sanitize_url( $contact['website'] ?? '' ),
		'primary_social_media_handle' => sanitize_text_field( $contact['primary_social_media_handle'] ?? '' ),
		'instagram_handle'            => sanitize_text_field( $contact['instagram_handle'] ?? '' ),
		'youtube_handle'              => sanitize_text_field( $contact['youtube_handle'] ?? '' ),
		'tiktok_handle'               => sanitize_text_field( $contact['tiktok_handle'] ?? '' ),
		'twitter_handle'              => sanitize_text_field( $contact['twitter_handle'] ?? '' ),
		'linkedin_handle'             => sanitize_text_field( $contact['linkedin_handle'] ?? '' ),
		'employee_range__c'           => sanitize_text_field( $contact['employee_range__c'] ?? '' ),
		'probable_use_case'           => sanitize_text_field( $contact['probable_use_case'] ?? '' ),
		'consent'                     => sanitize_key( $contact['consent'] ?? '' ),
		'gdpr_opted_in'               => sanitize_text_field( $contact['gdpr_opted_in'] ?? '' ),
		'downloadable_id'             => sanitize_text_field( $contact['downloadable_id'] ?? '' ),
		'page_url'                    => sanitize_text_field( $contact['page_url'] ?? '' ),
		'event_trade_show_details__c' => sanitize_text_field( $contact['event_trade_show_details__c'] ?? '' ),
		'partnerstack_xid'            => sanitize_text_field( $contact['partnerstack_xid'] ?? '' ),
		'partnerstack_partner_key__c' => sanitize_text_field( $contact['partnerstack_partner_key__c'] ?? '' ),
	);

	// Return the contact fields in the REST API response.
	return new WP_REST_Response(
		array(
			'success'     => true,
			'contactData' => $hubspot_form_fields,
		),
		200
	);
}
