<?php

namespace BBHubspotForms\HubSpot;

use BBHubspotForms\Settings;

final class Client {
	public static function submit_form( string $hubspot_form_id, array $fields, array $context = array(), array $consent = array() ): array {
		$portal_id     = Settings::get( 'portal_id', '' );
		$private_token = Settings::get( 'private_token', '' );

		if ( empty( $portal_id ) || empty( $private_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot credentials are missing.',
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
				'data'    => $data,
			);
		}

		$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'HubSpot submission failed.';

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

