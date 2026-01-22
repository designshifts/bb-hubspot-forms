<?php
/**
 * Pre-fills form fields based on URL query parameters.
 */
function hubspot_prefill_form_fields() {
	// Define an array of allowed fields to prefill.
	$allowed_fields = array( 'firstname', 'lastname', 'email', 'company', 'website', 'primary_social_media_handle', 'primary_social_platform', 'instagram_handle', 'youtube_handle', 'tiktok_handle', 'twitter_handle', 'linkedin', 'employee_range__c', 'probable_use_case', 'phone', 'downloadable_id', 'page_url', 'event_trade_show_details__c', 'partnerstack_xid', 'partnerstack_partner_key__c', 'prefill_data' );

	// Loop through all query parameters and sanitize their values.
	foreach ( $_GET as $param => $value ) {
		// Check if the parameter is in the allowed fields.
		if ( in_array( $param, $allowed_fields, true ) ) {
			// Sanitize the query parameter value.
			$prefill_data[ $param ] = sanitize_text_field( urldecode( $value ) );
		}
	}

	// If there is pre-filled data, add it to the data layer
	// and output JavaScript to pre-fill the form fields.
	if ( ! empty( $prefill_data ) ) {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				// Find all forms with the `data-form-id` attribute.
				const formElements = document.querySelectorAll('form[data-form-id]');

				formElements.forEach(function (formElement) {
					const formName = formElement.getAttribute('data-form-id');

					// Initialize the dataLayer if not already initialized.
					window.dataLayer = window.dataLayer || [];

					// Prefill form fields
					const prefillData = <?php echo wp_json_encode( $prefill_data ); ?>;
					for (const [fieldName, value] of Object.entries(prefillData)) {
						const field = formElement.querySelector(`[name="${fieldName}"]`);
						if (field) {
							field.value = value;
						}
					}
				});
			});
		</script>
		<?php
	}
}

// Hook the function to wp_footer to ensure it runs on every page load.
add_action( 'wp_head', 'hubspot_prefill_form_fields' );