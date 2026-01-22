# 05 REST API

## Namespace
`hubspotform/v1`

## Endpoints (MVP)

### `POST /hubspotform/v1/submit`
Submit a form payload to HubSpot via the server.

**Security requirements**
- Valid signed form token.
- Rate limit check.
- Captcha verification if enabled.

**Request Body (JSON)**
```json
{
  "formId": "GUID",
  "token": "signed_form_token",
  "schemaVersion": "v1",
  "fields": {
    "email": "user@example.com",
    "firstname": "Jane",
    "lastname": "Doe",
    "company": "Acme"
  },
  "hiddenFields": {
    "downloadable_id": "abc123"
  },
  "context": {
    "pageUri": "https://example.com/landing",
    "pageName": "Landing Page",
    "hutk": "optional"
  },
  "utm": {
    "utm_source": "google",
    "utm_medium": "cpc",
    "utm_campaign": "spring_launch"
  },
  "redirectUrl": "https://example.com/thanks",
  "appendEmailToRedirect": true,
  "captchaToken": "provider_token"
}
```

**Server behavior**
- Validate `token`, `schemaVersion`, and `formId` against CPT schema.
- Reject mismatched or expired `schemaVersion` with 403.
- Validate each field against the stored schema rules.
- Apply business-email blocking if enabled.
- Build HubSpot API payload and submit.
- Return normalized success or field errors.

**Response (Success)**
```json
{
  "success": true,
  "message": "Form submitted successfully."
}
```

**Response (Field Errors)**
```json
{
  "success": false,
  "errors": {
    "email": "Please enter a valid email address.",
    "company": "Company name is required."
  }
}
```

**Response (General Error)**
```json
{
  "success": false,
  "errors": {
    "submission": "There was an error submitting the form. Please try again later."
  }
}
```

**Response (Rate Limited)**
```json
{
  "success": false,
  "errors": {
    "rate_limit": "Too many requests. Please try again later."
  }
}
```

**HTTP Status Codes**
- `200` success
- `400` validation error or missing required data
- `403` token or permission failure
- `429` rate limited
- `500` server error or HubSpot error

## Deprecated / Removed
- No endpoint for `get-user-data` by email.

