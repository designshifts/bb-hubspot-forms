document.addEventListener('DOMContentLoaded', function () {
	const formElements = document.querySelectorAll('form[data-form-id]');
	if (formElements.length === 0) {
		return;
	}

	const userProfile = Cookies.get('UserProfile') ? JSON.parse(Cookies.get('UserProfile')) : {};

	formElements.forEach(formElement => {
		const formName = formElement.getAttribute('data-form-id');
		const progressiveMapping = hubspotProgressiveData.progressiveMappings[formName] || {};
		const hideKnownFields = formElement.getAttribute('data-hide-known-fields') === 'true';
		let formPrefillSource = 'none';
		let formProgressiveFilled = false;
		let hasHiddenFields = false;

		const formData = {};

		hubspotProgressiveData.allowedFields.forEach(fieldName => {
			if (fieldName === 'consent') {
				return; // Skip consent handling here
			}

			const inputElement = formElement.querySelector(`[name="${fieldName}"]`);

			if (inputElement) {
				// Step 1: Handle prefill for non-select fields only (let view.js handle selects)
				if (userProfile[fieldName] && inputElement.tagName !== 'SELECT') {
					inputElement.value = userProfile[fieldName];
					inputElement.classList.add('prefilled');
					formData[fieldName] = inputElement.value.trim();
					formPrefillSource = 'profile';
				}
				
				// For select fields, track if we have data (view.js will handle prefilling)
				if (userProfile[fieldName] && inputElement.tagName === 'SELECT') {
					formData[fieldName] = userProfile[fieldName];
					formPrefillSource = 'profile';
				}

				// Step 2: Hide known fields if hideKnownFields is enabled (except email)
				if (hideKnownFields && fieldName !== 'email') {
					// inputElement is already scoped to this form via formElement.querySelector()
					const fieldGroup = inputElement.closest('.wp-block-group');
					// Ensure fieldGroup exists and belongs to this form (safety check for multiple forms)
					// Since inputElement is from formElement.querySelector(), closest() will only find ancestors within formElement
					if (fieldGroup && formElement.contains(fieldGroup)) {
						// Check both userProfile and input value for non-select fields
						const hasValue = inputElement.tagName === 'SELECT' 
							? userProfile[fieldName] 
							: (inputElement.value.trim() || userProfile[fieldName]);
						
						if (hasValue) {
							// Check if this field has a progressive profiling replacement
							const replacementFieldName = progressiveMapping[fieldName];
							const replacementInput = replacementFieldName 
								? formElement.querySelector(`[name="${replacementFieldName}"]`)
								: null;
							
							if (replacementInput) {
								// Hide the known field and show the replacement field
								fieldGroup.classList.add('hidden-field');
								hasHiddenFields = true;
								
								const replacementFieldGroup = replacementInput.closest('.wp-block-group');
								// Ensure replacementFieldGroup exists and belongs to this form
								// Since replacementInput is from formElement.querySelector(), closest() will only find ancestors within formElement
								if (replacementFieldGroup && formElement.contains(replacementFieldGroup)) {
									replacementFieldGroup.classList.remove('hidden-field');
									replacementFieldGroup.classList.add('visible-field');
									formProgressiveFilled = true;
								}
							} else {
								// Hide the field (no replacement)
								fieldGroup.classList.add('hidden-field');
								hasHiddenFields = true;
							}
						}
					}
				}

				// Step 3: Process progressive mapping for fields that have values (when hideKnownFields is disabled)
				// Check both userProfile and input value, and hide known field if there's a replacement
				if (!hideKnownFields && fieldName !== 'email') {
					// inputElement is already scoped to this form via formElement.querySelector()
					const fieldGroup = inputElement.closest('.wp-block-group');
					// Ensure fieldGroup exists and belongs to this form (safety check for multiple forms)
					// Since inputElement is from formElement.querySelector(), closest() will only find ancestors within formElement
					if (fieldGroup && formElement.contains(fieldGroup)) {
						const hasValue = inputElement.tagName === 'SELECT' 
							? userProfile[fieldName] 
							: (inputElement.value.trim() || userProfile[fieldName]);
						
						if (hasValue) {
							const replacementFieldName = progressiveMapping[fieldName];
							if (replacementFieldName) {
								const replacementInput = formElement.querySelector(`[name="${replacementFieldName}"]`);
								if (replacementInput) {
									// Hide the known field and show the replacement field
									fieldGroup.classList.add('hidden-field');
									hasHiddenFields = true;
									
									const replacementFieldGroup = replacementInput.closest('.wp-block-group, .hidden-field');
									// Ensure replacementFieldGroup exists and belongs to this form
									// Since replacementInput is from formElement.querySelector(), closest() will only find ancestors within formElement
									if (replacementFieldGroup && formElement.contains(replacementFieldGroup)) {
										replacementFieldGroup.classList.remove('hidden-field');
										replacementFieldGroup.classList.add('visible-field');
										formProgressiveFilled = true;
									}
								}
							}
						}
					}
				}
			}
		});

		// Handle select fields that get prefilled by view.js (with delay to ensure view.js has run)
		function hidePrefilledSelectFields() {
			if (!hideKnownFields) return;
			
			hubspotProgressiveData.allowedFields.forEach(fieldName => {
				if (fieldName === 'consent' || fieldName === 'email') return;
				
				const inputElement = formElement.querySelector(`[name="${fieldName}"]`);
				if (inputElement && inputElement.tagName === 'SELECT' && userProfile[fieldName]) {
					// inputElement is already scoped to this form via formElement.querySelector()
					const fieldGroup = inputElement.closest('.wp-block-group');
					// Ensure fieldGroup exists and belongs to this form (safety check for multiple forms)
					// Since inputElement is from formElement.querySelector(), closest() will only find ancestors within formElement
					if (fieldGroup && formElement.contains(fieldGroup)) {
						const hasValue = inputElement.value.trim() || userProfile[fieldName];
						
						if (hasValue) {
							// Check if this field has a progressive profiling replacement
							const replacementFieldName = progressiveMapping[fieldName];
							const replacementInput = replacementFieldName 
								? formElement.querySelector(`[name="${replacementFieldName}"]`)
								: null;
							
							if (replacementInput) {
								// Hide the known field and show the replacement field
								fieldGroup.classList.add('hidden-field');
								hasHiddenFields = true;
								
								const replacementFieldGroup = replacementInput.closest('.wp-block-group');
								// Ensure replacementFieldGroup exists and belongs to this form
								// Since replacementInput is from formElement.querySelector(), closest() will only find ancestors within formElement
								if (replacementFieldGroup && formElement.contains(replacementFieldGroup)) {
									replacementFieldGroup.classList.remove('hidden-field');
									replacementFieldGroup.classList.add('visible-field');
									formProgressiveFilled = true;
								}
							} else {
								// Hide the field (no replacement)
								fieldGroup.classList.add('hidden-field');
								hasHiddenFields = true;
							}
						}
					}
				}
			});
		}

		// Call with delays to ensure view.js has prefilled select fields
		setTimeout(hidePrefilledSelectFields, 300);
		setTimeout(hidePrefilledSelectFields, 600);
		setTimeout(hidePrefilledSelectFields, 1000);

		// Set form dataset attributes for tracking
		formElement.dataset.prefillSource = formPrefillSource;
		formElement.dataset.progressiveFilled = formProgressiveFilled;
		formElement.dataset.hasHiddenFields = hasHiddenFields;

	});
});
