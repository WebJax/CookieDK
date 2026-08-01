# Ændringslog – CookieDK

Alle væsentlige ændringer til dette projekt dokumenteres i denne fil.

Formatet er baseret på [Keep a Changelog](https://keepachangelog.com/da/1.0.0/),
og dette projekt følger [Semantisk Versionering](https://semver.org/lang/da/).

---

## [Unreleased]

### Tilføjet

#### Fase 6 – GDPR-compliance
- Udvidet `includes/class-gdpr-compliance.php` med:
  - Fire nye AJAX-endpoints: `cookiedk_save_consent`, `cookiedk_export_user_data`, `cookiedk_revoke_consent`, `cookiedk_delete_user_cookies`
  - WordPress user-hooks: `user_register`, `delete_user`, `wp_logout`
  - Automatisk sletning af samtykker ved bruger-sletning
  - Hjælpemetode `sanitize_consent_data()` til validering af samtykke-input
  - Fingerprint-validering med regex (SHA-256 hex)
  - Eksplicit samtykke-flow med validering af tilladte kategorier
  - Refaktoreret `export_user_data()` til brug af `CookieDK_Consent_Export`
- Oprettet `includes/class-consent-export.php` (ny klasse):
  - `export_as_json()` – Eksporterer samtykke-data i JSON-format
  - `to_wordpress_export_format()` – Formatter data til WordPress DSAR-format
  - `format_consents()` – Formaterer samtykke-log-poster med kategori-navne
  - `format_cookies()` – Formaterer cookie-liste
  - `count_consents()` – Tæller antal samtykker for et fingerprint
  - Anonymiserer IP i eksport (`[anonymiseret]`)
- Oprettet `includes/class-privacy-policy.php` (ny klasse):
  - `add_privacy_policy_content()` – Tilføjer cookiepolitik til WordPress Privacy Policy
  - `generate_policy_content()` – Auto-genererer dansk HTML-cookiepolitik
  - `render_cookie_table()` – Bygger tabel over cookies pr. kategori
  - `get_category_descriptions()` – GDPR-tekster pr. kategori (dansk)
  - `get_category_banner_texts()` – Kortfattede banner-tekster

#### Fase 7 – Internationalisering (i18n)
- Oprettet `languages/cookiedk-da_DK.pot` – Oversættelsesskabelon med alle tekststrenge
- Oprettet `languages/cookiedk-da_DK.po` – Dansk oversættelse (kildekode)
- Oprettet `languages/cookiedk-da_DK.mo` – Kompileret binær oversættelsesfil
- Oprettet `includes/class-translations.php` (ny klasse):
  - `get_banner_strings()` – Banner-tekster
  - `get_settings_panel_strings()` – Indstillingspanel-tekster
  - `get_category_names()` – Kategori-navne (dansk)
  - `get_category_descriptions()` – Kategori-beskrivelser (dansk)
  - `get_admin_strings()` – Admin-interface-tekster
  - `get_error_strings()` – Fejlmeddelelser
  - `get_privacy_policy_strings()` – Privacy policy-tekster
  - `get_data_export_strings()` – Data-export-tekster
  - `get_deletion_strings()` – Samtykke-sletnings-tekster
  - `get_cookie_descriptions()` – Danske beskrivelser for 20+ kendte cookies
  - `get()` – Hjælpemetode til at hente enkelt tekststreng
- Oprettet `TRANSLATION.md` – Komplet vejledning til oversættelser med eksempler

### Ændret
- `cookiedk.php`: Indlæser nu `class-consent-export.php`, `class-privacy-policy.php` og `class-translations.php`
- `cookiedk.php`: Initialiserer `CookieDK_Privacy_Policy` og kalder `$privacy->init()`
- `includes/class-gdpr-compliance.php`: Tilføjet `$exporter`- og `$valid_categories`-properties
- `README.md`: Opdateret mappestruktur, funktionsliste, GDPR-integration og i18n-sektioner

#### Fase 4 – Banner & Brugergrænsefladen
- Oprettet `public/class-frontend.php` med:
  - Registrering af banner-scripts og styles via `wp_enqueue_scripts`
  - Banner-rendering via `wp_footer` hook
  - Dynamisk inline CSS med brugerdefinerede farver
  - Overgivelse af indstillinger og i18n til JavaScript via `wp_localize_script`
- Oprettet `public/assets/css/banner.css` med:
  - CSS Custom Properties til nem tilpasning
  - Responsive design (mobil-først, breakpoints ved 782px og 480px)
  - Banner-positioner: bund (standard), top og side
  - Indstillingspanel med kategori-toggles og cookie-liste
  - Toggle-switch komponent (CSS-only)
  - Dark mode support via `prefers-color-scheme`
  - WCAG 2.1 AA tilgængelighed (fokus-ringe)
- Oprettet `public/assets/js/banner.js` med:
  - Banner-visning/skjulning
  - Indstillingspanel åbne/luk
  - Fokus-trap i modal-dialog
  - Tastaturnavigation (Escape-tast)
  - ARIA live region til skærmlæser-annoncering
  - Ekspander/kollaps cookie-lister pr. kategori
- Oprettet `public/assets/js/cookie-consent.js` med:
  - LocalStorage-håndtering med udløbsdato
  - `acceptAll()` – acceptér alle kategorier
  - `acceptNecessaryOnly()` – kun nødvendige cookies
  - `saveCustomConsent()` – gem brugerdefineret samtykke
  - `getCurrentConsent()` / `isCategoryAccepted()` – læs nuværende samtykke
  - AJAX POST til `cookiedk_log_consent` for server-side logning
  - Custom DOM-events: `cookiedk:consent` og `cookiedk:consent:{kategori}`
  - Data-attributter på `<body>` til CSS-targeting
- Oprettet `public/templates/banner.php` med:
  - HTML-struktur med ARIA `dialog`-rolle
  - Tre knapper: Accepter alle, Indstillinger, Kun nødvendige
  - Link til cookiepolitik (valgfrit)
  - Live region til skærmlæsere
- Oprettet `public/templates/settings-panel.php` med:
  - Modal-dialog med ARIA `dialog`-rolle og fokus-trap
  - Toggle-switches pr. kategori (nødvendige låst aktive)
  - Ekspanderbare cookie-lister med navn, udbyder, varighed og beskrivelse
  - "Gem indstillinger" og "Accepter alle"-knapper

#### Fase 5 – Admin-interface
- Oprettet `admin/class-admin-menu.php` med:
  - Registrering af admin-side under **Indstillinger → CookieDK**
  - Capability-check (`manage_options`) på alle sider
  - Betinget indlæsning af admin-assets
- Oprettet `admin/class-admin-page.php` med:
  - Tab-baseret navigation (Dashboard, Cookies, Indstillinger, Samtykker, Test)
  - `handle_settings_form()` – behandler indstillingsformular med nonce-verificering
  - `handle_cookie_form()` – tilføj/rediger cookie med nonce-verificering
  - AJAX-endpoints:
    - `cookiedk_update_cookie` – opdater cookie-data
    - `cookiedk_delete_cookie` – slet en cookie
    - `cookiedk_export_cookies` – eksportér cookies som JSON
    - `cookiedk_save_settings` – gem indstillinger via AJAX
  - Fuldstændig input-sanitering og nonce-beskyttelse på alle endpoints
- Oprettet `admin/assets/css/admin.css` med:
  - Tab-navigation styling
  - Statistik-kort layout (CSS Grid)
  - Tabel-styling med kategori-badges
  - Formular-styling
  - Modal-dialog CSS
  - Notifikations-komponent
  - Responsive design (tablet og mobil)
- Oprettet `admin/assets/js/admin.js` med:
  - Modal-dialog åben/luk med fokus-håndtering og Escape-tast
  - AJAX cookie-redigering (inline i tabel)
  - AJAX cookie-sletning med bekræftelsesdialog
  - JSON-eksport til fil via Blob API
  - Tabel-sortering efter kolonne
  - Notifikations-system (success/error)
  - LocalStorage nulstil-funktion (test-mode)
- Oprettet `admin/partials/dashboard.php` med statistik-overblik
- Oprettet `admin/partials/cookies.php` med cookie-tabel, rediger- og slet-funktioner samt "Tilføj cookie"-modal
- Oprettet `admin/partials/settings.php` med alle indstillingsfelter og nonce-beskyttet formular
- Oprettet `admin/partials/consent-log.php` med samtykketabel, datofilter og statistik
- Oprettet `admin/partials/test.php` med banner-preview-link, reset-funktion og cookie-detektion-status

#### Opdateringer
- Opdateret `cookiedk.php` til at indlæse og initialisere Fase 4-5 klasser
- Opdateret standardindstillinger med `primary_color` og `secondary_color`
- Opdateret `README.md` med banner-konfiguration og opdateret mappestruktur

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
