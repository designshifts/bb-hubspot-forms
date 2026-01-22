<?php
/**
 * Block editor assets for HubSpot Forms.
 *
 * @package BBHubspotForms
 */

namespace BBHubspotForms\Admin;

/**
 * Enqueues editor block and assets for hubspot_form CPT.
 */
final class EditorAssets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Register the form config block.
	 *
	 * @return void
	 */
	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$asset_path = BBHUBSPOT_FORMS_PLUGIN_DIR . 'assets/js/editor-block.js';
		wp_register_script(
			'bb-hubspot-forms-editor-block',
			BBHUBSPOT_FORMS_PLUGIN_URL . 'assets/js/editor-block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-i18n',
				'wp-api-fetch',
				'wp-editor',
			),
			file_exists( $asset_path ) ? filemtime( $asset_path ) : BBHUBSPOT_FORMS_VERSION,
			true
		);

		register_block_type(
			'bb-hubspot-forms/form-config',
			array(
				'editor_script' => 'bb-hubspot-forms-editor-block',
			)
		);
	}

	/**
	 * Enqueue editor assets.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		global $post_type, $typenow;

		// Try multiple methods to get post type.
		$current_post_type = '';

		if ( ! empty( $typenow ) ) {
			$current_post_type = $typenow;
		} elseif ( ! empty( $post_type ) ) {
			$current_post_type = $post_type;
		} elseif ( ! empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_post_type = get_post_type( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_post_type = sanitize_key( $_GET['post_type'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( 'hubspot_form' !== $current_post_type ) {
			return;
		}

		// Add inline CSS to hide the sidebar panels (config is now in main area).
		wp_add_inline_style(
			'wp-edit-post',
			'.edit-post-sidebar .components-panel__body[class*="bb-hubspot"] { display: none; }'
		);
	}
}
