# CookieDK - Projektplan

## Overblik
CookieDK er en WordPress-plugin til GDPR-overholdelse, der automatisk detekterer cookies på websitet og præsenterer dem på dansk i en brugervenlig cookie-banner. Pluginen følger WordPress Coding Standards (WPCS) og danske GDPR-regler.

---

## Fase 1: Opsætning & Grundlæggende Struktur

### 1.1 Pluginarkitektur
```
cookiedk/
├── cookiedk.php                 # Hovedfil - Pluginheader
├── readme.txt                   # Plugin information
├── includes/
│   ├── class-cookie-detector.php       # Cookie-detektion
│   ├── class-cookie-storage.php        # Database-lagring
│   ├── class-banner.php                # Banner-rendering
│   └── class-gdpr-compliance.php       # GDPR-validering
├── admin/
│   ├── class-admin-menu.php            # Indstillinger
│   ├── class-admin-page.php            # Admin-interface
│   └── assets/
│       ├── css/admin.css
│       └── js/admin.js
├── public/
│   ├── class-frontend.php              # Frontend-rendering
│   └── assets/
│       ├── css/banner.css
│       ├── js/banner.js
│       └── js/cookie-consent.js
├── languages/
│   ├── cookiedk-da_DK.pot             # Oversættelsesbase
│   └── cookiedk-da_DK.po              # Dansk oversættelse
└── uninstall.php                # Rydning ved afinstallation
```

### 1.2 Pluginheader (cookiedk.php)
- Plugin Name, Description, Version, Author (dansk)
- Minimum WordPress version: 5.0
- Requires PHP: 7.4+
- License: GPL v2 eller senere
- Text Domain & Domain Path

### 1.3 Afhængigheder
- WordPress 5.0+
- PHP 7.4+
- Ingen tredjeparts PHP-afhængigheder (minimal footprint)

---

## Fase 2: Cookie-Detektion

### 2.1 Cookie-Detektionsmekanisme
Pluginen skal:

#### A. **Server-side Detektion**
- Scannings WordPress-hooks for `setcookie()` kald
- Logger cookies fra almindelige kilder:
  - WordPress core (wordpress_*, wordpress_logged_in_*)
  - WooCommerce (woocommerce_*)
  - Jetpack, Akismet osv.
  - Selv-angivne cookies via filter

#### B. **Client-side Detektion**
- JavaScript scanner document.cookie på sideload
- Finder cookies fra tredjeparts scripts (Google Analytics, Facebook Pixel osv.)
- Sender data tilbage til server via AJAX

#### C. **Cookie-Data Struktur**
```php
[
    'name' => 'ga_id',
    'category' => 'analytics',  // necessary, functional, analytics, marketing
    'description' => 'Google Analytics bruger-ID',
    'duration' => '2 år',
    'provider' => 'Google',
    'source' => 'server|client',
    'necessary' => false,
]
```

### 2.2 Cookies efter Kategori

**Nødvendige** (altid aktive, ikke-samtykke påkrævet):
- Session-cookies
- Login-cookies
- CSRF-protection cookies

**Funktionalitet** (forbedrer brugeroplevelse):
- Sprog-præferencer
- Tema-valg
- Bruger-indstillinger

**Analyser** (måler brug):
- Google Analytics
- Matomo
- Site-statistik

**Marketing** (målrettet reklamer):
- Facebook Pixel
- Google Ads
- Remarketing

---

## Fase 3: Cookie-Lagring & Håndtering

### 3.1 Database-struktur
```sql
CREATE TABLE IF NOT EXISTS cookiedk_cookies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,
    description_da TEXT,
    duration VARCHAR(100),
    provider VARCHAR(255),
    source VARCHAR(20),
    necessary BOOLEAN DEFAULT FALSE,
    detected_at DATETIME,
    last_updated DATETIME,
    enabled BOOLEAN DEFAULT FALSE,
    wp_option_name VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS cookiedk_consent_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_fingerprint VARCHAR(255),
    consent_data JSON,
    timestamp DATETIME,
    ip_address VARCHAR(45)
);
```

