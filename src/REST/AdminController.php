<?php

namespace BBHubspotForms\REST;

use WP_REST_Response;
use BBHubspotForms\HubSpot\Client;

final class AdminController {
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'hubspotform/v1',
			'/test-connection',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_test_connection' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);
	}

	public static function handle_test_connection( \WP_REST_Request $request ): WP_REST_Response {
		$params        = $request->get_json_params();
		$portal_id     = isset( $params['portal_id'] ) ? sanitize_text_field( $params['portal_id'] ) : '';
		$private_token = isset( $params['private_token'] ) ? sanitize_text_field( $params['private_token'] ) : '';
		$result        = Client::test_connection( $portal_id, $private_token );
		if ( $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => $result['message'],
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => $result['error'],
			),
			400
		);
	}

	public static function can_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}
}
