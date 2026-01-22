# 03 Data Model

## Custom Post Type

### `hubspot_form` (CPT)
Canonical source of truth for form definitions. Each record defines the schema, validation rules, and submission behavior.

**Core post fields**
- `post_title`: Human-readable form name.
- `post_status`: Draft/Publish to control availability.
- `post_content`: Optional long-form description or internal notes.
- `post_name`: Slug for admin URLs.

## Required Meta Schema

> All validation and rendering logic is derived from this schema. Client-provided field lists are ignored.

### `hubspot_form_schema` (array/object)
Defines fields, validation, and rendering rules.

**Fields**
- `fields`: ordered list of field definitions
  - `name`: string (HubSpot field name)
  - `label`: string
  - `type`: string (`text`, `email`, `tel`, `url`, `select`, `checkbox`, `textarea`, `hidden`)
  - `required`: boolean
  - `validation`: rules (pattern, min/max length, allowed values)
  - `options`: list of values for selects
  - `default`: default value or fallback
  - `visible`: boolean (for renderer only; not used for server validation)

**Server validation**
- `required_fields`: derived from field definitions
- `validation_rules`: compiled rules for server-side validation
- `hubspot_field_map`: optional mapping if internal names differ

### `hubspot_form_settings` (array/object)
Form-level behavior that overrides global settings.

- `hubspot_form_id`: HubSpot form GUID
- `redirect_url`: post-submit redirect URL
- `append_email_to_redirect`: boolean
- `thank_you_message`: string
- `enable_marketing_consent`: boolean
- `enable_social_selector`: boolean
- `enabled_social_platforms`: list of allowed platforms
- `block_email_domains`: boolean
- `captcha_required`: boolean (per-form override)
- `rate_limit_enabled`: boolean (per-form override)

### `hubspot_form_consent` (array/object)
Consent and subscription configuration for HubSpot.

- `consent_text`: string
- `marketing_text`: string
- `subscription_type_id`: integer

### `hubspot_form_utm` (array/object)
UTM capture behavior.

- `enable_utm_capture`: boolean
- `utm_fields`: list of keys to capture (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`)

### `hubspot_form_hidden_fields` (array)
Key/value pairs to include in submissions without rendering a visible input.

- `id`: field name (e.g., `downloadable_id`)
- `value`: string

### `hubspot_form_progressive_rules` (array/object)
Rules for progressive profiling and field replacement.

- `mappings`: list of `{ from, to }`
- `hide_known_fields`: boolean (renderer hides known fields, server still validates on schema)

### `hubspot_form_version` (string/int)
Schema version for signed token validation and replay protection.

### `hubspot_form_token_ttl` (int)
Token expiry in seconds.

## Plugin Settings

### `bb_hubspot_forms_settings` (array/object)
Global plugin configuration stored in options.

- `portal_id`: HubSpot portal ID (account ID)
- `private_token`: HubSpot Private App access token (`pat-...`, encrypted at rest when key is set)
- `captcha_provider`: CAPTCHA provider (`recaptcha_v3`, `turnstile`, `hcaptcha`, or empty for disabled)
- `captcha_site_key`: CAPTCHA site key
- `captcha_secret_key`: CAPTCHA secret key
- `captcha_min_score`: minimum reCAPTCHA v3 score (0.0–1.0)
- `captcha_expected_action`: expected reCAPTCHA v3 action name
- `consent_enabled`: boolean to enable consent options
- `consent_text`: consent to process text
- `marketing_enabled`: boolean to enable marketing consent
- `marketing_text`: marketing consent text
- `subscription_type_id`: HubSpot subscription type ID
- `debug_enabled`: boolean to enable debug logging

> **Note:** Form IDs are fetched directly from HubSpot using the Private App Token. No manual form ID configuration is required.

## Derived Behavior

### Rendering
- Renderer uses `hubspot_form_schema.fields` to build HTML.
- Hidden fields are appended from `hubspot_form_hidden_fields`.
- Data attributes include form ID, schema version, token expiry.

### Validation
- Server validates each submission against stored schema.
- Required fields are enforced even if client omits them.
- Field types and formats are validated server-side (email/URL/phone/patterns).
- Any client-provided `visible_fields` data is ignored.

### Submission Mapping
- Fields are normalized into HubSpot API payload shape.
- Legal consent and GDPR fields are added based on settings.
- IP, page URI, and context are appended when available.

## Notes on Security

- Schema hash is included in the signed form token.
- Submission payload is only accepted if schema version matches.
- No CRM data prefill endpoints are part of MVP data flow.