### 3.2 WordPress Options
- `cookiedk_detected_cookies` - JSON af detekterede cookies
- `cookiedk_settings` - Plugin-indstillinger
- `cookiedk_cookie_descriptions` - Brugerdefinerede beskrivelser

---

## Fase 4: Banner & Brugergrænsefladen

### 4.1 Banner-design (Dansk, GDPR-compliant)
```html
┌─────────────────────────────────────┐
│ 🍪 Vi bruger cookies                │
│                                     │
│ Vi bruger cookies til at forbedre   │
│ din oplevelse på siden.             │
│                                     │
│ [Accepter alle] [Indstillinger]     │
│                                     │
│ Læs vores cookiepolitik →           │
└─────────────────────────────────────┘
```

### 4.2 Banner-display Logik
- Vises kun hvis bruger ikke har samtykket
- Bruges localStorage til at gemme samtykke (1 år)
- Cookies accepteres først efter bruger-samtykke
- Nødvendige cookies sættes uafhængigt

### 4.3 Indstillingspanel
```
┌─────────────────────────────────────┐
│ Dine cookie-indstillinger          │
│                                     │
│ ☑ Nødvendige cookies                │
│  (altid aktive)                     │
│  └─ Beskrivelse af nødvendige      │
│                                     │
│ ☐ Funktionelle cookies             │
│  └─ Sproglokalisering, tema osv.   │
│                                     │
│ ☐ Analyser                          │
│  └─ Google Analytics, Matomo        │
│                                     │
│ ☐ Marketing                         │
│  └─ Remarketing, Facebook Pixel     │
│                                     │
│ [Gem indstillinger] [Accepter alle] │
└─────────────────────────────────────┘
```

### 4.4 Frontend-template
- Responsive design (mobil-først)
- Tilgængelighed (WCAG 2.1 AA)
- Dansk copywriting
- Link til cookiepolitik

---

## Fase 5: Admin-interface

### 5.1 Admin-menu
**Indstillinger → Cookie-indstillinger**

### 5.2 Admin-sider

#### Dashboard
- Status på plugin
- Antal fundne cookies
- Seneste samtykker
- Quick-links

#### Cookies (Håndtering)
- Tabel af detekterede cookies
- Redigering af beskrivelser (da)
- Tilføjelse af cookies manuelt
- Eksport/Import af cookies

#### Indstillinger
- Banner-position (top, bottom, side)
- Farvetema
- Acceptsedel-link
- Cookiepolitik-link
- Beholdningsperiode for samtykker
- Aktivering/deaktivering af kategorier

#### Samtykkelogning
- Log over samtykker
- GDPR Data-export
- Statistikker

#### Test
- Test af banner i preview
- Test af cookie-detektion

---

## Fase 6: GDPR-compliance

### 6.1 GDPR-krav
- ✅ Eksplicit samtykke før non-nødvendige cookies
- ✅ Klar information om cookiebrug
- ✅ Nem tilbagekaldelse af samtykke
- ✅ Dokumentation af samtykker
- ✅ Retten til at blive glemt
- ✅ Cookies i egen datapolitik

### 6.2 Implementering
- Samtykker gemmes (fingerprint, timestamp, IP)
- Export/import af bruger-data
- Sletning af konti sletter samtykker
- Privacy-venlig fingerprinting (ingen PII)

### 6.3 Logging
- Samtykke-logs til dokumentation
- Anonymisering af IP efter 30 dage
- GDPR-export funktion

---

## Fase 7: Internationalisering (i18n)

### 7.1 Dansk Sprog
- Alle strenge skal være oversættelige
- Brug `__()`, `_e()`, `wp_kses_post()` for output
- Tekstdomæne: `cookiedk`

