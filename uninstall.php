<?php
/**
 * Uninstall cleanup for BB HubSpot Forms.
 *
 * @package BB_HubSpot_Forms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin settings.
delete_option( 'bb_hubspot_forms_settings' );

// Delete all hubspot_form CPT posts and their post meta.
$bbhubspot_forms_posts = get_posts(
	array(
		'post_type'      => 'hubspot_form',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $bbhubspot_forms_posts as $bbhubspot_forms_post_id ) {
	wp_delete_post( $bbhubspot_forms_post_id, true );
}

// Delete rate-limiting transients.
global $wpdb;
$bbhubspot_forms_like         = $wpdb->esc_like( '_transient_bb-hubspot-forms_' ) . '%';
$bbhubspot_forms_timeout_like = $wpdb->esc_like( '_transient_timeout_bb-hubspot-forms_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $bbhubspot_forms_like, $bbhubspot_forms_timeout_like ) );
