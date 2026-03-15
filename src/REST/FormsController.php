<?php
/**
 * REST controller for HubSpot forms.
 *
 * @package BBHubspotForms
 */

namespace BBHubspotForms\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use BBHubspotForms\HubSpot\Client;
use BBHubspotForms\HubSpot\SchemaMapper;

/**
 * REST endpoints for listing and fetching HubSpot forms.
 */
final class FormsController {

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
			'bb-hubspot/v1',
			'/forms',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_forms' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			'bb-hubspot/v1',
			'/forms/schema',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'fetch_schema' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'args'                => array(
					'formGuid' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check if user can edit posts.
	 *
	 * @return bool
	 */
	public static function can_edit_posts(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * List forms from HubSpot.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function list_forms( WP_REST_Request $request ): WP_REST_Response {
		$result = Client::list_forms();

		if ( ! $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result['error'],
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'forms'   => $result['data'],
			),
			200
		);
	}

	/**
	 * Fetch schema for a specific form.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function fetch_schema( WP_REST_Request $request ): WP_REST_Response {
		$form_guid = $request->get_param( 'formGuid' );

		if ( empty( $form_guid ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'formGuid is required.',
				),
				400
			);
		}

		$result = Client::get_form_definition( $form_guid );

		if ( ! $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result['error'],
				),
				400
			);
		}

		$schema = SchemaMapper::map( $result['data'] );

		$payload = array(
			'success' => true,
			'schema'  => $schema,
		);
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
			$payload['_debug'] = array(
				'rawKeys'    => array_keys( $result['data'] ),
				'fieldCount' => count( $schema['fields'] ),
			);
		}

		return new WP_REST_Response( $payload, 200 );
	}
}