### 7.2 Ordbog (da_DK)
- Banner-tekster
- Cookie-beskrivelser
- Admin-tekster
- Fejlmeddelelser

### 7.3 Fremtid
- Tilføj anden sprog via .po-filer

---

## Fase 8: Udvikling & Testing

### 8.1 Coding Standards (WPCS)
- Brug WordPress Coding Standards
- PHPStan Level 5 minimum
- Indentation: 1 tab = 4 spaces
- Navngivning: snake_case for funktioner
- Sikkerhed: nonce-verificering, sanitering, escaping

### 8.2 Testing
- Unit-tests (PHPUnit)
- Integration-tests
- Browser-testing (Chrome, Firefox, Safari, Edge)
- Mobile-testing
- WCAG-testing (axe DevTools)

### 8.3 Dokumentation
- README.md (installation, konfiguration)
- CHANGELOG.md
- FAQ.md
- Kodedokumentation (PHPDoc)

---

## Fase 9: Sikkerhed

### 9.1 Sikkerhedsforanstaltninger
- Nonce-verificering på alle formularer
- Sanitering af alt bruger-input
- Escaping af alt output
- Capable-checks (`current_user_can()`)
- Rate-limiting på AJAX-kald
- Ingen hardcodede secrets

### 9.2 Data-behandling
- Minimering af data
- SSL/TLS-kryptering anbefalet
- Lokal lagring af samtykker
- Anonymisering af IP-adresser

---

## Fase 10: Release & Distribution

### 10.1 Version 1.0
- MVP med grundlæggende funktioner
- Dansk sprog fuldt understøttet
- GDPR-compliant
- Dokumentation komplet

### 10.2 Distribution
- WordPress.org plugin-directory (ønsket)
- GitHub releases
- Auto-opdater via WordPress

### 10.3 Vedligeholdelse
- Sikkerhedsopdateringer
- Kompatibilitetsopdateringer (WordPress/PHP)
- Fejlrettelser

---

## Tidsplan (Estimeret)

| Fase | Opgaver | Timer | Status |
|------|---------|-------|--------|
| 1    | Setup & struktur | 8 | ⬜ |
| 2    | Cookie-detektion | 16 | ⬜ |
| 3    | Lagring & DB | 12 | ⬜ |
| 4    | Banner & UI | 20 | ⬜ |
| 5    | Admin-interface | 24 | ⬜ |
| 6    | GDPR compliance | 12 | ⬜ |
| 7    | i18n & danske tekster | 8 | ⬜ |
| 8    | Testing & QA | 20 | ⬜ |
| 9    | Sikkerhed review | 12 | ⬜ |
| 10   | Release & dokumentation | 8 | ⬜ |
| **Total** | | **140 timer** | |

---

## Prioritering (MVP)

**Fase 1-3**: Grundlæggende cookie-detektion
**Fase 4**: Simpel banner (dansk)
**Fase 5**: Admin-interface (basis)
**Fase 6**: GDPR-compliance check
**Fase 7**: Dansk oversættelse
**Fase 8-10**: Test, sikkerhed, release

---

## Væsentlige features

✅ Auto-detektion af cookies  
✅ Dansk banner  
✅ Kategori-valg (nødvendig, funktionel, analytics, marketing)  
✅ GDPR-dokumentation  
✅ Admin-panel  
✅ WPCS-kompatibel  
✅ Minimal afhængigheder  
✅ Simpel, intuitiv UI  

---

## Links & Ressourcer

- [GDPR EU Cookie-direktiv](https://gdpr.eu/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Plugin-development](https://developer.wordpress.org/plugins/)
- [Danske Cookies & GDPR](https://www.datatilsynet.dk/)
- [Cookiepolitik-eksempler](https://www.iubenda.com/)

---

**Oprettet:** 1. August 2026  
**Version:** 0.1 (Projektplan)  
**Status:** Klar til udvikling
