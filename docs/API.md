# Developer API

## Core classes
- `CookieDK_Cookie_Database`
- `CookieDK_Cookie_Detector`
- `CookieDK_Cookie_Storage`
- `CookieDK_GDPR_Compliance`
- `CookieDK_Frontend`
- `CookieDK_Admin_Page`
- `CookieDK_Security`

## Typical usage
- Initialize via `cookiedk_init()` on `plugins_loaded`.
- Cookie names are matched against `database/cookies.json` (exact then wildcard).
- Loader maps GDPR categories: `preferences` → `functional`, `statistics` → `analytics`, `unclassified` → `functional`.
- Extend cookie library via `cookiedk_known_cookies` filter.
