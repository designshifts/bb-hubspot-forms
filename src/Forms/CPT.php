<?php
/**
 * HubSpot Form CPT registration.
 *
 * @package BBHubspotForms
 */

namespace BBHubspotForms\Forms;

/**
 * Registers the hubspot_form CPT and meta keys.
 */
final class CPT {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register the hubspot_form CPT.
	 *
	 * @return void
	 */
	public static function register_cpt(): void {
		register_post_type(
			'hubspot_form',
			array(
				'labels'          => array(
					'name'               => __( 'HubSpot Forms', 'bb-hubspot-forms' ),
					'singular_name'      => __( 'HubSpot Form', 'bb-hubspot-forms' ),
					'add_new'            => __( 'Add New', 'bb-hubspot-forms' ),
					'add_new_item'       => __( 'Add New HubSpot Form', 'bb-hubspot-forms' ),
					'edit_item'          => __( 'Edit HubSpot Form', 'bb-hubspot-forms' ),
					'new_item'           => __( 'New HubSpot Form', 'bb-hubspot-forms' ),
					'view_item'          => __( 'View HubSpot Form', 'bb-hubspot-forms' ),
					'search_items'       => __( 'Search HubSpot Forms', 'bb-hubspot-forms' ),
					'not_found'          => __( 'No forms found.', 'bb-hubspot-forms' ),
					'not_found_in_trash' => __( 'No forms found in Trash.', 'bb-hubspot-forms' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => true,
				'capability_type' => 'post',
				'has_archive'     => false,
				'supports'        => array( 'title', 'editor', 'custom-fields' ),
				'menu_icon'       => 'dashicons-feedback',
				'template'        => array(
					array( 'bb-hubspot-forms/form-config' ),
				),
				'template_lock'   => 'all',
			)
		);
	}

	/**
	 * Register post meta for the hubspot_form CPT.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		// Form GUID from HubSpot.
		register_post_meta(
			'hubspot_form',
			'_bbhs_form_guid',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Schema synced from HubSpot.
		register_post_meta(
			'hubspot_form',
			'_bbhs_schema',
			array(
				'type'          => 'object',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'properties'           => array(
							'portalId'  => array( 'type' => 'string' ),
							'formGuid'  => array( 'type' => 'string' ),
							'name'      => array( 'type' => 'string' ),
							'fetchedAt' => array( 'type' => 'integer' ),
							'fields'    => array(
								'type'  => 'array',
								'items' => array(
									'type'                 => 'object',
									'additionalProperties' => true,
									'properties'           => array(
										'name'     => array( 'type' => 'string' ),
										'label'    => array( 'type' => 'string' ),
										'type'     => array( 'type' => 'string' ),
										'required' => array( 'type' => 'boolean' ),
										'options'  => array(
											'type'  => 'array',
											'items' => array( 'type' => 'string' ),
										),
									),
								),
							),
						),
					),
				),
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'default'       => array(),
			)
		);

		// User overrides (order, hidden, labels).
		register_post_meta(
			'hubspot_form',
			'_bbhs_overrides',
			array(
				'type'          => 'object',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'properties'           => array(
							'order'  => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'hidden' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'labels' => array(
								'type'                 => 'object',
								'additionalProperties' => array( 'type' => 'string' ),
							),
						),
					),
				),
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'default'       => array(
					'order'  => array(),
					'hidden' => array(),
					'labels' => array(),
				),
			)
		);

		// Token TTL for signed tokens.
		register_post_meta(
			'hubspot_form',
			'_bbhs_token_ttl',
			array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'default'       => 600,
			)
		);
	}
}
