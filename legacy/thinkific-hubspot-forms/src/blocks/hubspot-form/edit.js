import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { ToggleControl, TextareaControl, PanelBody, PanelRow, TextControl, SelectControl, Button, Notice } from '@wordpress/components';
import './editor.scss';

// HubSpot Form ID options
const hubspotFormIdOptions = [
	{ label: __('Choose Form', 'think-blocks'), value: '', disabled: true },
	{ label: __('WP Test Form', 'think-blocks'), value: '02ab4863-2701-4b0d-8f0b-54f21600c8e5' },
	{ label: __('Dev Events (Socials)', 'think-blocks'), value: '39a14d99-0f72-42f8-8496-a8d44dba8fe3' },
	{ label: __('SS Content Download', 'think-blocks'), value: 'a8e1b766-f4e8-40fc-98db-21cd83fbff79' },
	{ label: __('Plus Content Download (TOF)', 'think-blocks'), value: 'da797add-f4b5-4a45-8a37-1e288990e945' },
	{ label: __('Plus Content Download (MOF)', 'think-blocks'), value: '940b5663-f686-43cd-bbee-941d9964a1f4' },
	{ label: __('Plus Content Download (BOF)', 'think-blocks'), value: 'b0c3ad4d-3dde-4b3f-95f5-556d5b215d77' },
	{ label: __('Plus Enquiries', 'think-blocks'), value: '10d5794c-5233-4d86-a619-a5a2f2a4b4e7' },
	{ label: __('Plus Gated Demo', 'think-blocks'), value: '20d0cbaf-436b-4a5c-9c51-997722b3e6bf' },
	{ label: __('Newsletter Sign-up', 'think-blocks'), value: 'a1e4d563-c754-42f2-a3f9-1739b2203bc5' },
	{ label: __('Paid Ads Trial LPs', 'think-blocks'), value: '76910d78-cb40-4b94-824c-1fabf91e4812' },
	{ label: __('Plus Webinar Registration', 'think-blocks'), value: 'eaffdf74-e315-427d-9d54-d94729ac7fe5' },
	{ label: __('Non-profit application form', 'think-blocks'), value: '6a925ef4-2878-472d-a2a9-bbaf6777bbfd' },
	{ label: __('Generic Lead Capture', 'think-blocks'), value: '6386d11f-bf68-4026-955c-9d5ebcd320ec' },
	{ label: __('Thinkific SS Webinar Registration', 'think-blocks'), value: '31c2c282-3be6-48b8-aaff-ebe2988beebc' },
	{ label: __('Events', 'think-blocks'), value: 'd603db0d-e40f-4d80-982b-8af8d42cc663' },
];

// Hidden field options
const hiddenFieldOptions = [
	{ label: __('Downloadable ID', 'think-blocks'), value: 'downloadable_id' },
	{ label: __('Event Trade Show Details', 'think-blocks'), value: 'event_trade_show_details__c' },
	{ label: __('PartnerStack XID', 'think-blocks'), value: 'partnerstack_xid' },
	{ label: __('PartnerStack Partner Key', 'think-blocks'), value: 'partnerstack_partner_key__c' },
];

