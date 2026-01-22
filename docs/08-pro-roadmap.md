# 08 Pro Roadmap

## Pro Features
- Unlimited forms (free tier limited to 1).
- Progressive profiling with secure rules and short-lived storage.
- Conditional routing (redirects and hidden fields).
- Field mapping presets + export/import JSON.
- WordPress-defined fields mapped to HubSpot properties.
- Reusable field groups.
- Multi-form field schemas.
- Advanced conditional logic.
- Webhook after submit (configurable endpoint).
- Lead routing rules (domain, country, or field-based).
- Advanced spam stack (Turnstile + heuristics + IP reputation hooks).
- Submission event log and audit trail.
- Multi-portal support (agency workflows).

## Pro Gating Strategy
- Feature flags checked server-side.
- UI hides or disables Pro-only options for free tier.
- Fallback behavior defaults to secure baseline.

## Extension Hooks
Provide hooks for advanced users:
- `bb_hubspot_forms_schema_filter`
- `bb_hubspot_forms_before_submit`
- `bb_hubspot_forms_after_submit`
- `bb_hubspot_forms_rate_limit_config`
- `bb_hs_allowed_field_types`
- `bb_hs_submission_payload`
- `bb_hs_schema_before_save`
- `bb_hs_after_schema_fetch`

## Pro Release Phases
1. Unlimited forms + export/import JSON.
2. Progressive profiling + conditional routing.
3. Audit log + multi-portal support.

