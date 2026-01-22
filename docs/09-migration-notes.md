# 09 Migration Notes

## Scope
This document maps the existing `thinkific-hubspot-forms` behavior to the new CPT-driven BB HubSpot Forms plugin.

## Form IDs
**Legacy:** HubSpot form IDs were hardcoded in the block editor list.  
**New:** Each HubSpot form is stored as a CPT entry with its own schema.

Migration steps:
- Create one `hubspot_form` CPT per existing HubSpot GUID.
- Replace block instances with shortcode or block selector references.

## Settings
**Legacy:** HubSpot credentials and reCAPTCHA keys were read from environment constants.  
**New:** Settings are stored via the plugin settings page (or wp-config constants).

Migration steps:
- Offer wp-config constants as an initial migration path.
- Provide admin UI for long-term management.

## REST Endpoints
**Legacy:** `GET /get-user-data?email=...` allowed CRM lookups by email.  
**New:** Endpoint removed; no CRM prefill in MVP.

## Progressive Profiling
**Legacy:** Client-side `visible_fields` influenced validation.  
**New:** Server-side schema validation is authoritative; visible fields are ignored.

## Client Storage
**Legacy:** UserProfile cookie used for prefill.  
**New:** Progressive profiling is gated to Pro and uses minimal, short-lived storage.

## Captcha
**Legacy:** Missing token could be accepted in “lazy load” scenarios.  
**New:** Missing or invalid captcha token always fails when enabled.

## Script Dependencies
**Legacy:** `js-cookie` loaded via CDN.  
**New:** Bundle dependency locally or replace with native storage APIs.

## Blocked Domains
**Legacy:** Large hardcoded PHP array.  
**New:** Bundled external lists with optional scheduled updates.