export default function Edit({ attributes, setAttributes }) {
	const { blockEmailDomains, content, thankYouMessage, formId, submitButtonHtml, progressiveMappings = {}, hiddenFields = [], appendEmailToRedirect, enableSoloDetect, enableMarketingConsent = true, hideKnownFields = false } = attributes;
	const [isPreview, setIsPreview] = useState(false);

	const blockProps = useBlockProps();

	const togglePreview = () => {
		setIsPreview(!isPreview);
	};

	// Default HTML content for new forms
	const defaultHTML = `
    <div class="wp-block-group">
        <label for="firstname" class="wp-block-group__label"><span class="label-text">${__('First Name', 'think-blocks')}</span>
            <input type="text" placeholder="" name="firstname" id="firstname" required aria-required="true" class="wp-block-group__input" />
        </label>
    </div>
    <div class="wp-block-group">
        <label for="lastname" class="wp-block-group__label"><span class="label-text">${__('Last Name', 'think-blocks')}</span>
            <input type="text" placeholder="" name="lastname" id="lastname" required aria-required="true" class="wp-block-group__input" />
        </label>
    </div>
    <div class="wp-block-group">
        <label for="email" class="wp-block-group__label"><span class="label-text">${__('Email', 'think-blocks')}</span>
            <input type="email" placeholder="" name="email" id="email" required aria-required="true" class="wp-block-group__input" />
        </label>
    </div>
    <div class="wp-block-group hidden-field">
        <label for="company" class="wp-block-group__label"><span class="label-text">${__('Company', 'think-blocks')}</span>
            <input type="text" placeholder="" name="company" id="company" class="wp-block-group__input" />
        </label>
    </div>
    <div class="wp-block-group hidden-field">
        <label for="phone" class="wp-block-group__label"><span class="label-text">${__('Phone Number', 'think-blocks')}</span>
            <input type="text" placeholder="" name="phone" id="phone" class="wp-block-group__input" />
        </label>
    </div>
    <div class="wp-block-group hidden-field">
        <label for="website" class="wp-block-group__label"><span class="label-text">${__('Company Website', 'think-blocks')}</span>
            <input type="text" placeholder="" name="website" id="website" class="wp-block-group__input" />
        </label>
    </div>
    <div class="wp-block-group hidden-field">
        <label for="employee_range__c" class="wp-block-group__label"><span class="label-text">${__('Company Size:', 'think-blocks')}</span>
            <select name="employee_range__c" id="employee_range__c" class="wp-block-group__input">
                <option value="">${__('Number of employees', 'think-blocks')}</option>
                <option value="Just me (full time)">${__('Solo – full-time', 'think-blocks')}</option>
                <option value="Just me (part time)">${__('Solo – part-time', 'think-blocks')}</option>
                <option value="2-4 people">${__('2–4 employees', 'think-blocks')}</option>
                <option value="5-9 people">${__('5-9 employees', 'think-blocks')}</option>
                <option value="10-24 people">${__('10-24 employees', 'think-blocks')}</option>
                <option value="25-99 people">${__('25-99 employees', 'think-blocks')}</option>
                <option value="100-249 people">${__('100-249 employees', 'think-blocks')}</option>
                <option value="250-500 people">${__('250-500 employees', 'think-blocks')}</option>
                <option value="501+ people">${__('501+ employees', 'think-blocks')}</option>
            </select>
        </label>
    </div>
    <div class="wp-block-group hidden-field">
        <label for="probable_use_case" class="wp-block-group__label"><span class="label-text">${__('I\'m interested in:', 'think-blocks')}</span>
            <select name="probable_use_case" id="probable_use_case" class="wp-block-group__input">
                <option value="">${__('Primary use case', 'think-blocks')}</option>
                <option value="Educating customers (on my product or service)">${__('Educate existing customers', 'think-blocks')}</option>
                <option value="External training for revenue generation">${__('Sell learning products', 'think-blocks')}</option>
                <option value="Training employees (internally)">${__('Train internal teams', 'think-blocks')}</option>
                <option value="Generating new leads">${__('Generate new leads', 'think-blocks')}</option>
                <option value="Advance a non-profit mission">${__('Support a non-profit mission', 'think-blocks')}</option>
                <option value="Support for my Thinkific Account">${__('Support for my Thinkific Account ', 'think-blocks')}</option>
            </select>
			<div id="support-message" class="support-info-message" style="display:none;">
				To get help with your Thinkific account, <a href="https://support.thinkific.com/hc/en-us/">visit our help center</a>, and get in-touch with our suppor team.
			</div>
        </label>
    </div>
`.trim();



	// Field mapping

	// Allowed fields for progressive mapping
	const allowedFields = ['firstname', 'lastname', 'email', 'company', 'phone', 'website', 'primary_social_media_handle', 'primary_social_platform', 'instagram_handle', 'youtube_handle', 'tiktok_handle', 'twitter_handle', 'linkedin', 'employee_range__c', 'probable_use_case'];

	// Update a mapping entry
	const updateMapping = (index, key, value) => {
		const updatedMappings = { ...progressiveMappings };
		const mappingKeys = Object.keys(updatedMappings);
		updatedMappings[mappingKeys[index]][key] = value;
		setAttributes({ progressiveMappings: updatedMappings });
	};
	// Add a new mapping
	const addMapping = () => {
		const newKey = `field_${Object.keys(progressiveMappings).length + 1}`;
		setAttributes({
			progressiveMappings: {
				...progressiveMappings,
				[newKey]: { from: '', to: '' }
			}
		});
	};
	// Remove a mapping
	const removeMapping = (index) => {
		const updatedMappings = { ...progressiveMappings };
		const mappingKeys = Object.keys(updatedMappings);
		delete updatedMappings[mappingKeys[index]];
		setAttributes({ progressiveMappings: updatedMappings });
	};

	// Hidden fields
	// Update a hidden field entry
	const updateHiddenField = (index, key, value) => {
		const updatedFields = [...hiddenFields];
		updatedFields[index][key] = value;
		setAttributes({ hiddenFields: updatedFields });
	};

	// Add a new hidden field
	const addHiddenField = () => {
		setAttributes({
			hiddenFields: [...hiddenFields, { id: '', value: '' }] // Start empty
		});
	};

	// Remove a hidden field
	const removeHiddenField = (index) => {
		const updatedFields = hiddenFields.filter((_, i) => i !== index);
		setAttributes({ hiddenFields: updatedFields });
	};

	const isInvalid = !formId && !attributes.redirectUrl;

	return (
		<>
			{/* Sidebar Controls */}
			<InspectorControls>
				<PanelBody title={__('Email Validation Settings', 'think-blocks')} initialOpen={false}>
					<PanelRow>
						<ToggleControl
							label={__('Business Email Validation', 'think-blocks')}
							checked={!!blockEmailDomains}
							onChange={(value) => {
								setAttributes({ blockEmailDomains: value !== undefined ? value : false }); // Explicitly save false
							}}
						/>
					</PanelRow>
				</PanelBody>

				{/* HubSpot Form ID */}
				<PanelBody title={__('Form Wrapper Settings', 'think-blocks')} initialOpen={true}>
					<PanelRow>
						<SelectControl
							label={__('HubSpot Form', 'think-blocks')}
							help={__('Choose the HubSpot form to load into this block', 'think-blocks')}
							value={formId}
							options={hubspotFormIdOptions}
							onChange={(value) => setAttributes({ formId: value })}
							required={!attributes.redirectUrl}
						/>
					</PanelRow>
				</PanelBody>

				{/* Hidden Fields */}
				<PanelBody title={__('Hidden Fields', 'think-blocks')} initialOpen={false}>
					<div className="hubspot-hidden-fields-wrapper">
						{hiddenFields.map((field, index) => (
							<div key={index} className="hubspot-hidden-field-row">
								<SelectControl
									value={field.id}
									options={[
										{ label: __('Choose a Hidden Field', 'think-blocks'), value: '' }, // ✅ Default option
										...hiddenFieldOptions
									]}
									onChange={(value) => updateHiddenField(index, 'id', value)}
								/>
								<TextControl
									placeholder={__('Enter value...', 'think-blocks')}
									value={field.value}
									onChange={(value) => updateHiddenField(index, 'value', value)}
								/>
								<Button isDestructive onClick={() => removeHiddenField(index)}>✖</Button>
							</div>
						))}
					</div>

					<Button primary onClick={addHiddenField}>+ {__('Add Another Field', 'think-blocks')}</Button>
				</PanelBody>

				{/* URL Redirect */}
				<PanelBody title={__('Redirect Settings', 'think-blocks')} initialOpen={false}>
					<TextControl
						label={__('Redirect URL after submission', 'think-blocks')}
						value={attributes.redirectUrl}
						onChange={(value) => setAttributes({ redirectUrl: value })}
						help={
							attributes.appendEmailToRedirect
								? __('The user will be redirected to this URL after submitting the form. The email will be appended as a query parameter.', 'think-blocks')
								: __('The user will be redirected to this URL after submitting the form. Toggle on "Append email" below to add the email as a query parameter.', 'think-blocks')
						}
						placeholder="https://courses.thinkific.com/clients/new?deal=start_23_v1_monthly_usd_2740"
					/>
					{attributes.redirectUrl && (
						<ToggleControl
							label={__('Append email as query parameter', 'think-blocks')}
							checked={!!attributes.appendEmailToRedirect}
							onChange={(value) => setAttributes({ appendEmailToRedirect: value })}
							help={__('When enabled, the submitted email will be added as ?email=user@example.com to the redirect URL.', 'think-blocks')}
						/>
					)}
				</PanelBody>

				{/* Submit Button */}
				<PanelBody title={__('Submit Button Settings', 'think-blocks')} initialOpen={false}>
					<PanelRow>
						<TextareaControl
							label={__('Submit Button', 'think-blocks')}
							help={__('Customize the HTML for the submit button.', 'think-blocks')}
							value={submitButtonHtml}
							onChange={(value) => setAttributes({ submitButtonHtml: value })}
							placeholder={__('Enter Submit Button...', 'think-blocks')}
						/>
					</PanelRow>
				</PanelBody>

				{/* Thank You Message Settings */}
				<PanelBody title={__('Thank You Message Settings', 'think-blocks')} initialOpen={false}>
					<PanelRow>
						<TextareaControl
							label={__('Thank You Message', 'think-blocks')}
							help={__('Customize the Thank You message shown after form submission.', 'think-blocks')}
							value={thankYouMessage}
							onChange={(value) => setAttributes({ thankYouMessage: value })}
							placeholder={__('Write your Thank You message here...', 'think-blocks')}
						/>
					</PanelRow>
				</PanelBody>

				{/* Social Media Platforms */}
				<PanelBody title={__('Social Media Platforms', 'think-blocks')} initialOpen={false}>
					<PanelRow>
						<ToggleControl
							label={__('Enable Social Media Platforms', 'think-blocks')}
							help={__('Allow users to select their primary social media platform and provide their handle.', 'think-blocks')}
							checked={!!attributes.enableSocialMediaSelector}
							onChange={(value) => setAttributes({ enableSocialMediaSelector: value })}
						/>
					</PanelRow>
					{attributes.enableSocialMediaSelector && (
						<>
							<PanelRow>
								<TextControl
									label={__('Selector Label', 'think-blocks')}
									value={attributes.socialMediaSelectorLabel || 'Primary Social Media Handle'}
									onChange={(value) => setAttributes({ socialMediaSelectorLabel: value })}
									help={__('Label for the social media platform selector.', 'think-blocks')}
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label={__('Disclaimer Text', 'think-blocks')}
									value={attributes.socialMediaDisclaimer || "We'll only use this to connect with you about the event."}
									onChange={(value) => setAttributes({ socialMediaDisclaimer: value })}
									help={__('Disclaimer text shown below the social media fields.', 'think-blocks')}
								/>
							</PanelRow>
							<PanelRow>
								<div className="social-platforms-config">
									<h4>{__('Available Platforms:', 'think-blocks')}</h4>
									<div className="social-platforms-list">
										{[
											{ key: 'linkedin', label: 'LinkedIn' },
											{ key: 'instagram', label: 'Instagram' },
											{ key: 'youtube', label: 'YouTube' },
											{ key: 'tiktok', label: 'TikTok' },
											{ key: 'twitter', label: 'Twitter/X' },
											{ key: 'other', label: 'Other' }
										].map(platform => (
											<ToggleControl
												key={platform.key}
												label={platform.label}
												checked={attributes.enabledSocialPlatforms ? attributes.enabledSocialPlatforms.includes(platform.key) : true}
												onChange={(checked) => {
													const currentPlatforms = attributes.enabledSocialPlatforms || ['instagram', 'youtube', 'tiktok', 'twitter', 'linkedin', 'other'];
													let updatedPlatforms;

													if (checked) {
														updatedPlatforms = [...currentPlatforms, platform.key];
													} else {
														updatedPlatforms = currentPlatforms.filter(p => p !== platform.key);
													}

													setAttributes({ enabledSocialPlatforms: updatedPlatforms });
												}}
											/>
										))}
									</div>
								</div>
							</PanelRow>
						</>
					)}
				</PanelBody>

				{/* Solo Detect Settings */}
				<PanelBody title={__('Solo Detection Settings', 'think-blocks')} initialOpen={false}>
					<PanelRow>
						<ToggleControl
							label={__('Enable Solo Detection', 'think-blocks')}
							help={__('When enabled, the form will detect when users select solo options and show self-serve alternatives. Only enable this for Sales Enquiries forms.', 'think-blocks')}
							checked={!!enableSoloDetect}
							onChange={(value) => setAttributes({ enableSoloDetect: value })}
						/>
					</PanelRow>
				</PanelBody>

				{/* Marketing Consent Settings */}
				<PanelBody title={__('Marketing Consent Settings', 'think-blocks')} initialOpen={false}>
					<PanelRow>
						<ToggleControl
							label={__('Enable Marketing Consent Checkbox', 'think-blocks')}
							help={__('When enabled, the marketing consent field will be tracked. For GDPR regions, only the data collection consent checkbox is shown. For non-GDPR regions, the marketing consent value is automatically set to "Not Applicable".', 'think-blocks')}
							checked={!!enableMarketingConsent}
							onChange={(value) => setAttributes({ enableMarketingConsent: value })}
						/>
					</PanelRow>
				</PanelBody>

				{/* Hide Known Fields Settings */}
				<PanelBody title={__('Hide Known Fields Settings', 'think-blocks')} initialOpen={false}>
					<PanelRow>
						<ToggleControl
							label={__('Hide Known Fields', 'think-blocks')}
							help={__('When enabled, fields with known data will be hidden (except email, which always remains visible). Hidden fields are still included in form submission but excluded from validation.', 'think-blocks')}
							checked={!!hideKnownFields}
							onChange={(value) => setAttributes({ hideKnownFields: value !== undefined ? value : false })}
						/>
					</PanelRow>
				</PanelBody>

				{/* Progressive Field Mapping */}
				<PanelBody title={__('Progressive Profiling', 'think-blocks')} initialOpen={false}>
					<div className="hubspot-field-mapping-wrapper">
						{Object.keys(progressiveMappings).length > 0 && (
							<div className="hubspot-field-mapping-header">
								<div className="hubspot-field-mapping-label">{__('Known field', 'think-blocks')}</div>
								<div className="hubspot-field-mapping-label">{__('Replacement field', 'think-blocks')}</div>
								<div className="hubspot-field-mapping-remove"></div> {/* Placeholder for remove button */}
							</div>
						)}
						{Object.entries(progressiveMappings).map(([key, mapping], index) => (
							<div key={key} className="hubspot-field-mapping-row">
								<SelectControl
									value={mapping.from}
									options={[
										{ label: __('Choose One', 'think-blocks'), value: '' },
										...allowedFields.map(field => ({ label: field, value: field }))
									]}
									onChange={(value) => updateMapping(index, 'from', value)}
								/>
								<SelectControl
									value={mapping.to}
									options={[
										{ label: __('Choose One', 'think-blocks'), value: '' },
										...allowedFields.map(field => ({ label: field, value: field }))
									]}
									onChange={(value) => updateMapping(index, 'to', value)}
								/>
								<Button isDestructive onClick={() => removeMapping(index)}>✖</Button>
							</div>
						))}
					</div>
					<Button primary onClick={addMapping}>+ {__('Add Mapping', 'think-blocks')}</Button>
				</PanelBody>
			</InspectorControls>

			{isInvalid && (
				<Notice status="error" isDismissible={false}>
					{__('Please select a HubSpot Form or provide a Redirect URL.', 'think-blocks')}
				</Notice>
			)}

			{/* Main Content */}
			<div {...blockProps}>
				<div className="preview-controls">
					<Button variant="primary" onClick={togglePreview}>
						{isPreview ? 'Edit HTML' : 'Preview'}
					</Button>
				</div>
				{isPreview ? (
					<div
						className="preview-html hubspot-form__form"
						dangerouslySetInnerHTML={{ __html: content }}
					/>
				) : (
					<TextareaControl
						label={__('HTML Form Content', 'think-blocks')}
						value={content || defaultHTML}
						onChange={(value) => setAttributes({ content: value })}
						help={__('Customize the HTML for the form fields here. Make sure to preserve valid HTML.', 'think-blocks')}
					/>
				)}
			</div>
		</>
	);
}