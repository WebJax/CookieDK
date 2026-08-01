# Security Checklist

## Audit status
- [x] Alle formularer har nonce-verificering
- [x] Alt bruger-input er saniteret
- [x] Alt output er escaped
- [x] Capability-checks på admin-funktioner
- [x] AJAX-endpoints er sikret
- [x] Database-queries bruger prepared statements
- [x] Ingen hardcodede hemmeligheder eller API-nøgler
- [x] Rate-limiting på AJAX-endpoints
- [x] HTTPS anbefalet i dokumentation

## Verification guide
- Kør `composer phpstan` og `composer phpcs`
- Kør `composer lint`
- Kør `composer test`
