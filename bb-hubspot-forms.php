<?php
/**
 * Plugin Name: BB HubSpot Forms
 * Plugin URI: https://betterbuilds.app
 * Description: Security-first HubSpot forms for WordPress.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Chris Anderson
 * Author URI: https://www.linkedin.com/in/chrisandersondesigns/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bb-hubspot-forms
 * Domain Path: /languages
 *
 * @package BB_HubSpot_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BBHUBSPOT_FORMS_VERSION', '1.0.0' );
define( 'BBHUBSPOT_FORMS_PLUGIN_FILE', __FILE__ );
define( 'BBHUBSPOT_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBHUBSPOT_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Plugin.php';

register_activation_hook( __FILE__, 'bb_hubspot_forms_activate' );
register_deactivation_hook( __FILE__, 'bb_hubspot_forms_deactivate' );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'bb-hubspot-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		\BBHubspotForms\Plugin::init();
	}
);

/**
 * Activation hook.
 *
 * @return void
 */
function bb_hubspot_forms_activate(): void {
	\BBHubspotForms\Plugin::init();
	\BBHubspotForms\Forms\CPT::register_cpt();
	flush_rewrite_rules();
}

/**
 * Deactivation hook.
 *
 * @return void
 */
function bb_hubspot_forms_deactivate(): void {
	bb_hubspot_forms_clear_transients();
	flush_rewrite_rules();
}

/**
 * Clear plugin transients.
 *
 * @return void
 */
function bb_hubspot_forms_clear_transients(): void {
	global $wpdb;
	$like = $wpdb->esc_like( '_transient_bb-hubspot-forms_' ) . '%';
	$timeout_like = $wpdb->esc_like( '_transient_timeout_bb-hubspot-forms_' ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) );
}
