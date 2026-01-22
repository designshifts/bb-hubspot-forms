# 06 Spam and Email Blocking

## Goals
- Block free and disposable email domains for business-focused forms.
- Provide reliable spam controls without maintaining a custom list in-code.
- Allow admin override for allow-list and deny-list.

## Domain Sources
Use curated, actively maintained lists and bundle them in the plugin with periodic updates.

Recommended sources:
- `free-email-domains` (HubSpot-based list)  
  https://github.com/kikobeats/free-email-domains?utm_source=chatgpt.com
- `disposable-email-domains` (widely used, actively maintained)  
  https://github.com/disposable-email-domains/disposable-email-domains?utm_source=chatgpt.com

License compatibility should be verified before bundling.

## Update Strategy
- Ship a bundled snapshot list in the plugin.
- Provide a scheduled update job (WP-Cron) to refresh lists when opted in.
- Cache lists in a transient or option with a configurable TTL.

## Normalization Rules
- Lowercase all domains.
- Trim whitespace.
- For emails, extract the domain and compare against normalized lists.
- Treat subdomains as matches if the base domain is blocked.

## Admin Controls
- Global toggle: Block business email domains.
- Source selection: use one or both lists.
- Allow-list: always permit specific domains.
- Deny-list: always block specific domains.
- Conflict rule: deny-list overrides allow-list.

## Captcha Providers
Supported providers (configurable):
- reCAPTCHA v3
- Turnstile
- hCaptcha

If captcha is enabled, missing or invalid tokens must fail submission.
reCAPTCHA v3 also enforces score, action, and hostname checks.

## Optional Heuristics
- Honeypot field.
- Minimum time-to-submit threshold.
- Per-IP rate limiting with burst protection.

## Performance Notes
- Load and normalize lists once per request.
- Cache normalized sets to avoid repeated transforms.
