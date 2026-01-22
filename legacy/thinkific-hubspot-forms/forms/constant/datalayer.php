<?php
/**
 * Adds tracking data to the GTM data layer.
 */
function hubspot_add_userprofile_to_datalayer() {
	global $post;
	// Common data layer fields, active site-wide.
	$page_title = get_the_title();
	$user_ip    = hubspot_get_user_ip();

	// This is using cloudflare IP.
	$country_code = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : 'not_identified_cloudflare_header_not_responding';

	// Check if the current page contains any 'hubspot-form-*' block dynamically.
	$has_hubspot_form = false;
	if ( isset( $post ) && ! empty( $post->post_content ) ) {
		if ( has_blocks( $post->post_content ) ) {
			$blocks = parse_blocks( $post->post_content );
			foreach ( $blocks as $block ) {
				if ( isset( $block['blockName'] ) && strpos( $block['blockName'], 'think-blocks/hubspot-form-' ) !== false ) {
					$has_hubspot_form = true;
					break;
				}
			}
		}
	}

	// Retrieve user profile data only if a `hubspot-form-*` block is present.
	$user_profile = array();
	if ( $has_hubspot_form ) {
		if ( isset( $_COOKIE['UserProfile'] ) ) {
			$user_profile = json_decode( stripslashes( $_COOKIE['UserProfile'] ), true );
		} else {
			$user_profile = null;
		}
	}
	?>
	<script>
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			'geo_ip': '<?php echo esc_js( $country_code ); ?>',
			'page_title': '<?php echo esc_js( $page_title ); ?>',
			'user_ip': '<?php echo esc_js( $user_ip ); ?>',
			<?php if ( $has_hubspot_form ) : ?>
			'user_profile': <?php echo wp_json_encode( $user_profile ); ?>,
			<?php endif; ?>
		});
	</script>
	<?php
}

/**
 * Helper function to check if a 'hubspot-form-*' block exists on the page.
 */
function has_hubspot_form_block( $post ) {
	if ( ! $post ) {
		return false;
	}

	// Parse blocks from the post content.
	$blocks = parse_blocks( $post->post_content );

	foreach ( $blocks as $block ) {
		if ( is_string( $block['blockName'] ) && strpos( $block['blockName'], 'think-blocks/hubspot-form-' ) !== false ) {
			return true;
		}
	}

	return false;
}

add_action( 'wp_head', 'hubspot_add_userprofile_to_datalayer' );
?>
