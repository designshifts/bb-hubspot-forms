/**
 * Solo Detection & Conditional Reveal System
 * 
 * Detects when users select solo options in the Number of employees field
 * and conditionally reveals/hides form elements accordingly.
 * 
 */

(function () {
	'use strict';

	// Configuration
	const CONFIG = {
		// Solo option values to detect
		soloValues: [
			'Just me (full time)',
			'Just me (part time)'
		],

		// Selectors for elements to hide/show - UPDATED to match edit.js
		selectors: {
			employeesField: 'select[name="employee_range__c"], input[name="employee_range__c"]',
			useCaseDropdown: 'select[name="probable_use_case"], input[name="probable_use_case"]',
			useCaseWrapper: 'select[name="probable_use_case"], input[name="probable_use_case"]',
			submitButton: 'button[type="submit"], input[type="submit"]',
			selfServeBox: '.self-serve-box',
			liveRegion: '.solo-detect-live-region'
		},

		// Animation settings
		animation: {
			duration: 200, // ms
			easing: 'ease-in-out'
		}
	};

	// State management
	let state = {
		isInitialized: false,
		isSoloSelected: false,
		hiddenElements: new Map(),
		originalValues: new Map(),
		selfServeBox: null,
		liveRegion: null,
		employeesField: null,
		flipCount: 0,
		lastFlipTime: null
	};

	/**
	 * Initialize the solo detection system
	 */
	function init() {
		if (state.isInitialized) return;

		try {
			// Find the employees field
			state.employeesField = document.querySelector(CONFIG.selectors.employeesField);
			if (!state.employeesField) {
				console.warn('[Solo Detect] Employees field not found, skipping initialization');
				return;
			}

			// Check if solo detection is enabled for this form
			const form = state.employeesField.closest('form');
			if (!form) {
				console.warn('[Solo Detect] Form not found, skipping initialization');
				return;
			}

			const enableSoloDetect = form.getAttribute('data-enable-solo-detect');
			if (enableSoloDetect !== 'true') {
				console.log('[Solo Detect] Solo detection is disabled for this form, skipping initialization');
				return;
			}

			// Load flip count from localStorage
			loadFlipCount();

			// Create live region for accessibility
			createLiveRegion();

			// Create self-serve box
			createSelfServeBox();

			// Set up event listeners
			setupEventListeners();

			// Check initial state (for prefill scenarios)
			checkInitialState();

			state.isInitialized = true;
			console.log('[Solo Detect] Initialized successfully');

		} catch (error) {
			console.error('[Solo Detect] Initialization failed:', error);
		}
	}

	/**
	 * Create live region for screen reader announcements
	 */
	function createLiveRegion() {
		state.liveRegion = document.createElement('div');
		state.liveRegion.className = 'solo-detect-live-region';
		state.liveRegion.setAttribute('aria-live', 'polite');
		state.liveRegion.setAttribute('aria-atomic', 'true');
		state.liveRegion.style.cssText = `
            position: absolute;
            left: -10000px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        `;
		document.body.appendChild(state.liveRegion);
	}

	/**
	 * Create the self-serve box
	 */
	function createSelfServeBox() {
		state.selfServeBox = document.createElement('div');
		state.selfServeBox.className = 'self-serve-box';
		state.selfServeBox.setAttribute('role', 'region');
		state.selfServeBox.setAttribute('aria-label', 'Self-serve options');

		updateSelfServeBoxContent();
	}

	/**
	 * Update self-serve box content based on flip count
	 */
	function updateSelfServeBoxContent() {
		const content = getSelfServeBoxContent();
		state.selfServeBox.innerHTML = `
            <h3 class="self-serve-box__title wp-block-heading has-instrument-sans-font-family">
                ${content.title}
            </h3>
            <p class="self-serve-box__description">
                ${content.description}
            </p>
			<div class="wp-block-buttons is-horizontal is-content-justification-left is-layout-flex wp-block-buttons-is-layout-flex">
				<div class="wp-block-button is-style-fill has-arrow arrow-right" style="font-style:normal;font-weight:600">
					<button type="button" class="self-serve-box__cta buttonAction_clicked-SSblock-CTA wp-block-button__link has-beacon-color has-rooted-background-color has-text-color has-background has-link-color wp-element-button" style="font-style:normal;font-weight:600">${content.ctaText}</button>
				</div>
			</div>
        `;
	}

	/**
	 * Get content for self-serve box based on flip count
	 */
	function getSelfServeBoxContent() {
		if (state.flipCount >= 4) {
			// Day-aware variant (4+ flips)
			return getDayAwareContent();
		} else if (state.flipCount >= 3) {
			// Three-flip variant
			return {
				title: 'Still deciding? 🤔',
				description: 'Thinkific Plus is the best fit if you:<br><br>• Manage a growing team<br>• Need advanced integrations or custom solutions<br>• Want dedicated account and success support<br><br>If that doesn\'t sound like you, 👉 our self‑serve plans may be a better fit—built for solo creators who want to launch fast and scale at their own pace.',
				ctaText: 'Start free trial'
			};
		} else {
			// Default content
			return {
				title: 'Jump-start your Thinkific journey',
				description: 'Skip the wait and launch your course business today with our Creator plans—no sales call needed.',
				ctaText: 'Start free trial'
			};
		}
	}

	/**
	 * Get day-aware content for 4+ flips
	 */
	function getDayAwareContent() {
		const dayMessages = {
			0: 'Plan ahead Sunday 🌅 — start fresh this week with the right fit.', // Sunday
			1: 'Mondays are tough 🤯 — let\'s keep this simple.', // Monday
			2: 'Tuesdays call for clarity 🔍 — we can help you choose.', // Tuesday
			3: 'Midweek check‑in 🗓️ — still weighing your options?', // Wednesday
			4: 'Almost Friday! ⏳ Let\'s find your best fit today.', // Thursday
			5: 'Weekend\'s coming 🎉 — get started before you log off.', // Friday
			6: 'Weekend warrior 💪 — ready to launch your plan?' // Saturday
		};

		const today = new Date().getDay();
		const dayMessage = dayMessages[today] || dayMessages[1]; // Default to Monday

		return {
			title: dayMessage,
			description: 'Thinkific Plus is the best fit if you:<br><br>• Manage a growing team<br>• Need advanced integrations or custom solutions<br>• Want dedicated account and success support<br><br>If that doesn\'t sound like you, 👉 our self‑serve plans may be a better fit—built for solo creators who want to launch fast and scale at their own pace.',
			ctaText: 'Start free trial'
		};
	}

	/**
	 * Set up event listeners
	 */
	function setupEventListeners() {
		// Listen for changes to the employees field
		state.employeesField.addEventListener('change', handleEmployeesChange);
		state.employeesField.addEventListener('input', handleEmployeesChange);

		// Listen for form submission to prevent it when solo is selected
		const form = state.employeesField.closest('form');
		if (form) {
			form.addEventListener('submit', handleFormSubmit);
		}

		// Listen for Enter key on form to prevent submission when solo is selected
		document.addEventListener('keydown', handleKeydown);

		// Listen for CTA button clicks
		if (state.selfServeBox) {
			state.selfServeBox.addEventListener('click', handleCTAClick);
		}
	}

	/**
	 * Handle changes to the employees field
	 */
	function handleEmployeesChange(event) {
		const value = event.target.value;
		const isSolo = CONFIG.soloValues.includes(value);

		if (isSolo && !state.isSoloSelected) {
			// Increment flip count and track analytics
			incrementFlipCount();

			// Track the appropriate event based on flip count
			if (state.flipCount >= 4) {
				trackAnalytics('SSblock_viewed_4+');
			} else if (state.flipCount >= 3) {
				trackAnalytics('SSblock_viewed_3');
			} else {
				trackAnalytics('SSblock_viewed');
			}

			showSelfServeBox();
			hideFormElements();
			state.isSoloSelected = true;
		} else if (!isSolo && state.isSoloSelected) {
			hideSelfServeBox();
			showFormElements();
			state.isSoloSelected = false;
		}
	}

	/**
	 * Increment flip count and persist to localStorage
	 */
	function incrementFlipCount() {
		state.flipCount++;
		state.lastFlipTime = Date.now();

		// Persist to localStorage
		const flipData = {
			count: state.flipCount,
			lastFlipTime: state.lastFlipTime,
			formId: getFormId()
		};
		localStorage.setItem('solo_detect_flips', JSON.stringify(flipData));
	}

	/**
	 * Load flip count from localStorage
	 */
	function loadFlipCount() {
		try {
			const stored = localStorage.getItem('solo_detect_flips');
			if (stored) {
				const flipData = JSON.parse(stored);
				const formId = getFormId();

				// Only use stored data if it's for the same form
				if (flipData.formId === formId) {
					state.flipCount = flipData.count || 0;
					state.lastFlipTime = flipData.lastFlipTime || null;
				}
			}
		} catch (error) {
			console.warn('[Solo Detect] Failed to load flip count:', error);
		}
	}

	/**
	 * Get form ID for tracking
	 */
	function getFormId() {
		const form = state.employeesField?.closest('form');
		return form?.getAttribute('data-form-id') || 'unknown';
	}

	/**
	 * Check initial state for prefill scenarios
	 */
	function checkInitialState() {
		const value = state.employeesField.value;
		const isSolo = CONFIG.soloValues.includes(value);

		if (isSolo) {
			// Track analytics for prefill reveal
			if (state.flipCount >= 4) {
				trackAnalytics('SSblock_viewed_4+');
			} else if (state.flipCount >= 3) {
				trackAnalytics('SSblock_viewed_3');
			} else {
				trackAnalytics('SSblock_viewed');
			}

			showSelfServeBox();
			hideFormElements();
			state.isSoloSelected = true;
		}
	}

	/**
	 * Show the self-serve box
	 */
	function showSelfServeBox() {
		if (!state.selfServeBox || !state.employeesField) return;

		// Update content based on current flip count
		updateSelfServeBoxContent();

		// Insert the box after the employees field
		const employeesFieldContainer = state.employeesField.closest('.wp-block-group, .hs-form-field, .form-field, .field');
		if (employeesFieldContainer) {
			employeesFieldContainer.insertAdjacentElement('afterend', state.selfServeBox);
		} else {
			state.employeesField.parentNode.insertBefore(state.selfServeBox, state.employeesField.nextSibling);
		}

		// Animate in
		requestAnimationFrame(() => {
			state.selfServeBox.style.display = 'block';

			requestAnimationFrame(() => {
				state.selfServeBox.classList.add('show');
			});
		});

		// Announce to screen readers
		announce('Self-serve options are now available');
	}

	/**
	 * Hide the self-serve box
	 */
	function hideSelfServeBox() {
		if (!state.selfServeBox) return;

		// Animate out
		state.selfServeBox.classList.remove('show');

		setTimeout(() => {
			state.selfServeBox.style.display = 'none';
		}, CONFIG.animation.duration);
	}

	/**
	 * Hide form elements (use-case dropdown wrapper and submit button)
	 */
	function hideFormElements() {
		// Find the closest wp-block-group parent for each probable_use_case field
		const useCaseFields = document.querySelectorAll(CONFIG.selectors.useCaseWrapper);
		const useCaseWrappers = Array.from(useCaseFields).map(field =>
			field.closest('.wp-block-group')
		).filter(wrapper => wrapper !== null);

		const elementsToHide = [
			...useCaseWrappers,
			...document.querySelectorAll(CONFIG.selectors.submitButton)
		];

		elementsToHide.forEach(element => {
			if (element.offsetParent !== null) { // Element is visible
				// Store original state
				state.hiddenElements.set(element, {
					display: element.style.display,
					visibility: element.style.visibility,
					tabIndex: element.getAttribute('tabindex'),
					ariaHidden: element.getAttribute('aria-hidden'),
					ariaRequired: element.getAttribute('aria-required'),
					required: element.hasAttribute('required')
				});

				// Store original value if it's a form field
				if (element.value !== undefined) {
					state.originalValues.set(element, element.value);
				}

				// Hide element and remove from accessibility tree
				element.style.display = 'none';
				element.style.visibility = 'hidden';
				element.setAttribute('tabindex', '-1');
				element.setAttribute('aria-hidden', 'true');
				element.removeAttribute('required');
				element.removeAttribute('aria-required');

				// Handle focus management
				if (document.activeElement === element) {
					const ctaButton = state.selfServeBox?.querySelector('.buttonAction_clicked-SSblock-CTA');
					if (ctaButton) {
						ctaButton.focus();
						// Announce focus change to screen readers
						announce('Focus moved to self-serve options');
					}
				}
			}
		});
	}

	/**
	 * Show form elements (restore use-case dropdown and submit button)
	 */
	function showFormElements() {
		state.hiddenElements.forEach((originalState, element) => {
			// Restore original styles
			element.style.display = originalState.display;
			element.style.visibility = originalState.visibility;

			// Restore accessibility attributes
			if (originalState.tabIndex) {
				element.setAttribute('tabindex', originalState.tabIndex);
			} else {
				element.removeAttribute('tabindex');
			}

			if (originalState.ariaHidden) {
				element.setAttribute('aria-hidden', originalState.ariaHidden);
			} else {
				element.removeAttribute('aria-hidden');
			}

			// Restore required attributes
			if (originalState.required) {
				element.setAttribute('required', '');
			}
			if (originalState.ariaRequired) {
				element.setAttribute('aria-required', originalState.ariaRequired);
			}

			// Restore original value if it was stored
			if (state.originalValues.has(element)) {
				element.value = state.originalValues.get(element);
			}
		});

		// Clear stored states
		state.hiddenElements.clear();
		state.originalValues.clear();
	}

	/**
	 * Handle form submission
	 */
	function handleFormSubmit(event) {
		if (state.isSoloSelected) {
			event.preventDefault();
			event.stopPropagation();
			return false;
		}
	}

	/**
	 * Handle keydown events
	 */
	function handleKeydown(event) {
		if (event.key === 'Enter' && state.isSoloSelected) {
			const target = event.target;
			const form = target.closest('form');

			if (form && !target.closest('.self-serve-box')) {
				event.preventDefault();
				event.stopPropagation();
				return false;
			}
		}
	}

	/**
	 * Handle CTA button clicks
	 */
	function handleCTAClick(event) {
		if (event.target.classList.contains('buttonAction_clicked-SSblock-CTA')) {
			// Track analytics for CTA click
			trackAnalytics('SSblock_CTA_clicked');

			console.log('[Solo Detect] CTA clicked');

			// Navigate to Thinkific signup page
			window.location.href = 'https://courses.thinkific.com/signup?plan=low_22&interval=month';
		}
	}

	/**
	 * Track analytics events to dataLayer
	 */
	function trackAnalytics(eventType, eventData = {}) {
		try {
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				event: 'form_interaction',
				event_type: eventType,
				...eventData
			});

			console.log('[Solo Detect] Analytics event:', eventType, eventData);
		} catch (error) {
			console.warn('[Solo Detect] Failed to track analytics:', error);
		}
	}

	/**
	 * Announce message to screen readers
	 */
	function announce(message) {
		if (state.liveRegion) {
			state.liveRegion.textContent = message;
		}
	}

	/**
	 * Public API
	 */
	window.SoloDetect = {
		init: init,
		isSoloSelected: () => state.isSoloSelected,
		getState: () => ({ ...state }),
		showSelfServeBox: showSelfServeBox,
		hideSelfServeBox: hideSelfServeBox
	};

	// Auto-initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();