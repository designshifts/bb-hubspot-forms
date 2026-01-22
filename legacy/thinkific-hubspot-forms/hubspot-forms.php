<?php

/**
 * Plugin Name:       Thinkific | Hubspot Forms
 * Plugin URI:        https://thinkific.com
 * Description:       Customizable Hubspot Forms for WordPress
 * Version:           1.0.1
 * Author:            Thinkific
 * Author URI:        https://thinkific.com
 * Text Domain:       hubspot-forms
 * Domain Path:       /languages
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// Current plugin version.
define( 'HUBSPOT_FORMS_VERSION', '1.0.1' );

// Enqueue scripts and styles.
require plugin_dir_path( __FILE__ ) . '/forms/enqueue-hubspot-forms.php';

// Include  Hubspot Forms Constants.
require plugin_dir_path( __FILE__ ) . '/forms/constant/blocked-domains.php';
require plugin_dir_path( __FILE__ ) . '/forms/constant/form-validations.php';
require plugin_dir_path( __FILE__ ) . '/forms/constant/datalayer.php';
require plugin_dir_path( __FILE__ ) . '/forms/constant/urlquery.php';

// Include the Hubspot Form Server Side Functions.
require plugin_dir_path( __FILE__ ) . '/forms/hubspot-form.php';

/**
 * Add the Hubspot Form Blocks.
 */
function hubspot_forms_register_blocks() {
	register_block_type(
		__DIR__ . '/blocks/hubspot-form',
		array(
			'render_callback' => 'think_hubspot_form_block_render',
		)
	);
}
add_action( 'init', 'hubspot_forms_register_blocks' );

/**********  LOCAL TEST AUTH */
define( 'HUBSPOT_TEST_PRIVATE_TOKEN', $_ENV['HUBSPOT_TEST_PRIVATE_TOKEN'] );
define( 'HUBSPOT_TEST_PORTAL_ID', $_ENV['HUBSPOT_TEST_PORTAL_ID'] );
/**********  END LOCAL TEST AUTH */

// This is the Thinkific Private Token and ID for the HubSpot forms.
define( 'HUBSPOT_PRIVATE_TOKEN', $_ENV['HUBSPOT_PRIVATE_TOKEN'] );
define( 'HUBSPOT_PORTAL_ID', $_ENV['HUBSPOT_PORTAL_ID'] );

// reCAPTCHA configuration
define( 'RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY'] ?? '' );

/**
 * Register REST API routes with nonce verification.
 */
add_action( 'rest_api_init', 'hubspotform_register_rest_routes' );

function hubspotform_register_rest_routes() {
	// MASTER FORM ROUTES.
	register_rest_route(
		'hubspotform/v1',
		'/hubspot-form/get-user-data',
		array(
			'methods'             => 'GET',
			'callback'            => 'handle_hubspotform_get_user_data',
			'permission_callback' => 'hubspotform_permissions_check',
		)
	);

	register_rest_route(
		'hubspotform/v1',
		'/hubspot-form/submit',
		array(
			'methods'             => 'POST',
			'callback'            => 'hubspot_rest_handle_submission',
			'permission_callback' => 'hubspotform_permissions_check',
		)
	);
}

// Removed problematic rewrite rules function that called non-existent hubspot_forms_add_rewrite_rules()


function think_hubspot_form_block_render( $attributes, $content, $block ) {
	$class_name = 'wp-block-think-blocks-hubspot-form';

	if ( isset( $block->attributes['className'] ) ) {
		$class_name .= ' ' . esc_attr( $block->attributes['className'] );
	}

	ob_start();
	echo '<div class="' . esc_attr( $class_name ) . '">';
	include plugin_dir_path( __FILE__ ) . 'blocks/hubspot-form/render.php';
	echo '</div>';
	return ob_get_clean();
}


/**
 * GeoIP country code from Cloudflare
 */
function add_geoip_meta_tag() {
	$geoip_country_code = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : 'not_identified_cloudflare_header_not_responding';
	echo '<meta name="geoip-country-code" content="' . esc_attr( $geoip_country_code ) . '">' . "\n";
}
add_action( 'wp_head', 'add_geoip_meta_tag' );


/**
 * Plugin activation - flush rewrite rules to ensure proper routing
 */
function hubspot_forms_activate() {
	flush_rewrite_rules();
}

/**
 * Plugin deactivation - clean up transients
 */
function hubspot_forms_deactivate() {
	// Clean up any cached transients created by the plugin
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hubspot_form_pages_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hubspot_form_pages_%'" );

	// Flush rewrite rules on deactivation
	flush_rewrite_rules();
}

// Register activation and deactivation hooks
register_activation_hook( __FILE__, 'hubspot_forms_activate' );
register_deactivation_hook( __FILE__, 'hubspot_forms_deactivate' );
