/**
 * Social Media Platform Selector
 * Handles the social media platform selection and dependent handle field visibility
 * Only initializes when forms with social media containers are present
 */

document.addEventListener('DOMContentLoaded', function () {
	// Check if there are any forms with social media containers
	const formElements = document.querySelectorAll('form[data-form-id]');
	if (formElements.length === 0) {
		return; // No forms on page, exit early
	}

	// Check if any of these forms have social media containers
	const socialMediaContainers = document.querySelectorAll('.social-media-container');
	if (socialMediaContainers.length === 0) {
		return; // No social media containers on page, exit early
	}

	class SocialMediaSelector {
		constructor(container) {
			if (!container || !container.querySelector) {
				console.warn('SocialMediaSelector: Invalid container provided');
				return;
			}

			this.container = container;
			this.platformSelector = null;
			this.handleFields = {};
			this.currentPlatform = null;

			this.init();
		}

		init() {
			try {
				this.setupReferences();
				this.bindEvents();
				this.loadSavedData();
			} catch (error) {
				console.error('SocialMediaSelector initialization error:', error);
			}
		}

		setupReferences() {
			// Find existing elements (rendered server-side)
			this.platformSelector = this.container.querySelector('.social-platform-selector');
			
			// Find existing handle fields
			this.container.querySelectorAll('.social-handle-field').forEach(element => {
				const platform = element.dataset.platform;
				if (platform) {
					this.handleFields[platform] = element;
				}
			});
		}

		bindEvents() {
			// Bind platform selection events
			this.container.querySelectorAll('.social-platform-radio').forEach(radio => {
				radio.addEventListener('change', (e) => {
					this.handlePlatformSelection(e.target.value);
				});
			});

			// Bind handle input events
			this.container.querySelectorAll('.social-handle-input').forEach(input => {
				input.addEventListener('input', (e) => {
					this.handleInputChange(e.target);
				});
			});
		}

		handlePlatformSelection(platform) {
			// Clear all error messages when switching platforms
			this.clearErrors();

			// Hide all handle fields
			Object.values(this.handleFields).forEach(field => {
				if (field) {
					field.style.display = 'none';
				}
			});

			// Show/hide disclaimer based on platform selection
			const disclaimer = this.container.querySelector('.social-media-disclaimer');
			if (disclaimer) {
				if (platform === 'other') {
					disclaimer.style.display = 'none';
				} else {
					disclaimer.style.display = 'block';
				}
			}

			// Show selected platform's handle field (except for 'other')
			if (platform !== 'other' && this.handleFields[platform]) {
				this.handleFields[platform].style.display = 'block';
				// Focus on the input field
				const input = this.handleFields[platform].querySelector('.social-handle-input');
				if (input) {
					setTimeout(() => input.focus(), 100);
				}
			}

			// Update the hidden field for HubSpot with proper HubSpot dropdown values
			const platformField = this.container.querySelector('#primary_social_platform');
			if (platformField) {
				// Map internal platform keys to HubSpot dropdown values
				const platformMapping = {
					'linkedin': 'LinkedIn',
					'instagram': 'Instagram',
					'youtube': 'YouTube',
					'twitter': 'Twitter/X',
					'tiktok': 'TikTok',
					'other': 'Other'
				};
				platformField.value = platformMapping[platform] || platform;
			}

			this.currentPlatform = platform;
			this.saveData();
		}

		handleInputChange(input) {
			// Remove @ prefix if user types it
			let value = input.value;
			if (value.startsWith('@')) {
				value = value.substring(1);
				input.value = value;
			}
			this.saveData();
		}

		saveData() {
			const data = {
				platform: this.currentPlatform,
				handles: {}
			};

			Object.entries(this.handleFields).forEach(([platform, field]) => {
				if (field) {
					const input = field.querySelector('.social-handle-input');
					if (input) {
						data.handles[platform] = input.value;
					}
				}
			});

			// Save to localStorage for persistence
			localStorage.setItem('socialMediaSelector', JSON.stringify(data));
		}

		loadSavedData() {
			try {
				const savedData = localStorage.getItem('socialMediaSelector');
				if (savedData) {
					const data = JSON.parse(savedData);
					
					// Restore platform selection
					if (data.platform) {
						const radio = this.container.querySelector(`input[value="${data.platform}"]`);
						if (radio) {
							radio.checked = true;
							this.handlePlatformSelection(data.platform);
						}
					}

					// Restore handle values
					if (data.handles) {
						Object.entries(data.handles).forEach(([platform, value]) => {
							if (this.handleFields[platform]) {
								const input = this.handleFields[platform].querySelector('.social-handle-input');
								if (input) {
									input.value = value;
								}
							}
						});
					}
				}
			} catch (error) {
				console.error('Error loading saved social media data:', error);
			}
		}



		// Public method to get form data
		getFormData() {
			const data = {};

			if (this.currentPlatform && this.currentPlatform !== 'other') {
				const platform = this.getPlatformByKey(this.currentPlatform);
				if (platform && platform.handleField && this.handleFields[this.currentPlatform]) {
					const input = this.handleFields[this.currentPlatform].querySelector('.social-handle-input');
					if (input) {
						data[platform.handleField] = input.value;
					}
				}
			}

			return data;
		}

		// Public method to validate - integrates with main form validation
		validate() {
			this.clearErrors();
			
			if (this.currentPlatform && this.currentPlatform !== 'other') {
				const field = this.handleFields[this.currentPlatform];
				if (field) {
					const input = field.querySelector('.social-handle-input');
					if (input) {
						const value = input.value.trim();
						
						// Check if empty
						if (!value) {
							this.showFormError(input, this.getLocalizedMessage('socialHandleRequired'));
							return false;
						}
						
						// Validate handle format
						const validationResult = this.validateHandleFormat(value, this.currentPlatform);
						if (!validationResult.isValid) {
							this.showFormError(input, validationResult.message);
							return false;
						}
					}
				}
			}
			return true;
		}

		// Validate handle format based on platform
		validateHandleFormat(handle, platform) {
			// Remove @ symbol if present
			handle = handle.replace(/^@/, '');
			
			// Platform-specific validation rules with localized messages
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
				return { isValid: false, message: this.getLocalizedMessage(rule.messageKey) };
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
					return { isValid: false, message: this.getLocalizedMessage('invalidHandleFormat') };
				}
			}

			return { isValid: true, message: '' };
		}

		// Show form error message - integrates with main form validation
		showFormError(input, message) {
			// Find the social-handle-field container (parent of the wrapper)
			const fieldContainer = input.closest('.social-handle-field');
			if (!fieldContainer) return;
			
			// Remove existing error messages
			const existingError = fieldContainer.parentElement.querySelector('.form-error');
			if (existingError) {
				existingError.remove();
			}

			// Add error class to input
			input.classList.add('input-error');

			// Create and add error message using the same pattern as main form
			const errorElement = document.createElement('p');
			errorElement.className = 'form-error';
			errorElement.textContent = message;
			
			// Insert after the social-handle-field (outside the field container)
			fieldContainer.parentElement.appendChild(errorElement);
		}

		// Public method to clear errors - integrates with main form validation
		clearErrors() {
			this.container.querySelectorAll('.social-handle-field').forEach(field => {
				field.classList.remove('error');
				// Remove custom error messages
				const errorMessage = field.querySelector('.error-message');
				if (errorMessage) {
					errorMessage.remove();
				}
				// Remove form error messages (from main form validation) - now outside the field container
				const formError = field.parentElement.querySelector('.form-error');
				if (formError) {
					formError.remove();
				}
				// Remove input-error class from inputs
				const input = field.querySelector('.social-handle-input');
				if (input) {
					input.classList.remove('input-error');
				}
			});
		}

		// Helper method to get platform info
		getPlatformByKey(key) {
			const SOCIAL_PLATFORMS = {
				instagram: { name: 'Instagram', handleField: 'instagram_handle' },
				youtube: { name: 'YouTube', handleField: 'youtube_handle' },
				tiktok: { name: 'TikTok', handleField: 'tiktok_handle' },
				twitter: { name: 'Twitter/X', handleField: 'twitter_handle' },
				linkedin: { name: 'LinkedIn', handleField: 'linkedin_handle' },
				other: { name: 'Other', handleField: null }
			};
			return SOCIAL_PLATFORMS[key];
		}

		// Get localized message with fallback
		getLocalizedMessage(messageKey) {
			// Check if localized messages are available
			if (typeof window.thinkBlocksL10n !== 'undefined' && window.thinkBlocksL10n[messageKey]) {
				return window.thinkBlocksL10n[messageKey];
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
	}

	// Initialize social media selectors
	socialMediaContainers.forEach(container => {
		try {
			new SocialMediaSelector(container);
		} catch (error) {
			console.error('SocialMediaSelector: Error initializing container:', error);
		}
	});

	// Export for use in other modules
	if (typeof window !== 'undefined') {
		window.SocialMediaSelector = SocialMediaSelector;
	}
});

