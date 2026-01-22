<?php
/**
 * Plugin Name: BB HubSpot Forms
 * Description: Security-first HubSpot forms for WordPress.
 * Version: 0.1.0
 * Author: Better Builds
 * Text Domain: bb-hubspot-forms
 */

if ( ! defined( 'ABSPATH' ) ) 
	exit;
}

define( 'BBHUBSPOT_FORMS_VERSION', '0.1.0' );
define( 'BBHUBSPOT_FORMS_PLUGIN_FILE', __FILE__ );
define( 'BBHUBSPOT_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBHUBSPOT_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		\BBHubspotForms\Plugin::init();
	}
);

