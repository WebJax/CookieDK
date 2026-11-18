# Hooks & Filters

## Actions
- `cookiedk_daily_cron`

## AJAX actions
- `cookiedk_log_consent`
- `cookiedk_save_consent`
- `cookiedk_export_user_data`
- `cookiedk_revoke_consent`
- `cookiedk_delete_user_cookies`

## Filters
- `cookiedk_known_cookies` — modify the JSON-loaded cookie library after category mapping.
- `cookiedk_enable_google_consent_mode` — return false to skip the Consent Mode v2 snippet and later `gtag('consent', 'update')` calls. Default true.

## WP Consent API

When that plugin is active, CookieDK hooks:

- `wp_consent_api_registered_{plugin_basename}`
- `wp_get_consent_type` — returns `optin`
- `wp_consent_api_cookie_expiration` — returns `consent_expiry_days`
