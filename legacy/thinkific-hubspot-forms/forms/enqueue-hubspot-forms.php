<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enqueue scripts and styles for HubSpot forms.
 */
function front_end_hubspot_scripts() {
	// Only load on singular posts/pages
	if ( ! is_singular() ) {
		return;
	}

	// Register js-cookie dependency
	wp_enqueue_script(
		'js-cookie',
		'https://cdn.jsdelivr.net/npm/js-cookie@3.0.5/dist/js.cookie.min.js',
		array(),
		'3.0.5',
		true
	);

	// Register the main script
	$asset_path = plugin_dir_path( __DIR__ ) . '/assets/js/app.asset.php';

	if ( file_exists( $asset_path ) ) {
		$asset_file   = include $asset_path;
		$dependencies = array_merge( $asset_file['dependencies'], array( 'js-cookie', 'jquery' ) );
		$version      = file_exists( plugin_dir_path( __DIR__ ) . '/assets/js/app.js' )
			? filemtime( plugin_dir_path( __DIR__ ) . '/assets/js/app.js' )
			: time();

		wp_register_script(
			'hubspot-front-end-scripts',
			plugin_dir_url( __DIR__ ) . '/assets/js/app.js',
			$dependencies,
			$version,
			true
		);
	} else {
		error_log( '[HubSpot Form] Missing asset file: app.asset.php' );
		return;
	}

	// CRITICAL: Always provide REST API access when script might load
	wp_localize_script(
		'hubspot-front-end-scripts',
		'hubspotform_rest_obj_think_blocks_hubspot_form',
		array(
			'rest_url' => esc_url_raw( rest_url( 'hubspotform/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		)
	);

	// Use cached check for HubSpot forms to avoid expensive post parsing
	$cache_key        = 'hubspot_form_pages_' . get_the_ID();
	$has_hubspot_form = get_transient( $cache_key );

	if ( false === $has_hubspot_form ) {
		$has_hubspot_form = has_block( 'think-blocks/hubspot-form' );
		set_transient( $cache_key, $has_hubspot_form, HOUR_IN_SECONDS );
	}

	// If no HubSpot form on this page, still provide minimal functionality but skip heavy resources
	if ( ! $has_hubspot_form ) {
		// Provide empty arrays for compatibility
		wp_localize_script(
			'hubspot-front-end-scripts',
			'hubspotform_block_email_domains',
			array( 'blockEmailDomains' => false )
		);

		wp_localize_script(
			'hubspot-front-end-scripts',
			'hubspotBlockedDomains',
			array( 'blockedDomains' => array() )
		);

		// Skip additional form processing but still enqueue basic script for compatibility
		wp_enqueue_script( 'hubspot-front-end-scripts' );
		return;
	}

	// Now check for blockEmailDomains flag (only when form is present)
	$block_email_domains = false;
	global $post;

	if ( $post && ! empty( $post->post_content ) ) {
		$blocks = parse_blocks( $post->post_content );

		// Recursive function to search for HubSpot form block
		function find_hubspot_form_block( $blocks ) {
			foreach ( $blocks as $block ) {
				if ( $block['blockName'] === 'think-blocks/hubspot-form' ) {
					return $block;
				}
				// Check inner blocks if they exist
				if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
					$found = find_hubspot_form_block( $block['innerBlocks'] );
					if ( $found ) {
						return $found;
					}
				}
			}
			return null;
		}

		$hubspot_block = find_hubspot_form_block( $blocks );
		if ( $hubspot_block ) {
			if ( isset( $hubspot_block['attrs']['blockEmailDomains'] ) ) {
				$attr_value = $hubspot_block['attrs']['blockEmailDomains'];
				// Handle both boolean and string values
				if ( is_bool( $attr_value ) ) {
					$block_email_domains = $attr_value;
				} else {
					// Convert string values to boolean
					$block_email_domains = in_array( strtolower( $attr_value ), array( 'true', '1', 'yes', 'on' ) );
				}
			}
		}
	}

	// Localize progressive data
	wp_localize_script(
		'hubspot-front-end-scripts',
		'hubspotProgressiveData',
		array(
			'allowedFields'       => array( 'firstname', 'lastname', 'email', 'company', 'phone', 'website', 'primary_social_media_handle', 'primary_social_platform', 'instagram_handle', 'youtube_handle', 'tiktok_handle', 'twitter_handle', 'linkedin', 'employee_range__c', 'probable_use_case', 'consent' ),
			'progressiveMappings' => array(),
		)
	);

	// Localize email domain blocking flag
	wp_localize_script(
		'hubspot-front-end-scripts',
		'hubspotform_block_email_domains',
		array( 'blockEmailDomains' => $block_email_domains ? true : false )
	);

	// Only load blocked domains when email validation is enabled
	if ( $block_email_domains ) {
		$blocked_domains = hubspot_get_blocked_domains();
		$localized_data  = array( 'blockedDomains' => $blocked_domains );
		wp_localize_script(
			'hubspot-front-end-scripts',
			'hubspotBlockedDomains',
			$localized_data
		);
	} else {
		// Provide empty array when not needed
		wp_localize_script(
			'hubspot-front-end-scripts',
			'hubspotBlockedDomains',
			array( 'blockedDomains' => array() )
		);
	}

	// Localize error messages
	wp_localize_script(
		'hubspot-front-end-scripts',
		'thinkBlocksL10n',
		array(
			'firstNameRequired'                => __( 'Please enter your first name', 'think-blocks' ),
			'lastNameRequired'                 => __( 'Please enter your last name', 'think-blocks' ),
			'companyRequired'                  => __( 'Company name is required', 'think-blocks' ),
			'phoneNumberRequired'              => __( 'Please enter your phone number', 'think-blocks' ),
			'websiteRequired'                  => __( 'Website is required.', 'think-blocks' ),
			'primarySocialMediaHandleRequired' => __( 'Primary Social Media Handle is required.', 'think-blocks' ),
			'employee_range__cRequired'        => __( 'Please select your company size', 'think-blocks' ),
			'probable_use_caseRequired'        => __( 'Please select what we can help with', 'think-blocks' ),
			'consentRequired'                  => __( 'Consent is required', 'think-blocks' ),
			'validPhoneError'                  => __( 'Please enter a valid phone number', 'think-blocks' ),
			'validUrlError'                    => __( 'Please enter a valid URL.', 'think-blocks' ),
			'emailError'                       => __( 'Please enter your email', 'think-blocks' ),
			'emailbusinessError'               => __( 'Please enter a business email. Don\'t have one? Check out our other plans <a target="_blank" href="https://www.thinkific.com/pricing/">here</a>.', 'think-blocks' ),
			'validEmailError'                  => __( 'Please enter a valid email address.', 'think-blocks' ),
			'requiredField'                    => __( 'This field is required.', 'think-blocks' ),
			'generalError'                     => __( 'An unexpected error occurred. Please try again later.', 'think-blocks' ),
			'submittingText'                   => __( 'Submitting...', 'think-blocks' ),
			'stillWorkingText'                 => __( 'Still working...', 'think-blocks' ),
			'hangTightText'                    => __( 'Hang tight—still processing...', 'think-blocks' ),
			// Social media validation messages
			'socialPlatformRequired'           => __( 'Please select a social media platform.', 'think-blocks' ),
			'socialHandleRequired'             => __( 'This field is required.', 'think-blocks' ),
			'instagramHandleError'             => __( 'Instagram handle must be 1-30 characters and can only contain letters, numbers, periods, and underscores.', 'think-blocks' ),
			'youtubeHandleError'               => __( 'YouTube handle must be 3-30 characters and can only contain letters, numbers, hyphens, and underscores.', 'think-blocks' ),
			'tiktokHandleError'                => __( 'TikTok handle must be 2-24 characters and can only contain letters, numbers, periods, and underscores.', 'think-blocks' ),
			'twitterHandleError'               => __( 'Twitter/X handle must be 1-15 characters and can only contain letters, numbers, and underscores.', 'think-blocks' ),
			'linkedinHandleError'              => __( 'LinkedIn handle must be 3-100 characters and can only contain letters, numbers, and hyphens.', 'think-blocks' ),
			'invalidHandleFormat'              => __( 'Invalid handle format. Please check your social media handle.', 'think-blocks' ),
		)
	);

	// Lazy-load reCAPTCHA only on form pages
	wp_add_inline_script(
		'hubspot-front-end-scripts',
		"(function() {
			window.addEventListener('DOMContentLoaded', function () {
				if (typeof grecaptcha === 'undefined' && document.querySelector('.hubspot-form-container')) {
					if ('requestIdleCallback' in window) {
						requestIdleCallback(loadRecaptcha);
					} else {
						setTimeout(loadRecaptcha, 200);
					}
				}
			});
			function loadRecaptcha() {
				var s = document.createElement('script');
				s.src = 'https://www.google.com/recaptcha/api.js?render=6Lc38q8qAAAAAOCUm8Ar7Mc4nSNYK08yIyrj4Ez-';
				s.async = true;
				s.defer = true;
				document.body.appendChild(s);
			}
		})();",
		'after'
	);

	// Enqueue socials script if social media selector is enabled
	global $post;
	if ( $post && ! empty( $post->post_content ) ) {
		$blocks        = parse_blocks( $post->post_content );
		$hubspot_block = find_hubspot_form_block( $blocks );
		if ( $hubspot_block && isset( $hubspot_block['attrs']['enableSocialMediaSelector'] ) && $hubspot_block['attrs']['enableSocialMediaSelector'] ) {
			wp_enqueue_script( 'hubspot-socials-script' );
		}
	}

	// Finally enqueue the script
	wp_enqueue_script( 'hubspot-front-end-scripts' );
}
add_action( 'wp_enqueue_scripts', 'front_end_hubspot_scripts' );
