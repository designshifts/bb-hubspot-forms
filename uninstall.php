<?php
/**
 * Uninstall cleanup for BB HubSpot Forms.
 *
 * @package BB_HubSpot_Forms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bb_hubspot_forms_settings' );

global $wpdb;
$like = $wpdb->esc_like( '_transient_bb-hubspot-forms_' ) . '%';
$timeout_like = $wpdb->esc_like( '_transient_timeout_bb-hubspot-forms_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) );
