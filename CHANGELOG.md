# Ændringslog – CookieDK

Alle væsentlige ændringer til dette projekt dokumenteres i denne fil.

Formatet er baseret på [Keep a Changelog](https://keepachangelog.com/da/1.0.0/),
og dette projekt følger [Semantisk Versionering](https://semver.org/lang/da/).

---

## [1.0.0] – 2026-08-01

### Tilføjet

#### Fase 1 – Plugin-opsætning & Grundlæggende Struktur
- Oprettet `cookiedk.php` med komplet plugin-header (Plugin Name, Description, Version, Author, Text Domain, Domain Path, Requires WordPress: 5.0, Requires PHP: 7.4)
- Oprettet mappestruktur: `/includes/`, `/admin/`, `/public/`, `/languages/`, `/assets/`
- Plugin-konstanter: `COOKIEDK_VERSION`, `COOKIEDK_PLUGIN_DIR`, `COOKIEDK_PLUGIN_URL`, `COOKIEDK_DB_VERSION`
- Aktiverings- og deaktiveringshooks
- Standard-indstillinger ved aktivering
- Database-migrations-system via versionskontrol
- Oprettet `uninstall.php` til rydning af alle plugin-data ved afinstallation

#### Fase 2 – Cookie-Detektion
- Oprettet `includes/class-cookie-detector.php` med:
  - Server-side cookie-scanning via `$_COOKIE` og WordPress `init`-hook
  - Client-side cookie-scanning via JavaScript i `wp_footer`
  - AJAX-endpoint til modtagelse af client-side cookie-data (`cookiedk_report_cookies`)
  - Cookie-klassificering via eksakt match og wildcard-patterns
  - Cookie-data struktur med `name`, `category`, `description_da`, `duration`, `provider`, `necessary`
  - Foruddefineret bibliotek med 30+ kendte cookies og danske beskrivelser:
    - WordPress core-cookies (`wordpress_*`, `wordpress_logged_in_*`, `wp-settings-*`, osv.)
    - WooCommerce-cookies (`woocommerce_cart_hash`, `wc_session_cookie`, osv.)
    - Google Analytics cookies (`_ga`, `_gid`, `_gat`, `_ga_*`)
    - Google Ads cookies (`_gcl_au`, `IDE`)
    - Facebook Pixel cookies (`_fbp`, `_fbc`)
    - Hotjar cookies (`_hjid`, `_hjSessionUser_*`)
    - Matomo/Piwik cookies (`_pk_id*`, `_pk_ses*`)
    - LinkedIn cookies (`li_gc`, `AnalyticsSyncHistory`)
    - Twitter/X cookies (`personalization_id`)
    - Cloudflare cookies (`__cfduid`, `cf_clearance`)
    - PHP sessions (`PHPSESSID`)
  - Filter `cookiedk_known_cookies` til at tilføje egne cookies
  - Statisk metode `get_categories()` med alle cookie-kategorier på dansk
  - Nonce-beskyttelse på AJAX-endpoints

#### Fase 3 – Cookie-Lagring & Håndtering
- Oprettet `includes/class-cookie-storage.php` med:
  - Oprettelse af database-tabel `wp_cookiedk_cookies` via `dbDelta()`
  - Oprettelse af database-tabel `wp_cookiedk_consent_log` via `dbDelta()`
  - CRUD-operationer: `save_cookie()`, `get_cookie_by_name()`, `get_cookie_by_id()`, `get_all_cookies()`, `update_cookie()`, `delete_cookie()`
  - `log_consent()` – logger bruger-samtykker med fingerprint, tidsstempel, IP og user-agent
  - `get_consent_log()` – henter samtykke-log, filtreret på fingerprint
  - `anonymize_old_ips()` – anonymiserer IP-adresser ældre end X dage
  - `delete_consent_log_by_fingerprint()` – GDPR "retten til at blive glemt"
  - Sanitering og validering af alle input-data
- Oprettet `includes/class-gdpr-compliance.php` med:
  - AJAX-handler `cookiedk_log_consent` til at logge samtykker fra frontend
  - Integration med WordPress' privatlivsværktøjer (`wp_privacy_personal_data_exporters`)
  - Integration med WordPress' data-sletning (`wp_privacy_personal_data_erasers`)
  - `export_user_data()` – GDPR data-export for en given e-mail
  - `erase_user_data()` – GDPR data-sletning for en given e-mail
  - `run_daily_maintenance()` – daglig cron til IP-anonymisering og log-oprydning
  - Anonymt fingerprint-system (ingen personhenførbare data)
  - IP-anonymisering efter 30 dage (konfigurerbar)
  - Oprydning af samtykke-log baseret på opbevaringsperiode

#### Dokumentation
- Oprettet `README.md` med installationsvejledning, database-schema, sikkerhedsbeskrivelse og hook-dokumentation
- Oprettet `CHANGELOG.md` (denne fil)

### Ændret
- Intet (første udgivelse)

### Rettet
- Intet (første udgivelse)

### Fjernet
- Intet (første udgivelse)

---

## [0.1.0] – 2026-08-01

### Tilføjet
- `PROJEKTPLAN.md` med komplet projektplan for CookieDK-pluginen

---

[1.0.0]: https://github.com/WebJax/CookieDK/releases/tag/v1.0.0
[0.1.0]: https://github.com/WebJax/CookieDK/releases/tag/v0.1.0
