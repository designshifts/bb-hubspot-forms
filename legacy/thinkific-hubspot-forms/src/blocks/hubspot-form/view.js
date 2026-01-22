import { pushFormInteraction } from "./helper";

document.addEventListener('DOMContentLoaded', function () {
	// Detect if we are in the editor
	if (window.location.pathname.includes('/wp-admin/')) {
		return; // Don't run AJAX in the editor
	}

	(function ($) {
		$(document).ready(function () {
			// Detect all forms with data-form-id
			const formElements = $('form[data-form-id]');
			// Ensure blockEmailDomains is available with proper fallback
			const globalBlockEmailDomains = (typeof hubspotform_block_email_domains !== 'undefined' && hubspotform_block_email_domains?.blockEmailDomains) || false;
			let blockEmailDomains = ['true', true, 1, '1'].includes(globalBlockEmailDomains);


			formElements.each(function () {
				const formElement = $(this);
				const formName = formElement.data('form-id');
				let formStarted = false;
				let impressionTracked = false;
				let formCompletedTracked = false;
				let isPrefilling = false;
				const container = formElement.closest('.hubspot-form-container');
				const blockInstanceId = container.data('unique-id');

				// Check the GeoIP country code from the server. EEA, the UK and Switzerland. (The EEA comprises the EU Member States and Iceland, Liechtenstein, and Norway).
				const geoCountryCode = $('meta[name="geoip-country-code"]').attr('content');
				const euCountries = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'IS', 'LI', 'NO', 'GB', 'CH'];
				const userInEU = euCountries.includes(geoCountryCode);

				// Check if marketing consent is enabled
				const marketingConsentEnabled = formElement.data('enable-marketing-consent') === 'true' || formElement.data('enable-marketing-consent') === true;
				
				if (userInEU) {
					formElement.find('#consent').closest('label').show(); // Ensure the label is visible
					formElement.find('#consent').prop('checked', false);  // Reset the checkbox state (uncheck by default)
					
					// Show/hide marketing consent checkbox for EU users based on setting
					const marketingConsentCheckbox = formElement.find('#gdpr_opted_in');
					if (marketingConsentCheckbox.length) {
						if (marketingConsentEnabled) {
							marketingConsentCheckbox.closest('label').show();
							marketingConsentCheckbox.prop('checked', false); // Never pre-check marketing consent
						} else {
							marketingConsentCheckbox.closest('label').hide();
							marketingConsentCheckbox.prop('checked', false); // Ensure it's unchecked
						}
					}
				} else {
					formElement.find('#consent').prop('checked', true);  // Ensure it's checked
					formElement.find('#consent').closest('label').hide(); // Hide the label

					// Hide marketing consent checkbox for non-EU users
					const marketingConsentCheckbox = formElement.find('#gdpr_opted_in');
					if (marketingConsentCheckbox.length) {
						marketingConsentCheckbox.closest('label').hide();
						marketingConsentCheckbox.prop('checked', false); // Ensure it's unchecked

						// Add hidden input with "Not Applicable" value for non-EU users
						// Remove any existing hidden input first
						formElement.find('input[name="gdpr_opted_in_hidden"]').remove();

						// Create and append hidden input
						const hiddenInput = $('<input>', {
							type: 'hidden',
							name: 'gdpr_opted_in_hidden',
							value: 'Not Applicable'
						});
						formElement.append(hiddenInput);
					}
				}

				// Access the localized REST API URL and nonce with safety checks
				if (typeof hubspotform_rest_obj_think_blocks_hubspot_form === 'undefined') {
					console.error('REST API object is not available. HubSpot form functionality will be limited.');
					return; // Skip this form if REST API is not available
				}

				const restUrl = hubspotform_rest_obj_think_blocks_hubspot_form.rest_url;
				const nonce = hubspotform_rest_obj_think_blocks_hubspot_form.nonce;

				if (!restUrl || !nonce) {
					console.error('REST URL or nonce is missing.');
					return;
				}

				// Function to fetch user data from HubSpot using contact Email
				function fetchUserData(email) {
					return $.ajax({
						url: restUrl + '/hubspot-form/get-user-data',
						method: 'GET',
						data: { email: email },
						beforeSend: function (xhr) {
							xhr.setRequestHeader('X-WP-Nonce', nonce);
						}
					});
				}

				// Function to prefill the form fields
				function prefillForm(data) {
					// Set prefill flag to prevent event firing during prefill
					isPrefilling = true;

					// Get UserProfile cookie as fallback for missing data
					const userProfile = typeof Cookies !== 'undefined' && Cookies.get('UserProfile')
						? JSON.parse(Cookies.get('UserProfile'))
						: {};

					// console.log('Prefilling form with data:', data);
					// console.log('UserProfile cookie data:', userProfile);

					formElement.find('#email').val(data.email || '');
					if (formElement.find('#firstname').length) {
						formElement.find('#firstname').val(data.firstname || '');
					}
					if (formElement.find('#lastname').length) {
						formElement.find('#lastname').val(data.lastname || '');
					}
					if (formElement.find('#company').length) {
						formElement.find('#company').val(data.company || '');
					}
					if (formElement.find('#phone').length) {
						formElement.find('#phone').val(data.phone || '');
					}
					if (formElement.find('#website').length) {
						formElement.find('#website').val(data.website || '');
					}
					if (formElement.find('#primary_social_media_handle').length) {
						formElement.find('#primary_social_media_handle').val(data.primary_social_media_handle || '');
					}
					if (formElement.find('#instagram_handle').length) {
						formElement.find('#instagram_handle').val(data.instagram_handle || '');
					}
					if (formElement.find('#youtube_handle').length) {
						formElement.find('#youtube_handle').val(data.youtube_handle || '');
					}
					if (formElement.find('#tiktok_handle').length) {
						formElement.find('#tiktok_handle').val(data.tiktok_handle || '');
					}
					if (formElement.find('#twitter_handle').length) {
						formElement.find('#twitter_handle').val(data.twitter_handle || '');
					}
					if (formElement.find('#linkedin_handle').length) {
						formElement.find('#linkedin_handle').val(data.linkedin_handle || '');
					}

					// Checkbox (e.g., consent) - use HubSpot data first, fallback to UserProfile
					const $consent = formElement.find('#consent');
					if ($consent.length) {
						const consentValue = data.consent || userProfile.consent;
						const isChecked = consentValue === 'true' || consentValue === true;
						$consent.prop('checked', isChecked);
					}

					// Handle select fields with improved logic and debugging
					function prefillSelectFields() {
						// Use HubSpot data first, fallback to UserProfile cookie
						const rangeVal = data.employee_range__c || userProfile.employee_range__c || '';
						const useCaseVal = data.probable_use_case || userProfile.probable_use_case || '';
						const gdprOptedInVal = data.gdpr_opted_in || userProfile.gdpr_opted_in || 'Not Applicable';

						// console.log('Select field values to prefill:', { rangeVal, useCaseVal, gdprOptedInVal });

						const $employeeRange = formElement.find('#employee_range__c');
						const $useCase = formElement.find('#probable_use_case');
						const $gdprOptedIn = formElement.find('#gdpr_opted_in');

						// console.log('Found select elements:', {
						// 	employeeRangeFound: $employeeRange.length > 0,
						// 	useCaseFound: $useCase.length > 0
						// });

						// Handle employee range select
						if ($employeeRange.length && rangeVal) {
							// console.log('Attempting to prefill employee_range__c with:', rangeVal);

							// Check if option exists
							const $matchingOption = $employeeRange.find(`option[value="${rangeVal}"]`);
							// console.log('Matching option found:', $matchingOption.length > 0);

							if ($matchingOption.length) {
								$employeeRange.val(rangeVal);

								// Verify the value was set
								const actualValue = $employeeRange.val();
								// console.log('Set employee_range__c value:', actualValue);

								if (actualValue === rangeVal) {
									$employeeRange.trigger('change');
									$employeeRange.addClass('prefilled');

									// Hide the field group when prefilled
									// const fieldGroup = $employeeRange.closest('.wp-block-group, .hidden-field');
									// if (fieldGroup.length) {
									// 	fieldGroup.removeClass('visible-field').addClass('hidden-field');
									// 	console.log('Hidden employee_range__c field group');
									// }
								} else {
									console.warn('Failed to set employee_range__c value. Expected:', rangeVal, 'Actual:', actualValue);
								}
							} else {
								console.warn('No matching option found for employee_range__c value:', rangeVal);
								// Log available options for debugging
								const availableOptions = [];
								$employeeRange.find('option').each(function () {
									availableOptions.push($(this).val());
								});
								// console.log('Available employee_range__c options:', availableOptions);
							}
						}

						// Handle use case select
						if ($useCase.length && useCaseVal) {
							// console.log('Attempting to prefill probable_use_case with:', useCaseVal);

							// Check if option exists
							const $matchingOption = $useCase.find(`option[value="${useCaseVal}"]`);
							// console.log('Matching option found:', $matchingOption.length > 0);

							if ($matchingOption.length) {
								$useCase.val(useCaseVal);

								// Verify the value was set
								const actualValue = $useCase.val();
								// console.log('Set probable_use_case value:', actualValue);

								if (actualValue === useCaseVal) {
									$useCase.trigger('change');
									$useCase.addClass('prefilled');

									// Hide the field group when prefilled
									// const fieldGroup = $useCase.closest('.wp-block-group, .hidden-field');
									// if (fieldGroup.length) {
									// 	fieldGroup.removeClass('visible-field').addClass('hidden-field');
									// 	console.log('Hidden probable_use_case field group');
									// }
								} else {
									console.warn('Failed to set probable_use_case value. Expected:', useCaseVal, 'Actual:', actualValue);
								}
							} else {
								console.warn('No matching option found for probable_use_case value:', useCaseVal);
								// Log available options for debugging
								const availableOptions = [];
								$useCase.find('option').each(function () {
									availableOptions.push($(this).val());
								});
								// console.log('Available probable_use_case options:', availableOptions);
							}
						}

						// Handle GDPR opted in checkbox
						if ($gdprOptedIn.length && gdprOptedInVal) {
							// If value is "Yes", check the checkbox; otherwise uncheck it
							if (gdprOptedInVal === 'Yes') {
								$gdprOptedIn.prop('checked', true);
								$gdprOptedIn.trigger('change');
								$gdprOptedIn.addClass('prefilled');
							} else {
								$gdprOptedIn.prop('checked', false);
							}
						}
					}

					// Try to prefill select fields with multiple attempts and increasing delays
					prefillSelectFields(); // Immediate attempt

					setTimeout(() => {
						// console.log('Second attempt at select field prefill');
						prefillSelectFields();
					}, 100);

					setTimeout(() => {
						// console.log('Third attempt at select field prefill');
						prefillSelectFields();
					}, 500);

					// Reset prefill flag after prefill is complete
					setTimeout(() => {
						isPrefilling = false;

						// Check if form is fully completed after prefill
						const totalVisibleFields = formElement.find(
							'input:not([type="hidden"]):not(.hidden-field):not(#consent), textarea:not(.hidden-field), select:not(.hidden-field)'
						).filter(function () {
							return $(this).css('display') !== 'none' && $(this).is('[required], [aria-required="true"]');
						}).length;

						// Add prefilled fields to completed fields set
						formElement.find('input, textarea, select').each(function () {
							const fieldName = $(this).attr('name');
							const fieldValue = $(this).val();
							const value = fieldValue ? String(fieldValue).trim() : '';
							const isRequired = $(this).is('[required]') || $(this).attr('aria-required') === 'true';

							if (isRequired && value) {
								completedFields.add(fieldName);
							}
						});

						// Fire form_completed event if all fields are completed
						if (completedFields.size === totalVisibleFields && !formCompletedTracked) {
							formCompletedTracked = true;
							window.dataLayer.push({
								block_instance_id: blockInstanceId,
								event: "form_interaction",
								event_type: "form_completed",
								form_name: formName,
							});
						}
					}, 600);
				}

				// Initialize form prefill
				function initializeFormPrefill() {
					let prefillSource = "none";
					const userProfile = typeof Cookies !== 'undefined' && Cookies.get('UserProfile')
						? JSON.parse(Cookies.get('UserProfile'))
						: {};
					const email = userProfile.email || '';

					// console.log('Initializing form prefill. UserProfile:', userProfile);

					if (email) {
						prefillSource = "profile";
						fetchUserData(email)
							.done(function (response) {
								if (response.success && response.contactData) {
									const contact = response.contactData;

									// Only update cookie if at least one key field has non-empty value
									const hasValidData = contact.firstname || contact.lastname || contact.company || contact.phone || contact.website;

									if (hasValidData) {
										userProfile.firstname = contact.firstname || userProfile.firstname;
										userProfile.lastname = contact.lastname || userProfile.lastname;
										userProfile.email = contact.email || userProfile.email;
										userProfile.company = contact.company || userProfile.company;
										userProfile.phone = contact.phone || userProfile.phone;
										userProfile.website = contact.website || userProfile.website;
										userProfile.primary_social_media_handle = contact.primary_social_media_handle || userProfile.primary_social_media_handle;
										userProfile.instagram_handle = contact.instagram_handle || userProfile.instagram_handle;
										userProfile.youtube_handle = contact.youtube_handle || userProfile.youtube_handle;
										userProfile.tiktok_handle = contact.tiktok_handle || userProfile.tiktok_handle;
										userProfile.twitter_handle = contact.twitter_handle || userProfile.twitter_handle;
										userProfile.linkedin_handle = contact.linkedin_handle || userProfile.linkedin_handle;
										userProfile.employee_range__c = contact.employee_range__c || userProfile.employee_range__c;
										userProfile.probable_use_case = contact.probable_use_case || userProfile.probable_use_case;
										userProfile.consent = contact.consent || userProfile.consent;

										if (!window.location.pathname.includes('/wp-admin/')) {
											Cookies.set('UserProfile', JSON.stringify(userProfile), {
												expires: 30,
												path: '/',
												sameSite: 'Lax'
											});
										}

										// Increased delay to ensure form-field-mapping.js completes first
										setTimeout(() => {
											prefillForm(contact);
										}, 200);
									} else {
										console.warn('Fetched contact data is empty. Trying to prefill from cookie data.');
										// Try to prefill from cookie data if HubSpot data is empty
										if (userProfile.email || userProfile.firstname || userProfile.lastname || userProfile.employee_range__c || userProfile.probable_use_case) {
											prefillSource = "cookie";
											setTimeout(() => {
												prefillForm(userProfile);
											}, 200);
										}
									}
								} else {
									console.warn('Failed to fetch user data:', response.message);
									// Try to prefill from cookie data if AJAX fails
									if (userProfile.email || userProfile.firstname || userProfile.lastname || userProfile.employee_range__c || userProfile.probable_use_case) {
										prefillSource = "cookie";
										setTimeout(() => {
											prefillForm(userProfile);
										}, 200);
									}
								}
							})
							.fail(function (jqXHR, textStatus, errorThrown) {
								console.error('AJAX request failed:', textStatus, errorThrown);
								// Try to prefill from cookie data if AJAX fails
								if (userProfile.email || userProfile.firstname || userProfile.lastname || userProfile.employee_range__c || userProfile.probable_use_case || userProfile.primary_social_media_handle) {
									prefillSource = "cookie";
									setTimeout(() => {
										prefillForm(userProfile);
									}, 200);
								}
							});
					} else if (userProfile.firstname || userProfile.lastname || userProfile.employee_range__c || userProfile.probable_use_case || userProfile.primary_social_media_handle) {
						// No email but we have other data in the cookie
						// console.log('No email found, but other data exists in cookie. Attempting prefill.');
						prefillSource = "cookie";
						setTimeout(() => {
							prefillForm(userProfile);
						}, 200);
					} else {
						console.log('No prefill data available.');
					}
				}

				// Function to determine form type for analytics
				function determineFormType() {
					const hideKnownFields = formElement.data('hide-known-fields') === 'true' || formElement.data('hide-known-fields') === true;
					const hasHiddenFields = formElement[0].dataset.hasHiddenFields === 'true';
					const hasProgressiveProfiling = formElement[0].dataset.progressiveFilled === 'true';
					const hasPrefillData = formElement[0].dataset.prefillSource && formElement[0].dataset.prefillSource !== 'none';

					if (hideKnownFields && hasHiddenFields && hasProgressiveProfiling) {
						return 'shortened-progressive';
					} else if (hideKnownFields && hasHiddenFields) {
						return 'shortened';
					} else if (hasProgressiveProfiling) {
						return 'progressive';
					} else if (hasPrefillData) {
						return 'prefill';
					}
					return 'prefill'; // Default for backward compatibility
				}

				// Execute the prefill on page load with a small delay to ensure form-field-mapping.js completes
				setTimeout(() => {
					initializeFormPrefill();
				}, 50);

				// Detect when form is in viewport
				const observer = new IntersectionObserver(
					function (entries) {
						entries.forEach(entry => {
							if (entry.isIntersecting) {
								if (!impressionTracked) {
									impressionTracked = true;
									const formType = determineFormType();
									window.dataLayer.push({
										block_instance_id: blockInstanceId,
										event: "form_interaction",
										event_type: "impression",
										type: formType,
										form_name: formName,
									});
								}

								// Initialize prefill when form enters viewport
								initializeFormPrefill();
								observer.unobserve(formElement[0]);
							}
						});
					},
					{ threshold: 0.3 }
				);

				observer.observe(formElement[0]);

				// Track when a user starts interacting with the form
				formElement.find('input, textarea, select').one('focus', function () {
					if (!formStarted) {
						formStarted = true;
						window.dataLayer.push({
							block_instance_id: blockInstanceId,
							event: "form_interaction",
							event_type: "form_started",
							form_name: formName,
						});
					}
				});

				// Track completed fields
				let completedFields = new Set();

				// Remove error messages when user starts typing again
				formElement.find('input, textarea, select').on('input', function () {
					if ($(this).hasClass('input-error')) {
						$(this).removeClass('input-error').siblings('.form-error').remove();
					}
				});

				// Attach validation when a user clicks out of a field (blur event)
				// Only validate format/formatting errors on blur, not required field errors
				formElement.find('input, textarea, select').on('blur', function () {
					const fieldName = $(this).attr('name');
					const fieldValue = $(this).val();
					const value = fieldValue ? String(fieldValue).trim() : '';
					const errors = {};

					// Skip validation for hidden fields (only when hideKnownFields is enabled)
					const fieldGroup = $(this).closest('.wp-block-group');
					const hideKnownFieldsEnabled = formElement.data('hide-known-fields') === 'true';

					// Only skip validation if:
					// 1. hideKnownFields feature is enabled
					// 2. Field is inside a .wp-block-group that's hidden
					// 3. Field is not email (email always validates)
					// 4. Field is not consent (consent validates separately)
					if (hideKnownFieldsEnabled && 
						fieldGroup && 
						(fieldGroup.hasClass('hidden-field') || fieldGroup.css('display') === 'none') &&
						fieldName !== 'email' && 
						fieldName !== 'consent') {
						return; // Don't validate hidden known fields
					}

					const isRequired = $(this).is('[required]') || $(this).attr('aria-required') === 'true';

					if (typeof thinkBlocksL10n === 'undefined') {
						console.log('Using fallback validation messages - localized messages not available.');
						window.thinkBlocksL10n = {
							validEmailError: 'Please enter a valid email address',
							emailbusinessError: 'Please enter a business email. Don\'t have one? Check out our other plans <a target="_blank" href="https://www.thinkific.com/pricing/">here</a>.',
							validPhoneError: 'Please enter a valid phone number',
							validUrlError: 'Please enter a valid URL',
							firstnameRequired: 'Please enter your first name',
							lastnameRequired: 'Please enter your last name',
							emailRequired: 'Please enter your email',
							companyRequired: 'Company name is required',
							primarySocialMediaHandleRequired: 'Primary Social Media Handle is required.',
							phoneNumberRequired: 'Please enter your phone number',
							websiteRequired: 'Company website is required',
							employee_range__cRequired: 'Please select your company size',
							probable_use_caseRequired: 'Please select what we can help with',
							consentRequired: 'Consent is required',
							requiredField: 'This field is required',
							generalError: 'Something went wrong. Please try again',
						};
					}

					// Only validate format/formatting errors on blur, not required field errors
					// Required field errors will only show on form submission
					if (value) { // Only validate if there's a value
						switch (fieldName) {
							case 'email':
								const emailInput = value.trim();
								const emailParts = emailInput.split('@');
								const emailDomain = emailParts.length > 1 ? emailParts[1].toLowerCase() : '';

								if (!validateEmail(emailInput)) {
									errors.email = thinkBlocksL10n.validEmailError;
								} else {
									// ✅ Use per-form hidden input for toggle check
									const perFormToggle = formElement.find('input[name="blockEmailDomains"]').val();
									const blockEmailDomainsEnabled = ['true', true, '1', 1].includes(perFormToggle);

									if (blockEmailDomainsEnabled) {
										const blockedDomains = (typeof hubspotBlockedDomains !== 'undefined' && hubspotBlockedDomains?.blockedDomains) || [];
										if (blockedDomains.length > 0 && blockedDomains.includes(emailDomain)) {
											pushFormInteraction({
												event_type: "form_error",
												message: "business email required"
											})
											errors.email = thinkBlocksL10n.emailbusinessError;
										}
									}
								}
								break;
							case 'phone':
								if (formElement.find('#phone').is(':visible') && !validatePhone(value)) {
									errors.phone = thinkBlocksL10n.validPhoneError;
								}
								break;
							case 'website':
								if (formElement.find('#website').is(':visible') && !validateWebsite(value)) {
									errors.website = thinkBlocksL10n.validUrlError;
								}
								break;
							case 'probable_use_case':
								if (value === 'Support for my Thinkific Account') {
									formElement.find('#support-message').show();
								} else {
									formElement.find('#support-message').hide();
								}
								break;
							// Social media handle validation
							case 'instagram_handle':
							case 'youtube_handle':
							case 'tiktok_handle':
							case 'twitter_handle':
							case 'linkedin':
								// Only validate if this field is currently visible (active platform)
								const fieldContainer = $(this).closest('.social-handle-field');
								if (fieldContainer.length && fieldContainer.css('display') !== 'none') {
									const platform = fieldName.replace('_handle', '');
									const validationResult = validateSocialHandle(value, platform);
									if (!validationResult.isValid) {
										errors[fieldName] = validationResult.message;
									}
								}
								break;
						}
					}

					// Remove existing error messages for this field
					const currentField = $(this);
					if (currentField.attr('name') && currentField.attr('name').endsWith('_handle')) {
						// For social media handle fields, remove errors from outside the wrapper
						const fieldContainer = currentField.closest('.social-handle-field');
						if (fieldContainer.length) {
							fieldContainer.parent().find('.form-error').remove();
						} else {
							currentField.siblings('.form-error').remove();
						}
					} else {
						currentField.siblings('.form-error').remove();
					}

					if (Object.keys(errors).length > 0) {
						for (const [field, message] of Object.entries(errors)) {
							const inputField = formElement.find(`[name="${field}"]`);
							if (inputField.length) {
								// Special handling for social media handle fields
								if (field.endsWith('_handle')) {
									const fieldContainer = inputField.closest('.social-handle-field');
									if (fieldContainer.length) {
										// Insert error message after the field container (outside the wrapper)
										fieldContainer.after(`<p class="form-error">${message}</p>`);
									} else {
										// Fallback to default behavior
										inputField.after(`<p class="form-error">${message}</p>`);
									}
								} else {
									// Default behavior for other fields
									inputField.after(`<p class="form-error">${message}</p>`);
								}
								inputField.addClass('input-error');
							}
						}
					} else {
						$(this).removeClass('input-error').siblings('.form-error').remove();
						// Only add to completed fields if there's a value and no errors
						if (value) {
							// Only fire field completion event if not prefilling and field hasn't been completed before
							if (!isPrefilling && !completedFields.has(fieldName)) {
								completedFields.add(fieldName);

								// Push successful field completion to dataLayer
								window.dataLayer.push({
									block_instance_id: blockInstanceId,
									event: "form_interaction",
									event_type: "form_field_completed",
									form_name: formName,
									field_name: fieldName,
								});
							} else if (!isPrefilling) {
								// Field already completed, just add to set without firing event
								completedFields.add(fieldName);
							}
						}
					}
					// If all fields are completed, push form completed event to dataLayer
					// Only fire if not prefilling and form hasn't been completed before
					if (!isPrefilling && !formCompletedTracked) {
						const totalVisibleFields = formElement.find(
							'input:not([type="hidden"]):not(.hidden-field):not(#consent), textarea:not(.hidden-field), select:not(.hidden-field)'
						).filter(function () {
							return $(this).css('display') !== 'none' && $(this).is('[required], [aria-required="true"]');
						}).length;

						if (completedFields.size === totalVisibleFields) {
							formCompletedTracked = true;
							window.dataLayer.push({
								block_instance_id: blockInstanceId,
								event: "form_interaction",
								event_type: "form_completed",
								form_name: formName,
							});
						}
					}
				});

				// Handle autofill events to ensure validation runs
				formElement.find('input, textarea, select').on('animationstart', function (e) {
					// Detect autofill animation (browsers add animation when autofilling)
					if (e.animationName === 'onAutoFillStart' || e.animationName === 'onAutoFillCancel') {
						const fieldName = $(this).attr('name');
						const fieldValue = $(this).val();
						const value = fieldValue ? String(fieldValue).trim() : '';
						const isRequired = $(this).is('[required]') || $(this).attr('aria-required') === 'true';

						// If field has a value and is required, add it to completed fields
						// Only fire events if not prefilling
						if (isRequired && value) {
							if (!isPrefilling && !completedFields.has(fieldName)) {
								completedFields.add(fieldName);
								$(this).removeClass('input-error').siblings('.form-error').remove();

								// Push successful field completion to dataLayer
								window.dataLayer.push({
									block_instance_id: blockInstanceId,
									event: "form_interaction",
									event_type: "form_field_completed",
									form_name: formName,
									field_name: fieldName,
								});
							} else if (!isPrefilling) {
								// Field already completed, just add to set without firing event
								completedFields.add(fieldName);
								$(this).removeClass('input-error').siblings('.form-error').remove();
							}
						}
					}
				});

				// Also handle the change event for autofill
				formElement.find('input, textarea, select').on('change', function () {
					const fieldName = $(this).attr('name');
					const fieldValue = $(this).val();
					const value = fieldValue ? String(fieldValue).trim() : '';
					const isRequired = $(this).is('[required]') || $(this).attr('aria-required') === 'true';

					// If field has a value and is required, add it to completed fields
					// Only fire events if not prefilling
					if (isRequired && value) {
						if (!isPrefilling && !completedFields.has(fieldName)) {
							completedFields.add(fieldName);
							$(this).removeClass('input-error').siblings('.form-error').remove();

							// Push successful field completion to dataLayer
							window.dataLayer.push({
								block_instance_id: blockInstanceId,
								event: "form_interaction",
								event_type: "form_field_completed",
								form_name: formName,
								field_name: fieldName,
							});
						} else if (!isPrefilling) {
							// Field already completed, just add to set without firing event
							completedFields.add(fieldName);
							$(this).removeClass('input-error').siblings('.form-error').remove();
						}
					}
				});

				// Function to check for autofilled fields and validate them
				function checkAutofilledFields() {
					formElement.find('input, textarea, select').each(function () {
						const fieldName = $(this).attr('name');
						const fieldValue = $(this).val();
						const value = fieldValue ? String(fieldValue).trim() : '';
						const isRequired = $(this).is('[required]') || $(this).attr('aria-required') === 'true';

						// Check if field has a value and appears to be autofilled
						if (isRequired && value && $(this).css('background-color') !== 'rgba(0, 0, 0, 0)') {
							// Trigger validation for this field
							$(this).trigger('blur');
						}
					});
				}

				// Check for autofilled fields after a short delay (to allow autofill to complete)
				setTimeout(checkAutofilledFields, 100);
				setTimeout(checkAutofilledFields, 500);
				setTimeout(checkAutofilledFields, 1000);

				// Show or hide the support message based on the selected use case
				formElement.find('#probable_use_case').on('change', function () {
					const value = $(this).val();
					if (value === 'Support for my Thinkific Account') {
						formElement.find('#support-message').show();
					} else {
						formElement.find('#support-message').hide();
					}
				});
				//console.log('Submitting with blockEmailDomains:', blockEmailDomains);

				// Track form submission state to prevent double submissions
				let isSubmitting = false;

				// Form submission handler
				formElement.on('submit', function (e) {
					e.preventDefault();

					// Prevent double submissions
					if (isSubmitting) {
						console.log('HubSpot Form: Submission blocked - form is already submitting');
						return false;
					}

					// Check if form is currently throttled
					if (throttlingState.isThrottled) {
						console.log('HubSpot Form: Submission blocked - form is throttled');
						// Show throttling error if not already visible
						if (formElement.find('.form-error').length === 0) {
							showThrottlingError(formElement, throttlingState.timeRemaining);
						}
						return false;
					}

					isSubmitting = true;

					// Clear any existing errors
					clearErrors(formElement);
					clearMessages(formElement);

					const clientErrors = {};

					// Check for autofilled fields before validation
					checkAutofilledFields();

					// Check for existing error messages first
					const existingErrors = formElement.find('.form-error, .input-error');
					if (existingErrors.length > 0) {
						displayErrors({ submission: 'Please fix the errors before submitting.' }, formElement, false);
						isSubmitting = false; // Reset submission state
						return;
					}

					// Specifically check email field for errors
					const emailField = formElement.find('#email');
					if (emailField.hasClass('input-error') || emailField.siblings('.form-error').length > 0) {
						displayErrors({ submission: 'Please fix the email field errors before submitting.' }, formElement, false);
						isSubmitting = false; // Reset submission state
						return;
					}

					// Find hidden fields and store their names
					const visibleFields = [];
					formElement.find('.wp-block-group:not(.hidden-field) input, .wp-block-group:not(.hidden-field) textarea, .wp-block-group:not(.hidden-field) select').each(function () {
						const fieldName = $(this).attr('name');
						if (fieldName) {
							visibleFields.push(fieldName);
						}
					});

					// Add the `visible_fields` data as a hidden input
					const visibleFieldsInput = $('<input>')
						.attr('type', 'hidden')
						.attr('name', 'visible_fields')
						.val(JSON.stringify(visibleFields));

					formElement.append(visibleFieldsInput);

					// Retrieve blockEmailDomains again before submission
					let perFormToggle = formElement.find('input[name="blockEmailDomains"]').val();
					let blockEmailDomains = ['true', true, '1', 1].includes(perFormToggle);

					// Detect if user is in the EU based on country code meta tag
					const geoCountryCode = $('meta[name="geoip-country-code"]').attr('content');
					const euCountries = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'IS', 'LI', 'NO', 'GB', 'CH'];
					const userInEU = euCountries.includes(geoCountryCode);

					// Find the consent checkbox
					const consentInput = formElement.find('#consent');
					const consentLabel = consentInput.closest('label');

					formElement.find('input, textarea, select').each(function () {
						const fieldName = $(this).attr('name');
						const fieldValue = $(this).val();
						const value = fieldValue ? String(fieldValue).trim() : '';
						
						// Skip validation for hidden fields (only when hideKnownFields is enabled)
						const fieldGroup = $(this).closest('.wp-block-group');
						const hideKnownFieldsEnabled = formElement.data('hide-known-fields') === 'true';

						// Only skip validation if:
						// 1. hideKnownFields feature is enabled
						// 2. Field is inside a .wp-block-group that's hidden
						// 3. Field is not email (email always validates)
						// 4. Field is not consent (consent validates separately)
						if (hideKnownFieldsEnabled && 
							fieldGroup && 
							(fieldGroup.hasClass('hidden-field') || fieldGroup.css('display') === 'none') &&
							fieldName !== 'email' && 
							fieldName !== 'consent') {
							return; // Don't validate hidden known fields
						}

						const isRequired = $(this).is('[required]') || $(this).attr('aria-required') === 'true';

						// Check if this is a social media handle field
						const isSocialHandleField = (fieldName && fieldName.endsWith('_handle')) || (fieldName === 'linkedin');

						if (isRequired && !value) {
							// For social media handle fields, only validate if they're visible
							if (isSocialHandleField) {
								const fieldContainer = $(this).closest('.social-handle-field');
								if (fieldContainer.length && fieldContainer.css('display') !== 'none') {
									clientErrors[fieldName] = thinkBlocksL10n[fieldName + "Required"] || "This field is required";
								}
							} else {
								// For non-social media fields, validate normally
								clientErrors[fieldName] = thinkBlocksL10n[fieldName + "Required"] || "This field is required";
							}
						} else if (fieldName === 'email' && value) {
							// Validate email format and business email requirements
							const emailInput = value.trim();
							const emailParts = emailInput.split('@');
							const emailDomain = emailParts.length > 1 ? emailParts[1].toLowerCase() : '';

							if (!validateEmail(emailInput)) {
								clientErrors.email = thinkBlocksL10n.validEmailError;
							} else {
								// Check business email requirements
								const perFormToggle = formElement.find('input[name="blockEmailDomains"]').val();
								const blockEmailDomainsEnabled = ['true', true, '1', 1].includes(perFormToggle);

								if (blockEmailDomainsEnabled) {
									const blockedDomains = (typeof hubspotBlockedDomains !== 'undefined' && hubspotBlockedDomains?.blockedDomains) || [];
									if (blockedDomains.length > 0 && blockedDomains.includes(emailDomain)) {
										pushFormInteraction({
											event_type: "form_error",
											message: "business email required"
										})
										clientErrors.email = thinkBlocksL10n.emailbusinessError;
									}
								}
							}
						} else if ((fieldName && fieldName.endsWith('_handle') && value) || (fieldName === 'linkedin' && value)) {
							// Only validate if this field is currently visible (active platform)
							const fieldContainer = $(this).closest('.social-handle-field');
							if (fieldContainer.length && fieldContainer.css('display') !== 'none') {
								const platform = fieldName.replace('_handle', '');
								const validationResult = validateSocialHandle(value, platform);
								if (!validationResult.isValid) {
									clientErrors[fieldName] = validationResult.message;
								}
							}
						}
					});

					// Check if consent is required and not checked
					if (userInEU && consentInput.length && !consentInput.is(':checked')) {
						clientErrors['legalConsentOptions'] = "You must agree to the terms and conditions to proceed.";

						// Ensure the error is displayed next to the checkbox
						consentInput.addClass('input-error');

						// Ensure `consentLabel` is correctly referenced**
						if (consentLabel.length) {
							consentLabel.find('.form-error').remove(); // Remove existing error message
							consentLabel.append('<p class="form-error">You must agree to the terms and conditions to proceed.</p>');
						} else {
							// If `label` is missing, insert error message right after the checkbox
							consentInput.after('<p class="form-error">You must agree to the terms and conditions to proceed.</p>');
						}
					}

					// If any errors exist, stop form submission
					if (Object.keys(clientErrors).length > 0) {
						displayErrors(clientErrors, formElement, false);
						isSubmitting = false; // Reset submission state
						return;
					}

					// Clear existing messages and errors only if validation passes
					clearErrors(formElement);
					clearMessages(formElement);

					const formId = formElement.data('form-id');

					// Collect hidden field data
					const hiddenInputs = formElement.find('input[type="hidden"]');

					let hiddenFieldData = {};
					hiddenInputs.each(function (key) {
						hiddenFieldData[$(this).attr('name')] = $(this).val();
					});

					// function getFieldValue(selector) {

					// 	const el = formElement.find(selector);
					// 	if(selector === '#phone') {
					// 		console.log('phone',el);	
					// 	}
					// 	return el.length ? el.val() : '';
					// }

					// Chris if you're reading this - I updated your code to watch for my form plugin.
					function getFieldValue(selector) {
						const el = formElement.find(selector);

						if (selector === '#phone') {
							const domEl = el[0];
							if (domEl && domEl.hasAttribute('data-country-select') && domEl._iti) {
								// return 'What do you see here?'
								return domEl._iti.getNumber();
							}
						}

						if (selector === "#mixpanel_id" && !el.val()) {
							const mixpanelCookie = Cookies.get('mp_e9f85a260e22673665c335ea07907e45_mixpanel');
							if (mixpanelCookie) {
								try {
									const parsedCookie = JSON.parse(mixpanelCookie);
									const distinctId = parsedCookie.distinct_id;
									el.val(distinctId)
									return distinctId;
								} catch (e) {
									console.error("Error parsing cookie:", e);
								}
							}
						}
						return el.length ? el.val() : '';
					}


					const formData = {
						formId: formId,
						firstname: getFieldValue('#firstname'),
						lastname: getFieldValue('#lastname'),
						email: getFieldValue('#email'),
						company: getFieldValue('#company'),
						phone: getFieldValue('#phone'),
						website: getFieldValue('#website'),
						mixpanel_id: getFieldValue('#mixpanel_id'),
						primary_social_media_handle: getFieldValue('#primary_social_media_handle'),
						primary_social_platform: getFieldValue('#primary_social_platform'),
						instagram_handle: getFieldValue('#instagram_handle'),
						youtube_handle: getFieldValue('#youtube_handle'),
						tiktok_handle: getFieldValue('#tiktok_handle'),
						twitter_handle: getFieldValue('#twitter_handle'),
						linkedin: getFieldValue('#linkedin'),
						employee_range__c: getFieldValue('#employee_range__c'),
						probable_use_case: getFieldValue('#probable_use_case'),
						hutk: Cookies.get('hubspotutk') || '',
						pageUri: window.location.pathname,
						pageName: document.title,
						blockEmailDomains: blockEmailDomains,
						redirectUrl: formElement.data('redirect-url') || ''

					};

					const ignoreHiddenFields = ['#mixpanel_id']
					$.each(hiddenFieldData, function (key, value) {
						if (!key.includes(ignoreHiddenFields)) {
							formData[key] = value;
						}
					});

					// Include consent data only if user is in the EU
					if (userInEU) {
						formData.legalConsentOptions = {
							consent: {
								consentToProcess: consentInput.is(':checked') ? 'true' : 'false',
								text: "I agree to allow Company Name to store and process my personal data.",
								communications: [
									{
										value: consentInput.is(':checked') ? 'true' : 'false',
										subscriptionTypeId: 466761704,
										text: "I agree to receive marketing communications."
									}
								]
							}
						};
					}

					// Handle marketing consent value
					const marketingConsentCheckbox = formElement.find('#gdpr_opted_in');
					const hiddenInput = formElement.find('input[name="gdpr_opted_in_hidden"]');
					const marketingConsentEnabled = formElement.data('enable-marketing-consent') === 'true' || formElement.data('enable-marketing-consent') === true;
					
					if (userInEU) {
						// User is in GDPR region: check if marketing consent checkbox is visible and enabled
						if (marketingConsentEnabled && marketingConsentCheckbox.length && marketingConsentCheckbox.closest('label').is(':visible')) {
							// Marketing consent checkbox is visible: checked = "Yes", unchecked = "No"
							formData.gdpr_opted_in = marketingConsentCheckbox.is(':checked') ? 'Yes' : 'No';
						} else {
							// Marketing consent checkbox is hidden or disabled: set to "Not Applicable"
							formData.gdpr_opted_in = 'Not Applicable';
						}
					} else {
						// User is not in GDPR region: use hidden input value or default to "Not Applicable"
						formData.gdpr_opted_in = hiddenInput.length ? hiddenInput.val() : 'Not Applicable';
					}

					// Trigger reCAPTCHA execution with safety check
					if (typeof grecaptcha !== 'undefined') {
						grecaptcha.ready(function () {
							grecaptcha.execute('6Lc38q8qAAAAAOCUm8Ar7Mc4nSNYK08yIyrj4Ez-', { action: 'submit' })
								.then(function (token) {
									formData['g-recaptcha-response'] = token;
									submitFormWithData();
								})
								.catch(function (error) {
									console.error('reCAPTCHA execution failed:', error);
									pushFormInteraction({
										event_type: "form_error",
										message: "triggered recaptcha wait period",
									});

									// Show throttling error for reCAPTCHA failures
									showThrottlingError(formElement, 30);
									isSubmitting = false;
								});
						});

					} else {
						// reCAPTCHA not loaded yet, submit without token (server should handle this gracefully)
						console.warn('reCAPTCHA not loaded, submitting form without token');
						submitFormWithData();
					}

					function submitFormWithData() {
						let submitButton = formElement.find('button[type="submit"]');
						const originalButtonText = submitButton.text().trim();
						const submitStartTime = Date.now();
						let timeout5s, timeout30s;

						// Function to update button state
						function updateButtonState(text, isLoading = true) {
							// Create spinner HTML if not exists
							const spinnerHtml = '<span class="submit-spinner" aria-hidden="true"></span>';

							// Update button content
							submitButton.html(`${spinnerHtml} ${text}`);

							// Update accessibility attributes
							submitButton.prop('disabled', isLoading);
							submitButton.attr('aria-busy', isLoading ? 'true' : 'false');

							// Announce to screen readers
							if (isLoading) {
								const announcement = document.createElement('div');
								announcement.setAttribute('aria-live', 'polite');
								announcement.setAttribute('aria-atomic', 'true');
								announcement.className = 'sr-only';
								announcement.textContent = text;
								document.body.appendChild(announcement);

								// Remove announcement after a delay
								setTimeout(() => {
									document.body.removeChild(announcement);
								}, 1000);
							}
						}

						// Function to reset button state
						function resetButtonState() {
							clearTimeout(timeout5s);
							clearTimeout(timeout30s);
							submitButton.html(originalButtonText);
							submitButton.prop('disabled', false);
							submitButton.attr('aria-busy', 'false');
						}

						// Set initial loading state
						updateButtonState(thinkBlocksL10n.submittingText || 'Submitting...');

						// Set up timeout handlers
						timeout5s = setTimeout(() => {
							updateButtonState(thinkBlocksL10n.stillWorkingText || 'Still working...');
						}, 5000);

						timeout30s = setTimeout(() => {
							updateButtonState(thinkBlocksL10n.hangTightText || 'Hang tight—still processing...');

							// Fire timeout event to dataLayer
							window.dataLayer.push({
								block_instance_id: blockInstanceId,
								event: 'form_interaction',
								event_type: 'submit_timeout_30s',
								form_name: formName
							});
						}, 30000);

						$.ajax({
							url: restUrl + '/hubspot-form/submit',
							method: 'POST',
							beforeSend: function (xhr) {
								xhr.setRequestHeader('X-WP-Nonce', nonce);
							},
							data: JSON.stringify(formData),
							contentType: 'application/json',
							success: function (response) {
								// Calculate response time
								const responseTime = Date.now() - submitStartTime;

								// Ensure minimum loading time of 500ms to avoid flicker
								const minLoadingTime = Math.max(500 - responseTime, 0);

								setTimeout(() => {
									// Clear timeouts
									clearTimeout(timeout5s);
									clearTimeout(timeout30s);
									console.log('inside set time out');

									// Use the original formElement that triggered the submission
									let formProgressiveFilled = formElement[0].dataset.progressiveFilled === "true";
									let formPrefillSource = formElement[0].dataset.prefillSource || 'none';

									if (formId) {
										window.dataLayer.push({
											block_instance_id: blockInstanceId,
											event: "form_interaction",
											event_type: "form_submission",
											form_name: formName,
											form_progressive_filled: formProgressiveFilled,
											form_prefill_source: formPrefillSource,
											form_data: formData,
										});
									}

									if (response.success) {
										console.log('Form submission successful:', response);

										// Store user profile data in cookie for future use FIRST (before any redirects)
										let userProfileData = {};

										if (response.contactData) {
											// Use enriched HubSpot contact data
											console.log("response data", response.contactData);
											userProfileData = {
												firstname: response.contactData.firstname || '',
												lastname: response.contactData.lastname || '',
												email: response.contactData.email || '',
												company: response.contactData.company || '',
												phone: response.contactData.phone || '',
												website: response.contactData.website || '',
												primary_social_media_handle: response.contactData.primary_social_media_handle || '',
												instagram_handle: response.contactData.instagram_handle || '',
												youtube_handle: response.contactData.youtube_handle || '',
												tiktok_handle: response.contactData.tiktok_handle || '',
												twitter_handle: response.contactData.twitter_handle || '',
												linkedin_handle: response.contactData.linkedin_handle || '',
												employee_range__c: response.contactData.employee_range__c || '',
												probable_use_case: response.contactData.probable_use_case || '',
												consent: response.contactData.consent || '',
												mixpanel_id: response.contactData.mixpanel_id || ''
											};
										} else {
											// Fallback to submitted form data (minus g-recaptcha)
											const { 'g-recaptcha-response': _, ...submitted } = formData;
											userProfileData = submitted;
											console.log("submitted data", submitted);
										}

										// Set cookie immediately, regardless of redirect
										if (!window.location.pathname.includes('/wp-admin/')) {
											console.log('Setting UserProfile cookie with data:', userProfileData);
											Cookies.set('UserProfile', JSON.stringify(userProfileData), {
												expires: 7,
												path: '/',
												sameSite: 'Lax',
											});
										}

										const redirectUrl = formElement.data('redirect-url');
										const appendEmail = formElement.attr('data-append-email') === 'true';
										const userEmail = formData.email;

										console.log('Redirect URL:', redirectUrl);
										console.log('Append email:', appendEmail);
										console.log('User email:', userEmail);

										// Handle redirects after cookie is set
										if (redirectUrl) {
											console.log('Redirecting to:', redirectUrl);
											if (appendEmail && userEmail) {
												// Use URL constructor for clean parameter handling
												const url = new URL(redirectUrl);
												url.searchParams.set('email', userEmail);
												window.location.href = url.toString();
											} else {
												window.location.href = redirectUrl;
											}
											return;
										}

										console.log('No redirect URL, showing thank you message');

										// Show thank you message if no redirect URL
										if (!redirectUrl) {
											const formId = formElement.attr('data-form-id');
											// Find the thank you message within the same container as the form
											const container = formElement.closest('.hubspot-form-container');
											const thankYouMessage = container.find(`#thank-you-message-${formId}`);

											if (thankYouMessage.length) {
												console.log('HubSpot Form: Showing thank you message for form', formId);

												// Hide the form first
												formElement.hide();

												// Show thank you message
												thankYouMessage.show();

												// Reset button state
												resetButtonState();

												// Scroll to thank you message
												thankYouMessage[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
											} else {
												console.warn('HubSpot Form: Thank you message not found for form', formId);
												// Reset button state even if thank you message not found
												resetButtonState();
											}
										}

									} else if (response.errors) {
										// Reset button state on error
										resetButtonState();
										displayErrors(response.errors);
									}

									// Reset submission state
									isSubmitting = false;
								}, minLoadingTime);
							},
							error: function (xhr) {
								// Clear timeouts
								clearTimeout(timeout5s);
								clearTimeout(timeout30s);

								// Reset button state
								resetButtonState();

								// Reset submission state
								isSubmitting = false;

								// Clear any existing errors before showing new ones
								clearErrors(formElement);
								const errorContainer = formElement.closest('.hubspot-form-container');
								errorContainer.find('.form-error').remove();

								let errorResponse;
								try {
									errorResponse = JSON.parse(xhr.responseText);
								} catch (e) {
									console.error('Error parsing server response:', e);
								}

								function getDeepestString(node) {
									let best = { depth: -1, text: "" };
									(function walk(n, d) {
										if (typeof n === "string") {
											if (d > best.depth) best = { depth: d, text: n };
											return;
										}
										if (n && typeof n === "object") {
											for (const k in n) walk(n[k], d + 1);
										}
									})(node, 0);
									return best.text;
								}

								let errorText = "";

								try {
									const json = JSON.parse(xhr.responseText);
									// prefer nested message/errors if present
									errorText =
										getDeepestString(json?.errors) ||
										getDeepestString(json?.message) ||
										getDeepestString(json) ||
										"";
								} catch (e) {
									// non-JSON fallback
									errorText = xhr?.statusText || "Server error";
								}

								// final hard fallback to keep message always a string
								if (!errorText || typeof errorText !== "string") {
									errorText = "Server error";
								}

								// Handle 429 rate limiting specifically
								if (xhr.status === 429) {
									// Check if this is a reCAPTCHA throttling scenario
									const isRecaptchaThrottling = errorText.toLowerCase().includes('recaptcha') ||
										errorText.toLowerCase().includes('wait') ||
										errorText.toLowerCase().includes('throttle') ||
										errorText.toLowerCase().includes('rapid');

									if (isRecaptchaThrottling) {
										// Push correct dataLayer event for reCAPTCHA throttling
										window.dataLayer.push({
											event: "form_interaction",
											event_type: "form_error",
											message: "triggered recaptcha wait period"
										});

										// Show throttling error with countdown
										showThrottlingError(formElement, 30); // 30 second cooldown
										return;
									} else {
										// Regular rate limiting
										window.dataLayer.push({
											event: "form_interaction",
											event_type: "form_error",
											message: "rate limit exceeded"
										});

										// Show rate limiting error with countdown
										showThrottlingError(formElement, 60); // 60 second cooldown
										return;
									}
								}

								// ---- dataLayer push for other errors ----
								window.dataLayer.push({
									event: "form_interaction",
									event_type: "form_submit_error", // fixed typo: sumbit -> submit
									message: errorText
								});

								// Get container reference (ensure it's in scope)
								// Note: errorContainer already defined above when clearing errors

								if (errorResponse && errorResponse.errors) {
									displayErrors(errorResponse.errors, formElement);
								} else if (errorResponse && errorResponse.message) {
									// Handle cases where server returns 'message' instead of 'errors' (e.g., reCAPTCHA validation)
									const errorEl = $('<p class="form-error"></p>').text(errorResponse.message);
									errorContainer.prepend(errorEl);
								} else if (errorText) {
									// Fallback to errorText if message/errors structure not found
									const errorEl = $('<p class="form-error"></p>').text(errorText);
									errorContainer.prepend(errorEl);
								} else {
									errorContainer.prepend('<p class="form-error">Something went wrong on our end. Please try again in a few minutes.</p>');
								}
							},
							complete: function () {
								// This will be called after success/error, but we handle button state in those callbacks
							},
						});
					}
				});
			});

			// Throttling state management
			let throttlingState = {
				isThrottled: false,
				timeRemaining: 0,
				timer: null
			};

			function showThrottlingError(formElement, cooldownSeconds) {
				// Set throttling state
				throttlingState.isThrottled = true;
				throttlingState.timeRemaining = cooldownSeconds;

				// Disable submit button
				const submitButton = formElement.find('button[type="submit"]');
				submitButton.prop('disabled', true);
				submitButton.addClass('throttled');

				// Use existing error system to show throttling message
				const throttlingMessage = `Please wait before trying again. You've submitted too many requests in a short period. To prevent spam, we've temporarily paused submissions. You can try again in ${cooldownSeconds} seconds. Thanks for your patience.`;

				// Display using existing error system with throttling class
				displayErrors({ submission: throttlingMessage }, formElement, true);

				// Add special class to throttling error for styling
				const errorElement = formElement.find('.form-error').first();
				if (errorElement.length) {
					errorElement.addClass('throttling-error');
				}

				// Start countdown timer
				startThrottlingCountdown(formElement, cooldownSeconds);
			}

			function startThrottlingCountdown(formElement, cooldownSeconds) {
				throttlingState.timer = setInterval(() => {
					throttlingState.timeRemaining--;

					// Update the error message with new countdown
					const errorElement = formElement.find('.form-error').first();
					if (errorElement.length && errorElement.text().includes('You can try again in')) {
						const updatedMessage = errorElement.text().replace(/You can try again in \d+ seconds/, `You can try again in ${throttlingState.timeRemaining} seconds`);
						errorElement.text(updatedMessage);
					}

					if (throttlingState.timeRemaining <= 0) {
						clearInterval(throttlingState.timer);
						throttlingState.timer = null;
						throttlingState.isThrottled = false;

						// Clear throttling error message
						clearErrors(formElement);

						// Re-enable submit button
						const submitButton = formElement.find('button[type="submit"]');
						submitButton.prop('disabled', false);
						submitButton.removeClass('throttled');

						// Push dataLayer event for throttling end
						window.dataLayer.push({
							event: "form_interaction",
							event_type: "throttling_ended",
							form_name: formElement.data('form-id')
						});
					}
				}, 1000);
			}

			function clearThrottlingState() {
				if (throttlingState.timer) {
					clearInterval(throttlingState.timer);
					throttlingState.timer = null;
				}
				throttlingState.isThrottled = false;
				throttlingState.timeRemaining = 0;
			}

			// Utility functions. Maybe move these to a separate file under src/js/scripts...
			function validateEmail(email) {
				const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

				// if (!email.includes("@")) {
				// 	pushFormInteraction({
				// 		event_type: "form_error",
				// 		message: "missing @ symbol",
				// 		field_name: "form_interaction"
				// 	});
				// 	return false;
				// }

				return re.test(email);
			}

			function validatePhone(phone) {
				// Loosened phone validation - accepts various formats
				// Must contain at least 7 digits and only valid characters
				const digitsOnly = phone.replace(/[^0-9]/g, '');
				const validChars = /^[0-9\s\-\(\)\+\.]+$/;

				// Check if it has enough digits (minimum 7, maximum 15)
				if (digitsOnly.length < 7 || digitsOnly.length > 15) {
					return false;
				}

				// Check if it only contains valid characters
				if (!validChars.test(phone)) {
					return false;
				}

				return true;
			}

			function validateWebsite(website) {
				const re = /^(http|https):\/\/[^ "\n]+$/;
				return re.test(website);
			}

			function validateSocialHandle(handle, platform) {
				// Remove @ symbol if present
				handle = handle.replace(/^@/, '');

				// Platform-specific validation rules
				const validationRules = {
					instagram: {
						pattern: /^[a-zA-Z0-9._]{1,30}$/,
						messageKey: 'instagramHandleError'
					},
					youtube: {
						pattern: /^[a-zA-Z0-9_-]{3,30}$/,
						messageKey: 'youtubeHandleError'
					},
					tiktok: {
						pattern: /^[a-zA-Z0-9._]{2,24}$/,
						messageKey: 'tiktokHandleError'
					},
					twitter: {
						pattern: /^[a-zA-Z0-9_]{1,15}$/,
						messageKey: 'twitterHandleError'
					},
					linkedin: {
						pattern: /^[a-zA-Z0-9-]{3,100}$/,
						messageKey: 'linkedinHandleError'
					}
				};

				const rule = validationRules[platform];
				if (!rule) {
					return { isValid: true, message: '' };
				}

				// Check pattern
				if (!rule.pattern.test(handle)) {
					return { isValid: false, message: getLocalizedMessage(rule.messageKey) };
				}

				// Check for common invalid patterns
				const invalidPatterns = [
					/^[0-9]+$/, // Only numbers
					/^[._-]+$/, // Only special characters
					/^[._-]/,   // Starts with special character
					/[._-]$/,   // Ends with special character
					/\.{2,}/,   // Multiple consecutive periods
					/_{2,}/,    // Multiple consecutive underscores
					/-{2,}/,    // Multiple consecutive hyphens
				];

				for (const pattern of invalidPatterns) {
					if (pattern.test(handle)) {
						return { isValid: false, message: getLocalizedMessage('invalidHandleFormat') };
					}
				}

				return { isValid: true, message: '' };
			}

			function getLocalizedMessage(messageKey) {
				// Check if localized messages are available
				if (typeof thinkBlocksL10n !== 'undefined' && thinkBlocksL10n[messageKey]) {
					return thinkBlocksL10n[messageKey];
				}

				// Fallback messages if localization is not available
				const fallbackMessages = {
					'socialPlatformRequired': 'Please select a social media platform.',
					'socialHandleRequired': 'This field is required.',
					'instagramHandleError': 'Instagram handle must be 1-30 characters and can only contain letters, numbers, periods, and underscores.',
					'youtubeHandleError': 'YouTube handle must be 3-30 characters and can only contain letters, numbers, hyphens, and underscores.',
					'tiktokHandleError': 'TikTok handle must be 2-24 characters and can only contain letters, numbers, periods, and underscores.',
					'twitterHandleError': 'Twitter/X handle must be 1-15 characters and can only contain letters, numbers, and underscores.',
					'linkedinHandleError': 'LinkedIn handle must be 3-100 characters and can only contain letters, numbers, and hyphens.',
					'invalidHandleFormat': 'Invalid handle format. Please check your social media handle.'
				};

				console.log('Using fallback validation messages - localized messages not available.');
				return fallbackMessages[messageKey] || 'Validation error occurred.';
			}

			function clearErrors(formElement) {
				formElement.find('.form-error').remove();
				formElement.find('.input-error').removeClass('input-error');
			}

			function displayErrors(errors, formElement, shouldScroll = true) {
				const allErrors = Object.keys(errors).join(', ');
				pushFormInteraction({
					event_type: "form_error",
					message: "required field empty on submission",
					field_name: allErrors
				})
				for (const [field, message] of Object.entries(errors)) {
					if (field === 'legalConsentOptions') {
						const consentInput = formElement.find('#consent');
						const consentLabel = consentInput.closest('label');

						if (consentInput.length) {
							consentInput.addClass('input-error');
							consentLabel.find('.form-error').remove();
							consentLabel.append(`<p class="form-error">${message}</p>`);
						}
						continue;
					}

					if (field === 'submission') {
						formElement.prepend(`<p class="form-error">${message}</p>`);
						continue;
					}

					const inputField = formElement.find(`[name="${field}"]`);
					if (inputField.length) {
						// Special handling for social media handle fields
						if (field.endsWith('_handle')) {
							const fieldContainer = inputField.closest('.social-handle-field');
							if (fieldContainer.length) {
								// Remove existing error messages
								fieldContainer.parent().find('.form-error').remove();
								// Add error class to input
								inputField.addClass('input-error');
								// Insert error message after the field container (outside the wrapper)
								fieldContainer.after(`<p class="form-error">${message}</p>`);
							} else {
								// Fallback to default behavior if field container not found
								inputField.addClass('input-error').after(`<p class="form-error">${message}</p>`);
							}
						} else {
							// Default behavior for other fields
							inputField.addClass('input-error').after(`<p class="form-error">${message}</p>`);
						}
					} else {
						console.warn(`No input field found for: ${field}`);
					}
				}

				// Only scroll if shouldScroll is true
				if (shouldScroll) {
					const firstError = formElement.find('.form-error').first();
					if (firstError.length) {
						$('html, body').animate({
							scrollTop: firstError.offset().top - 20,
						}, 500);
					}
				}
			}

			function clearMessages(formElement) {
				formElement.find('.error-message, .form-error').remove();
			}
		});
	})(jQuery);
});

