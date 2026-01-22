(() => {
	const forms = document.querySelectorAll('.bb-hubspot-forms-form');
	if (!forms.length) {
		return;
	}

	const getCaptchaToken = async () => {
		const provider = window.bbHubspotFormsConfig?.captchaProvider;
		const siteKey = window.bbHubspotFormsConfig?.captchaSiteKey;
		const action = window.bbHubspotFormsConfig?.captchaAction || 'hubspot_form_submit';

		if (provider !== 'recaptcha_v3' || !siteKey || !window.grecaptcha?.ready) {
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

	const submitForm = async (form) => {
		const messageEl = form.querySelector('.bb-hubspot-forms-form__message');
		try {
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

			const payload = {
				formId: formId,
				token: token,
				schemaVersion: schemaVersion,
				fields,
				context: {
					pageUri: window.location.href,
					pageName: document.title,
				},
				redirectUrl: redirectUrl,
				appendEmailToRedirect: appendEmail,
				captchaToken: captcha.token,
				captchaAction: captcha.action,
			};

			messageEl.textContent = 'Submitting...';

			const response = await fetch(window.bbHubspotFormsConfig?.restUrl || '', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload),
			});

			const data = await response.json().catch(() => ({}));
			if (!response.ok || !data.success) {
				messageEl.textContent = data?.errors?.submission || 'Submission failed.';
				return;
			}

			messageEl.textContent = data.message || 'Submitted successfully.';
			if (redirectUrl) {
				let url = redirectUrl;
				if (appendEmail && fields.email) {
					const separator = url.includes('?') ? '&' : '?';
					url = `${url}${separator}email=${encodeURIComponent(fields.email)}`;
				}
				window.location.href = url;
			}
		} catch (error) {
			messageEl.textContent = 'Unable to submit form. Please try again.';
			if (window.console && console.error) {
				console.error('BB HubSpot Forms:', error);
			}
		}
	};

	forms.forEach((form) => {
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			submitForm(form);
		});
	});
})();

