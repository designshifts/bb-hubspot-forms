<?php $unique_id = uniqid( 'hubspot_form_' ); ?>

<div class="hubspot-form-container" id="<?php echo esc_attr( $unique_id ); ?>" data-unique-id="<?php echo esc_attr( $unique_id ); ?>">
	<?php

	// Display submission errors.
	if ( isset( $_GET['submission'] ) && $_GET['submission'] === 'failed' ) {
		echo '<p class="form-error" style="color:red;">There was an error submitting the form. Please try again.</p>';
	}

	// Display any server-side validation errors.
	if ( ! empty( $errors ) ) {
		foreach ( $errors as $error ) {
			echo '<p class="form-error" style="color:red;">' . esc_html( $error ) . '</p>';
		}
	}

	// If no success message, show the form.
	if ( empty( $output ) ) :
		// Form attributes.
		$form_id              = ! empty( $attributes['formId'] ) ? esc_attr( $attributes['formId'] ) : '';
		$progressive_mappings = ! empty( $attributes['progressiveMappings'] ) ? $attributes['progressiveMappings'] : array();

		// Transform the stored mapping into a correct format.
		$processed_mappings = array();
		foreach ( $progressive_mappings as $map ) {
			if ( ! empty( $map['from'] ) && ! empty( $map['to'] ) ) {
				$processed_mappings[ $map['from'] ] = $map['to'];
			}
		}

		// Content, Hidden fields and Thank You message.
		$hidden_fields            = ! empty( $attributes['hiddenFields'] ) ? $attributes['hiddenFields'] : array();
		$content                  = ! empty( $attributes['content'] ) ? $attributes['content'] : '';
		$submit_button_html       = isset( $attributes['submitButtonHtml'] ) ? $attributes['submitButtonHtml'] : "<div class='wp-block-button has-arrow arrow-right'><button type='submit' name='form_submit' class='wp-block-button__link wp-element-button g-recaptcha'>Submit</button></div>";
		$thank_you_message        = ! empty( $attributes['thankYouMessage'] ) ? $attributes['thankYouMessage'] : 'Thank you for your submission.';
		$enable_marketing_consent = isset( $attributes['enableMarketingConsent'] ) ? $attributes['enableMarketingConsent'] : true; // Default to true
		?>

		<form
			id="<?php echo esc_attr( $form_id ); ?>"
			data-form-id="<?php echo esc_attr( $form_id ); ?>"
			data-redirect-url="<?php echo esc_url( $attributes['redirectUrl'] ?? '' ); ?>"
			data-append-email="<?php echo isset( $attributes['appendEmailToRedirect'] ) ? ( $attributes['appendEmailToRedirect'] ? 'true' : 'false' ) : 'false'; ?>"
			data-enable-solo-detect="<?php echo isset( $attributes['enableSoloDetect'] ) ? ( $attributes['enableSoloDetect'] ? 'true' : 'false' ) : 'false'; ?>"
			data-enable-marketing-consent="<?php echo $enable_marketing_consent ? 'true' : 'false'; ?>"
			data-hide-known-fields="<?php echo isset( $attributes['hideKnownFields'] ) ? ( $attributes['hideKnownFields'] ? 'true' : 'false' ) : 'false'; ?>"
			class="hubspot-form__form" novalidate>

			<div id="form-fields">

				<?php wp_nonce_field( 'hubspot_form_action', 'hubspot_form_nonce' ); ?>

				<!-- Form content -->
				<?php echo $content; ?>

				<!-- Social Media Selector -->
				<?php if ( isset( $attributes['enableSocialMediaSelector'] ) && $attributes['enableSocialMediaSelector'] ) : ?>
					<?php
					$enabled_platforms = $attributes['enabledSocialPlatforms'] ?? array( 'instagram', 'youtube', 'tiktok', 'twitter', 'linkedin', 'other' );
					$platform_labels   = array(
						'instagram' => 'Instagram',
						'youtube'   => 'YouTube',
						'tiktok'    => 'TikTok',
						'twitter'   => 'Twitter/X',
						'linkedin'  => 'LinkedIn',
						'other'     => 'Other',
					);
					$handle_fields     = array(
						'linkedin'  => 'linkedin',
						'instagram' => 'instagram_handle',
						'youtube'   => 'youtube_handle',
						'tiktok'    => 'tiktok_handle',
						'twitter'   => 'twitter_handle',
					);
					?>
					<div class="social-media-container"
						data-selector-label="<?php echo esc_attr( $attributes['socialMediaSelectorLabel'] ?? 'Primary social media platform' ); ?>"
						data-disclaimer="<?php echo esc_attr( $attributes['socialMediaDisclaimer'] ?? "We'll only use this to connect with you about the event." ); ?>"
						data-enabled-platforms="<?php echo esc_attr( json_encode( $enabled_platforms ) ); ?>">
						<div class="social-platform-selector">
							<label class="social-platform-label"><?php echo esc_html( $attributes['socialMediaSelectorLabel'] ?? 'Primary social media platform' ); ?></label>
							<div class="social-platform-options">
								<?php foreach ( $enabled_platforms as $platform ) : ?>
									<?php if ( isset( $platform_labels[ $platform ] ) ) : ?>
										<label class="social-platform-option">
											<input type="radio" name="social_platform" value="<?php echo esc_attr( $platform ); ?>" class="social-platform-radio">
											<span class="social-platform-icon-wrapper">
												<span class="social-platform-icon">
													<?php if ( $platform === 'instagram' ) : ?>
														<svg aria-hidden="true" focusable="false" data-prefix="fab" data-icon="instagram" class="svg-inline--fa fa-instagram" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" title="Instagram">
															<path fill="currentColor" d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.6 102.7-9 132.1z"></path>
														</svg>
													<?php elseif ( $platform === 'youtube' ) : ?>
														<svg aria-hidden="true" focusable="false" data-prefix="fab" data-icon="youtube" class="svg-inline--fa fa-youtube" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" title="YouTube">
															<path fill="currentColor" d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"></path>
														</svg>
													<?php elseif ( $platform === 'tiktok' ) : ?>
														<svg aria-hidden="true" focusable="false" data-prefix="fab" data-icon="tiktok" class="svg-inline--fa fa-tiktok" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" title="TikTok">
															<path fill="currentColor" d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.6,162.6,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z"></path>
														</svg>
													<?php elseif ( $platform === 'twitter' ) : ?>
														<svg aria-hidden="true" focusable="false" data-prefix="fab" data-icon="twitter" class="svg-inline--fa fa-twitter" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" title="Twitter/X">
															<path fill="currentColor" d="M459.37 151.716c.325 4.548.325 9.097.325 13.645 0 138.72-105.583 298.558-298.558 298.558-59.452 0-114.68-17.219-161.137-47.106 8.447.974 16.568 1.299 25.34 1.299 49.055 0 94.213-16.568 130.274-44.832-46.132-.975-84.792-31.188-98.112-72.772 6.498.974 12.995 1.624 19.818 1.624 9.421 0 18.843-1.3 27.614-3.573-48.081-9.747-84.143-51.98-84.143-102.985v-1.299c13.969 7.797 30.214 12.67 47.431 13.319-28.264-18.843-46.781-51.005-46.781-87.391 0-19.492 5.197-37.36 14.294-52.954 51.655 63.675 129.3 105.258 216.365 109.807-1.624-7.797-2.599-15.918-2.599-24.04 0-57.828 46.782-104.934 104.934-104.934 30.213 0 57.502 12.67 76.67 33.137 23.715-4.548 46.456-13.32 66.599-25.34-7.798 24.366-24.366 44.833-46.132 57.827 21.117-2.273 41.584-8.122 60.426-16.243-14.292 20.791-32.161 39.308-52.628 54.253z"></path>
														</svg>
													<?php elseif ( $platform === 'linkedin' ) : ?>
														<svg aria-hidden="true" focusable="false" data-prefix="fab" data-icon="linkedin" class="svg-inline--fa fa-linkedin" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" title="LinkedIn">
															<path fill="currentColor" d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"></path>
														</svg>
													<?php endif; ?>
												</span>
												<span class="social-platform-name"><?php echo esc_html( $platform_labels[ $platform ] ); ?></span>
											</span>
										</label>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="social-handle-fields">
							<?php foreach ( $enabled_platforms as $platform ) : ?>
								<?php if ( isset( $handle_fields[ $platform ] ) ) : ?>
									<div class="social-handle-field" data-platform="<?php echo esc_attr( $platform ); ?>" style="display: none;">
										<label for="<?php echo esc_attr( $handle_fields[ $platform ] ); ?>" class="social-handle-label"><?php echo esc_html( $platform_labels[ $platform ] ); ?> handle</label>
										<div class="social-handle-input-wrapper">
											<span class="social-handle-prefix">@</span>
											<input type="text" id="<?php echo esc_attr( $handle_fields[ $platform ] ); ?>" name="<?php echo esc_attr( $handle_fields[ $platform ] ); ?>" class="social-handle-input" placeholder="username" required aria-required="true">
										</div>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
						<p class="social-media-disclaimer"><?php echo esc_html( $attributes['socialMediaDisclaimer'] ?? "We'll only use this to connect with you about the event." ); ?></p>

						<!-- Hidden field to capture the selected platform for HubSpot -->
						<input type="hidden" id="primary_social_platform" name="primary_social_platform" value="">
					</div>
				<?php endif; ?>

				<!-- Page URL -->
				<?php
				// Get the current URL path, handling various server configurations
				$page_url = home_url();

				if ( isset( $_SERVER['REQUEST_URI'] ) ) {
					$request_uri = $_SERVER['REQUEST_URI'];

					// Remove query string and hash if present
					$path = $request_uri;

					// Remove query string (everything after ?)
					$question_pos = strpos( $path, '?' );
					if ( $question_pos !== false ) {
						$path = substr( $path, 0, $question_pos );
					}

					// Remove hash (everything after #)
					$hash_pos = strpos( $path, '#' );
					if ( $hash_pos !== false ) {
						$path = substr( $path, 0, $hash_pos );
					}

					// Build the clean URL
					$page_url = home_url( $path );
				}
				?>
				<input type="hidden" name="page_url" value="<?php echo esc_url( $page_url ); ?>" />

				<!-- Blocked Email Domains -->
				<?php if ( ! empty( $attributes['blockEmailDomains'] ) ) : ?>
					<input type="hidden" name="blockEmailDomains" value="true" />
				<?php endif; ?>

				<!-- Hidden fields -->
				<?php
				foreach ( $hidden_fields as $field ) :
					if ( ! empty( $field['id'] ) && ! empty( $field['value'] ) ) :
						?>
						<input type="hidden" name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
						<?php
					endif;
				endforeach;
				?>

				<!-- Mixpanel -->
				<!-- 
				<?php
				$cookie_name = 'mp_e9f85a260e22673665c335ea07907e45_mixpanel';
				$mixpanel_id = '';

				if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
					$raw  = $_COOKIE[ $cookie_name ];
					$data = json_decode( stripslashes( $raw ), true );
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						$data = json_decode( urldecode( $raw ), true );
					}
					if ( is_array( $data ) ) {
						$mixpanel_id = $data['distinct_id'] ?? ( $data['$device_id'] ?? '' );
					}
				}
				?>
				<div class="mixpanel">
					<label for="mixpanel_id">
						<input type="hidden" id="mixpanel_id" name="mixpanel_id" value="<?php echo esc_attr( $mixpanel_id ); ?>">
					</label>
				</div> -->


				<!-- EU Consent Checkbox -->
				<label for="consent" style="display: none;">
					<input type="checkbox" id="consent" name="consent" required aria-required="true" value="true">
					<span>
						<?php
						echo wp_kses(
							__( 'By submitting this form I agree to Thinkific\'s <a href="https://www.thinkific.com/terms-of-service/" target="_blank">Terms of Service</a> and <a href="https://www.thinkific.com/privacy-policy/" target="_blank">Privacy Policy</a>.', 'think-blocks' ),
							array(
								'a' => array(
									'href'   => array(),
									'target' => array(),
								),
							)
						);
						?>
						<span class="required-indicator"> *Required</span>
					</span>
				</label>

				<!-- Marketing Consent Checkbox -->
				<?php if ( $enable_marketing_consent ) : ?>
					<label for="gdpr_opted_in" style="display: none;">
						<input type="checkbox" id="gdpr_opted_in" name="gdpr_opted_in" value="yes">
						<span>
							<?php
							echo wp_kses(
								__( 'I want to receive emails about product updates, exclusive offers, and news.<br>You can unsubscribe at any time. Read our <a href="/privacy-policy" target="_blank">Privacy Policy</a> for details.', 'think-blocks' ),
								array(
									'a'  => array(
										'href'   => array(),
										'target' => array(),
									),
									'br' => array(),
								)
							);
							?>
							<span class="optional-indicator"> (Optional)</span>
						</span>
					</label>
				<?php endif; ?>

				<!-- Submit Button -->
				<?php echo wp_kses_post( $submit_button_html ); ?>

			</div>
		</form>

		<!-- Thank You Message -->
		<div id="thank-you-message-<?php echo esc_attr( $form_id ); ?>" class="thank-you-message" style="display: none;">
			<?php echo $thank_you_message; ?>
		</div>

		<div id="form-message" class="message" style="display: none;"></div>
		<ul id="form-errors" class="errors" style="display: none;"></ul>
	<?php endif; ?>
</div>

<script>
	document.addEventListener("DOMContentLoaded", function() {
		if (typeof window.hubspotProgressiveData === "undefined") {
			window.hubspotProgressiveData = {
				allowedFields: ["firstname", "lastname", "email", "company", "phone", "website", "primary_social_media_handle", "primary_social_platform", "instagram_handle", "youtube_handle", "tiktok_handle", "twitter_handle", "linkedin", "employee_range__c", "probable_use_case", "mixpanelID"],
				progressiveMappings: {}
			};
		}

		if (typeof window.hubspotProgressiveData.progressiveMappings === "undefined") {
			window.hubspotProgressiveData.progressiveMappings = {};
		}

		window.hubspotProgressiveData.progressiveMappings["<?php echo esc_js( $form_id ); ?>"] = <?php echo json_encode( $processed_mappings, JSON_FORCE_OBJECT ); ?>;
	});
</script>