(() => {
	const forms = document.querySelectorAll('.bb-hubspot-forms-form');
	if (!forms.length) {
		return;
	}

	/**
	 * EU/EEA/UK country codes for GDPR consent.
	 */
	const EU_COUNTRIES = [
		'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU',
		'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
		'IS', 'LI', 'NO', 'GB', 'CH' // EEA + UK + Switzerland
	];

	/**
	 * Check if country code is EU/UK.
	 */
	const isEUCountry = (countryCode) => {
		return countryCode && EU_COUNTRIES.includes(countryCode.toUpperCase());
	};

	/**
	 * Setup consent visibility based on mode and country.
	 */
	const setupConsentVisibility = (form) => {
		const consentBlock = form.querySelector('.bb-hubspot-forms-form__consent');
		if (!consentBlock) return;

		const consentMode = form.getAttribute('data-consent-mode') || 'always';
		const userCountry = form.getAttribute('data-user-country') || '';
		const consentCheckbox = consentBlock.querySelector('input[name="consent_to_process"]');

		if (consentMode === 'eu_only') {
			// Only hide if we have a confirmed non-EU country.
			if (userCountry && !isEUCountry(userCountry)) {
				consentBlock.style.display = 'none';
				// Auto-check consent for non-EU users.
				if (consentCheckbox) {
					consentCheckbox.checked = true;
				}
			}
			// If no country detected, keep consent visible (safe default).
		}
		// For 'always' mode, consent is always visible (default).
		// For 'disabled' mode, consent block won't be rendered at all.
	};

	/**
	 * Validate consent checkbox if visible.
	 */
	const validateConsent = (form) => {
		const consentBlock = form.querySelector('.bb-hubspot-forms-form__consent');
		if (!consentBlock || consentBlock.style.display === 'none') {
			return null; // No validation needed.
		}

		const consentCheckbox = consentBlock.querySelector('input[name="consent_to_process"]');
		if (consentCheckbox && !consentCheckbox.checked) {
			return consentCheckbox;
		}

		return null;
	};

	/**
	 * Get consent values from form.
	 */
	const getConsentValues = (form) => {
		const consentBlock = form.querySelector('.bb-hubspot-forms-form__consent');
		const consentMode = form.getAttribute('data-consent-mode') || 'always';

		if (consentMode === 'disabled' || !consentBlock) {
			return { consentToProcess: false, marketingConsent: false };
		}

		const consentCheckbox = consentBlock.querySelector('input[name="consent_to_process"]');
		const marketingCheckbox = consentBlock.querySelector('input[name="marketing_consent"]');

		return {
			consentToProcess: consentCheckbox ? consentCheckbox.checked : false,
			marketingConsent: marketingCheckbox ? marketingCheckbox.checked : false,
		};
	};

	/**
	 * Get CAPTCHA token if configured.
	 */
	const getCaptchaToken = async () => {
		const provider = window.bbHubspotFormsConfig?.captchaProvider;
		const siteKey = window.bbHubspotFormsConfig?.captchaSiteKey;
		const action = window.bbHubspotFormsConfig?.captchaAction || 'hubspot_form_submit';

		if (provider !== 'recaptcha_v3' || !siteKey) {
			return { token: '', action: '' };
		}

		if (!window.grecaptcha) {
			return { token: '', action: '' };
		}

		try {
			return await new Promise((resolve) => {
				window.grecaptcha.ready(async () => {
					try {
						const token = await window.grecaptcha.execute(siteKey, { action });
						resolve({ token, action });
					} catch (error) {
						resolve({ token: '', action: '' });
					}
				});
			});
		} catch (error) {
			return { token: '', action: '' };
		}
	};

	/**
	 * Clear all field errors.
	 */
	const clearFieldErrors = (form) => {
		form.querySelectorAll('.bb-hubspot-forms-form__field').forEach((field) => {
			field.classList.remove('bb-hubspot-forms-form__field--error');
			const errorEl = field.querySelector('.bb-hubspot-forms-form__field-error');
			if (errorEl) {
				errorEl.remove();
			}
		});
	};

	/**
	 * Show error on a specific field.
	 */
	const showFieldError = (input, message) => {
		const fieldWrapper = input.closest('.bb-hubspot-forms-form__field');
		if (!fieldWrapper) return;

		fieldWrapper.classList.add('bb-hubspot-forms-form__field--error');

		// Remove existing error if present.
		const existingError = fieldWrapper.querySelector('.bb-hubspot-forms-form__field-error');
		if (existingError) {
			existingError.remove();
		}

		// Add new error message.
		const errorEl = document.createElement('span');
		errorEl.className = 'bb-hubspot-forms-form__field-error';
		errorEl.setAttribute('role', 'alert');
		errorEl.textContent = message;
		fieldWrapper.appendChild(errorEl);
	};

	/**
	 * Validate required fields.
	 * Returns first invalid input or null if all valid.
	 */
	const validateRequired = (form) => {
		const requiredInputs = form.querySelectorAll('[required]');
		let firstInvalid = null;

		requiredInputs.forEach((input) => {
			const value = input.type === 'checkbox' ? input.checked : input.value.trim();
			if (!value) {
				const label = input.closest('.bb-hubspot-forms-form__field')?.querySelector('.bb-hubspot-forms-form__label')?.textContent || 'This field';
				showFieldError(input, `${label} is required.`);
				if (!firstInvalid) {
					firstInvalid = input;
				}
			}
		});

		return firstInvalid;
	};

	/**
	 * Validate email format.
	 */
	const validateEmail = (form) => {
		const emailField = form.querySelector('input[type="email"]');
		if (!emailField || !emailField.value.trim()) {
			return null;
		}

		const emailValue = emailField.value.trim();
		const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue);
		if (!emailOk) {
			showFieldError(emailField, 'Please enter a valid email address.');
			return emailField;
		}

		return null;
	};

	/**
	 * Set form message with appropriate styling.
	 */
	const setFormMessage = (form, message, type = 'info') => {
		const messageEl = form.querySelector('.bb-hubspot-forms-form__message');
		if (!messageEl) return;

		// Remove existing type classes.
		messageEl.classList.remove(
			'bb-hubspot-forms-form__message--success',
			'bb-hubspot-forms-form__message--error',
			'bb-hubspot-forms-form__message--loading'
		);

		// Add new type class.
		if (type) {
			messageEl.classList.add(`bb-hubspot-forms-form__message--${type}`);
		}

		messageEl.textContent = message;
	};

	/**
	 * Hide form fields after successful submission.
	 */
	const hideFormFields = (form) => {
		// Hide all fields, consent block, and submit button.
		form.querySelectorAll('.bb-hubspot-forms-form__field, .bb-hubspot-forms-form__consent, button[type="submit"]').forEach((el) => {
			el.style.display = 'none';
		});
		// Add submitted class for additional styling.
		form.classList.add('bb-hubspot-forms-form--submitted');
	};

	/**
	 * Set loading state on form.
	 */
	const setLoading = (form, isLoading) => {
		const submitBtn = form.querySelector('button[type="submit"]');
		
		if (isLoading) {
			form.classList.add('bb-hubspot-forms-form--loading');
			if (submitBtn) {
				submitBtn.disabled = true;
				submitBtn.setAttribute('data-original-text', submitBtn.textContent);
				submitBtn.textContent = 'Submitting…';
			}
			setFormMessage(form, 'Submitting your form…', 'loading');
		} else {
			form.classList.remove('bb-hubspot-forms-form--loading');
			if (submitBtn) {
				submitBtn.disabled = false;
				const originalText = submitBtn.getAttribute('data-original-text');
				if (originalText) {
					submitBtn.textContent = originalText;
				}
			}
		}
	};

	/**
	 * Handle form submission.
	 */
	const submitForm = async (form) => {
		try {
			// Clear previous errors.
			clearFieldErrors(form);
			setFormMessage(form, '', '');

			// Validate required fields.
			const invalidRequired = validateRequired(form);
			if (invalidRequired) {
				setFormMessage(form, 'Please complete all required fields.', 'error');
				invalidRequired.focus();
				return;
			}

			// Validate email format.
			const invalidEmail = validateEmail(form);
			if (invalidEmail) {
				setFormMessage(form, 'Please correct the errors above.', 'error');
				invalidEmail.focus();
				return;
			}

			// Validate consent checkbox if visible.
			const invalidConsent = validateConsent(form);
			if (invalidConsent) {
				setFormMessage(form, 'Please confirm consent before submitting.', 'error');
				invalidConsent.focus();
				return;
			}

			// Show loading state.
			setLoading(form, true);

			const formId = form.getAttribute('data-form-id');
			const token = form.getAttribute('data-token');
			const schemaVersion = form.getAttribute('data-schema-version');
			const redirectUrl = form.getAttribute('data-redirect-url');
			const appendEmail = form.getAttribute('data-append-email') === '1';
			const formData = new FormData(form);
			const fields = {};

			formData.forEach((value, key) => {
				fields[key] = value;
			});

			const captcha = await getCaptchaToken();
			const consent = getConsentValues(form);

			const payload = {
				formId: formId,
				token: token,
				schemaVersion: schemaVersion,
				fields,
				context: {
					pageUri: window.location.href,
					pageName: document.title,
				},
				consent: consent,
				redirectUrl: redirectUrl,
				appendEmailToRedirect: appendEmail,
				captchaToken: captcha.token,
				captchaAction: captcha.action,
			};

		const response = await fetch(window.bbHubspotFormsConfig?.restUrl || '', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload),
		});

		const data = await response.json().catch(() => ({}));
		
		setLoading(form, false);

		if (!response.ok || !data.success) {
			// Extract error message from various possible locations in response.
			let errorMessage = 'Submission failed. Please try again.';
			if (data?.errors?.submission) {
				errorMessage = data.errors.submission;
			} else if (data?.message) {
				errorMessage = data.message;
			} else if (data?.errors && typeof data.errors === 'object') {
				// Get first field error if present.
				const firstError = Object.values(data.errors)[0];
				if (firstError) {
					errorMessage = firstError;
				}
			}
			setFormMessage(form, errorMessage, 'error');
			return;
		}

			// Success!
			const successMessage = data.message || 'Thank you! Your form has been submitted successfully.';
			setFormMessage(form, successMessage, 'success');
			form.reset();

			// Hide form fields and show only success message.
			hideFormFields(form);

			// Handle redirect if configured.
			if (redirectUrl) {
				let url = redirectUrl;
				if (appendEmail && fields.email) {
					const separator = url.includes('?') ? '&' : '?';
					url = `${url}${separator}email=${encodeURIComponent(fields.email)}`;
				}
				setTimeout(() => {
					window.location.href = url;
				}, 1000);
			}
		} catch (error) {
			setLoading(form, false);
			setFormMessage(form, 'Unable to submit form. Please check your connection and try again.', 'error');
			if (window.console && console.error) {
				console.error('BB HubSpot Forms:', error);
			}
		}
	};

	/**
	 * Clear field error on input.
	 */
	const setupFieldListeners = (form) => {
		const inputs = form.querySelectorAll('input, textarea, select');
		inputs.forEach((input) => {
			input.addEventListener('input', () => {
				const fieldWrapper = input.closest('.bb-hubspot-forms-form__field');
				if (fieldWrapper) {
					fieldWrapper.classList.remove('bb-hubspot-forms-form__field--error');
					const errorEl = fieldWrapper.querySelector('.bb-hubspot-forms-form__field-error');
					if (errorEl) {
						errorEl.remove();
					}
				}
			});
		});
	};

	// Initialize all forms.
	forms.forEach((form) => {
		setupConsentVisibility(form);
		setupFieldListeners(form);
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			submitForm(form);
		});
	});
})();
