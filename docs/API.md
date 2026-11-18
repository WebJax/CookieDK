# Developer API

## Core classes
- `CookieDK_Cookie_Database`
- `CookieDK_Cookie_Detector`
- `CookieDK_Cookie_Storage`
- `CookieDK_GDPR_Compliance`
- `CookieDK_Consent_API`
- `CookieDK_Frontend`
- `CookieDK_Admin_Page`
- `CookieDK_Security`

## Typical usage
- Initialize via `cookiedk_init()` on `plugins_loaded`.
- Cookie names are matched against `database/cookies.json` (exact then wildcard).
- Loader maps GDPR categories: `preferences` → `functional`, `statistics` → `analytics`, `unclassified` → `functional`.
- Extend cookie library via `cookiedk_known_cookies` filter.

## WP Consent API

`CookieDK_Consent_API` registers CookieDK when [WP Consent API](https://github.com/WordPress/wp-consent-level-api) is active.

- Consent type is `optin`.
- Cookie lifetime follows `consent_expiry_days`.
- Category map: `necessary` → `functional`, `functional` → `preferences`, `analytics` → `statistics` and `statistics-anonymous`, `marketing` → `marketing`.
- `functional` is allowed before the visitor chooses. Other categories stay unset until a choice is stored.
- Known site cookies are registered with `wp_add_cookie_info()`. `cookiedk_consent` is registered as `LOCALSTORAGE`.
