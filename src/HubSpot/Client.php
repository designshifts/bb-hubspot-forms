<?php

namespace BBHubspotForms\HubSpot;

use BBHubspotForms\Settings;

final class Client {

	/**
	 * List all forms from HubSpot.
	 *
	 * @return array
	 */
	public static function list_forms(): array {
		$private_token = Settings::get( 'private_token', '' );

		if ( empty( $private_token ) ) {
			// Check if token is stored but can't be decrypted.
			$raw_token = Settings::get_raw( 'private_token' );
			if ( ! empty( $raw_token ) && Settings::is_encrypted_value( $raw_token ) ) {
				return array(
					'success' => false,
					'error'   => 'HubSpot token is encrypted but cannot be decrypted. Check your encryption key.',
				);
			}
			return array(
				'success' => false,
				'error'   => 'HubSpot Private App Token is not configured. Go to Settings → BB HubSpot Forms.',
			);
		}

		$url      = 'https://api.hubapi.com/marketing/v3/forms';
		$response = wp_remote_get(
			$url,
			array(
				'headers'   => array(
					'Authorization' => 'Bearer ' . $private_token,
				),
				'timeout'   => 20,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Connection error: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 && is_array( $data ) ) {
			$forms = array();
			if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
				foreach ( $data['results'] as $form ) {
					$forms[] = array(
						'id'   => isset( $form['id'] ) ? $form['id'] : '',
						'name' => isset( $form['name'] ) ? $form['name'] : '',
					);
				}
			}
			return array(
				'success' => true,
				'data'    => $forms,
			);
		}

		// Build helpful error message.
		$message = 'HubSpot API error';
		if ( is_array( $data ) && isset( $data['message'] ) ) {
			$message = $data['message'];
		} elseif ( 401 === $status_code ) {
			$message = 'Authentication failed. Check your Private App Token.';
		} elseif ( 403 === $status_code ) {
			$message = 'Access denied. Ensure your Private App has the "forms" scope.';
		}

		return array(
			'success' => false,
			'error'   => $message,
		);
	}

	/**
	 * Get form definition from HubSpot.
	 *
	 * @param string $form_id Form GUID.
	 * @return array
	 */
	public static function get_form_definition( string $form_id ): array {
		$form_id       = sanitize_text_field( $form_id );
		$private_token = Settings::get( 'private_token', '' );

		if ( empty( $form_id ) || empty( $private_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot credentials or form ID are missing.',
			);
		}

		$url = 'https://api.hubapi.com/marketing/v3/forms/' . rawurlencode( $form_id );
		$response = wp_remote_get(
			$url,
			array(
				'headers'   => array(
					'Authorization' => 'Bearer ' . $private_token,
				),
				'timeout'   => 20,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 && is_array( $data ) ) {
			return array(
				'success' => true,
				'data'    => $data,
			);
		}

		$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'Unable to fetch HubSpot form definition.';
		return array(
			'success' => false,
			'error'   => $message,
		);
	}
	public static function submit_form( string $hubspot_form_id, array $fields, array $context = array(), array $consent = array() ): array {
		$portal_id     = Settings::get( 'portal_id', '' );
		$private_token = Settings::get( 'private_token', '' );

		if ( empty( $portal_id ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot Portal ID is not configured. Go to Settings → BB HubSpot Forms.',
			);
		}

		if ( empty( $private_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot Private App Token is not configured. Go to Settings → BB HubSpot Forms.',
			);
		}

		$url = 'https://api.hsforms.com/submissions/v3/integration/submit/' . rawurlencode( $portal_id ) . '/' . rawurlencode( $hubspot_form_id );

		$payload = array(
			'fields'  => $fields,
			'context' => $context,
		);

		if ( ! empty( $consent ) ) {
			$payload['legalConsentOptions'] = $consent;
		}

		// Log the submission attempt if debug mode is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[bb-hubspot-forms] Submitting to HubSpot: portal=' . $portal_id . ', form=' . $hubspot_form_id );
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers'   => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $private_token,
				),
				'body'      => wp_json_encode( $payload ),
				'timeout'   => 20,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[bb-hubspot-forms] Connection error: ' . $error_msg );
			}
			return array(
				'success' => false,
				'error'   => 'Connection error: ' . $error_msg,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[bb-hubspot-forms] Submission successful' );
			}
			return array(
				'success' => true,
				'data'    => $data,
			);
		}

		// Build detailed error message.
		$message = 'HubSpot submission failed.';
		if ( is_array( $data ) ) {
			if ( isset( $data['message'] ) ) {
				$message = $data['message'];
			}
			// HubSpot often returns errors in different formats.
			if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
				$error_messages = array();
				foreach ( $data['errors'] as $err ) {
					if ( isset( $err['message'] ) ) {
						$error_messages[] = $err['message'];
					}
				}
				if ( ! empty( $error_messages ) ) {
					$message = implode( ' ', $error_messages );
				}
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[bb-hubspot-forms] Submission failed: status=' . $status_code . ', message=' . $message );
			error_log( '[bb-hubspot-forms] Response body: ' . $body );
		}

		return array(
			'success' => false,
			'error'   => $message,
		);
	}

	public static function test_connection( string $portal_id = '', string $private_token = '' ): array {
		$portal_id     = $portal_id !== '' ? $portal_id : Settings::get( 'portal_id', '' );
		$private_token = $private_token !== '' ? $private_token : Settings::get( 'private_token', '' );

		if ( empty( $portal_id ) || empty( $private_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot credentials are missing.',
			);
		}

		$url = 'https://api.hubapi.com/integrations/v1/me';
		$response = wp_remote_get(
			$url,
			array(
				'headers'   => array(
					'Authorization' => 'Bearer ' . $private_token,
				),
				'timeout'   => 20,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			return array(
				'success' => true,
				'message' => 'Connected to HubSpot successfully.',
				'data'    => $data,
			);
		}

		$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'Unable to connect to HubSpot.';
		return array(
			'success' => false,
			'error'   => $message,
		);
	}
}

