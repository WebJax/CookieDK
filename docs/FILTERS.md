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
- `cookiedk_catalog_urls` — override remote `cookies` and `providers` JSON URLs.
- `cookiedk_catalog_dir` — override the local snapshot directory.
- `cookiedk_catalog_allowed_hosts` — HTTPS hosts allowed for catalog download.
- `cookiedk_local_cookies` — alter the site overlay merged on top of the catalog.
- `cookiedk_github_token` — GitHub token used to push AI cookie entries.
- `cookiedk_github_repo` / `cookiedk_github_branch` / `cookiedk_github_cookies_path` — GitHub push target.
- `cookiedk_ai_available` — force-enable or disable AI descriptions.
- `cookiedk_pre_ai_description` — return an array (or `WP_Error`) to skip the live AI client.
- `cookiedk_pre_remote_request` — mock or short-circuit catalog HTTP requests.

## WP Consent API

When that plugin is active, CookieDK hooks:

- `wp_consent_api_registered_{plugin_basename}`
- `wp_get_consent_type` — returns `optin`
- `wp_consent_api_cookie_expiration` — returns `consent_expiry_days`
