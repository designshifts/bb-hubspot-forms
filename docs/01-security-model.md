# 01 Security Model

## Threat Model (MVP)
- Public form endpoints are discoverable and can be targeted by bots.
- Client-side validation can be bypassed.
- Email-based CRM lookups can leak PII.
- Credential leakage can expose HubSpot accounts.
- Replay attacks can reuse submissions.

## Core Security Principles
- Server-side validation is authoritative.
- No CRM read endpoints in MVP.
- All submissions require a signed form token.
- Tokens expire and are tied to schema version.
- SSL verification is always enabled for outbound requests.
- PII is minimized in client storage and logs.

## Signed Form Tokens
**Purpose:** Prevent form tampering and casual abuse.

**Token payload fields:**
- `form_id`
- `schema_version`
- `issued_at`
- `expires_at`
- `site_hash` (optional)

**Token scheme:**
- HMAC (SHA-256) using a server-side secret.
- Token included in the rendered form markup.
- Server verifies signature, expiry, and schema version.
- Token secret is derived from a site secret plus a versioned salt to allow rotation.

## CSRF and Public Forms
- Public forms should not rely on WP REST nonces for security.
- Signed tokens are the primary CSRF mitigation for unauthenticated submissions.

## REST Submission Security
- Rate limiting by IP with burst protection.
- Optional honeypot field and minimum time-to-submit.
- Captcha required when enabled (missing or invalid token fails).
- Reject client-provided “visible fields” lists.

## PII Handling
- No storage of sensitive fields in cookies by default.
- If progressive profiling is used, only store non-sensitive values and short TTL.
- Logs must redact email and other PII by default.
- Do not log raw submission payloads in debug mode.
- If IPs are stored, store only a one-way hash.

## IP Address Handling
- Use direct client IP by default.
- If behind a proxy/CDN, use trusted headers only when explicitly enabled.

## Settings and Secrets
- HubSpot credentials stored in wp-config constants or encrypted options.
- Never expose secrets in front-end JS.
- Admin settings page enforces capability checks.

## Permissions and Capabilities
- Only admins (or a dedicated capability) can manage settings and CPT forms.
- Shortcode and block rendering are public, but submission is token-gated.

## Deprecations from Legacy Plugin
- Remove any endpoints that accept `email` and return CRM data.
- Stop loading scripts from third-party CDNs unless pinned and justified.

