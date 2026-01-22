# 00 Vision and Scope

## Vision
Build a security-first, enterprise-ready HubSpot forms plugin for WordPress that is simple to configure, easy to reuse across pages, and safe by default.

The plugin should:
- Use a CPT as the source of truth for form definitions.
- Embed forms via shortcode with an optional block selector.
- Enforce server-side validation and submission rules regardless of client behavior.

See `bb-hubspot-forms/STATUS.md` for current progress and remaining tasks.

## Target Platform
- WordPress: 6.9+
- PHP: 8.0+ (confirm minimum in implementation phase)

## Naming (Decision)
- Plugin slug: `bb-hubspot-forms`
- Text domain: `bb-hubspot-forms`

## MVP Objectives (Free)
- Secure submission pipeline with signed form token and rate limiting.
- Settings page for HubSpot credentials and spam controls.
- Form library (CPT) with a basic schema-driven editor.
- Shortcode embedding + block selector.
- Server-side submission to HubSpot with normalized errors.
- No CRM read endpoints in MVP.
- Spam protection with captcha provider options and domain blocking.
- Hidden fields + UTM capture + dataLayer push.
- Redirect URL with optional append-email.

## Pro Objectives
- Multiple forms (free limited to 1).
- Progressive profiling (hide known fields) with secure rules.
- Advanced routing (conditional redirects, hidden fields).
- Field mapping presets + export/import JSON.

Pro features ship in a separate add-on plugin: `bb-hubspot-forms-pro`.

## Non-Goals (MVP)
- Full drag-and-drop form builder.
- CRM read endpoints for contact prefill.
- File upload handling.
- Multi-portal HubSpot routing.

## Free vs Pro Boundaries (Initial)
- Free: 1 CPT form (enforced at CPT creation), core settings, basic spam controls, shortcode + block selector.
- Pro: unlimited forms, progressive profiling, advanced routing, presets, export/import.

## Success Criteria
- No public endpoint exposes CRM contact data.
- All submissions pass server-side schema validation.
- All outbound HubSpot requests use SSL verification.
- Admin UX is clear and non-janky for single-form usage.

