# CookieDK – GDPR Cookie-banner til WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange)](CHANGELOG.md)

**CookieDK** er en GDPR-compliant WordPress-plugin, der automatisk detekterer cookies på dit website og præsenterer dem i et brugervenligt dansk cookie-banner.

---

## 🍪 Funktioner

- **Auto-detektion** – Finder og klassificerer cookies automatisk (server-side og client-side)
- **Dansk** – Alle tekster og cookie-beskrivelser på dansk
- **GDPR-compliant** – Overholder EU's GDPR og den danske cookielovgivning
- **Kategorier** – Nødvendige, Funktionelle, Analyser og Marketing
- **Cookie-banner** – Responsivt banner med top/bund/side-position og dark mode
- **Indstillingspanel** – Toggle-baseret kategoriudvælgelse med cookie-detaljer
- **Admin-interface** – Dashboard, cookie-management, indstillinger, samtykkelogning og test
- **Samtykkelogning** – Dokumenterer bruger-samtykker med tidsstempel
- **Data-export** – Integreret med WordPress' privatlivsværktøjer
- **Auto-anonymisering** – IP-adresser anonymiseres automatisk efter 30 dage
- **Tilgængelighed** – WCAG 2.1 AA kompatibel (ARIA labels, keyboard navigation)
- **WordPress Coding Standards** – Fuldt kompatibel med WPCS

---

## 📋 Krav

| Krav             | Minimum |
|------------------|---------|
| WordPress        | 5.0     |
| PHP              | 7.4     |
| MySQL            | 5.6     |

---

## 🚀 Installation

### Manuel installation

1. Download den seneste version fra [GitHub Releases](https://github.com/WebJax/CookieDK/releases)
2. Log ind i din WordPress-adminpanel
3. Gå til **Plugins → Tilføj ny**
4. Klik på **Upload plugin**
5. Vælg den downloadede `.zip`-fil og klik **Installer nu**
6. Aktivér pluginen via **Plugins → Installerede plugins**

### Via FTP

1. Download og udpak `.zip`-filen
2. Upload mappen `cookiedk/` til `/wp-content/plugins/`
3. Aktivér pluginen i WordPress-adminpanelet

### Via WP-CLI

```bash
wp plugin install cookiedk.zip --activate
```

---

## ⚙️ Konfiguration

Efter aktivering oprettes database-tabellerne automatisk, og du kan konfigurere pluginen:

1. Gå til **Indstillinger → CookieDK** i adminpanelet
2. Konfigurér via de fem faneblade:
   - **Dashboard** – Oversigt med statistik over detekterede cookies og samtykker
   - **Cookies** – Administrér detekterede cookies (rediger, tilføj, slet, eksportér)
   - **Indstillinger** – Konfigurér banner og GDPR-indstillinger:
     - **Banner-position** – Top, bund eller side
     - **Farvetema** – Lyst, mørkt eller automatisk (følger system-præference)
     - **Primær farve** – Baggrundsfarve for "Accepter alle"-knappen
     - **Sekundær farve** – Hover-farve for knapper
     - **Cookiepolitik-URL** – Link til din cookiepolitik-side (vises i banneret)
     - **Samtykkefrist** – Antal dage samtykket bevares (standard: 365)
     - **Aktive kategorier** – Aktiver/deaktiver funktionelle, analytiske og marketing-cookies
     - **IP-anonymisering** – Anonymisér IP-adresser efter 30 dage
     - **Logopbevaring** – Antal dage samtykkelogen bevares
   - **Samtykker** – Oversigt over samtykkeloggen med datofilter
   - **Test** – Test banner-visning og nulstil samtykker

### Banner-konfiguration

Banneret vises automatisk for besøgende der ikke har givet samtykke. Det understøtter tre knapper:
- **Accepter alle** – Aktiverer alle cookie-kategorier
- **Indstillinger** – Åbner et detaljeret indstillingspanel
- **Kun nødvendige** – Accepterer kun teknisk nødvendige cookies

Samtykket gemmes i `localStorage` med det konfigurerede antal dages udløb.

---

## 🗂️ Mappestruktur

```
cookiedk/
├── cookiedk.php                        # Hoved-plugin-fil
├── uninstall.php                       # Rydning ved afinstallation
├── README.md                           # Denne fil
├── CHANGELOG.md                        # Ændringslog
├── PROJEKTPLAN.md                      # Projektplan
├── includes/
│   ├── class-cookie-detector.php       # Cookie-detektion
│   ├── class-cookie-storage.php        # Database-lagring & CRUD
│   └── class-gdpr-compliance.php       # GDPR-overholdelse
├── admin/
│   ├── class-admin-menu.php            # Admin-menu registrering
│   ├── class-admin-page.php            # Admin-side renderer & AJAX-handlers
│   ├── assets/
│   │   ├── css/admin.css               # Admin CSS
│   │   └── js/admin.js                 # Admin JavaScript
│   └── partials/
│       ├── dashboard.php               # Dashboard-statistik
│       ├── cookies.php                 # Cookie-management
│       ├── settings.php                # Plugin-indstillinger
│       ├── consent-log.php             # Samtykkelogning
│       └── test.php                    # Test-side
├── public/
│   ├── class-frontend.php              # Frontend banner-rendering
│   ├── assets/
│   │   ├── css/banner.css              # Banner CSS (responsivt, dark mode)
│   │   └── js/
│   │       ├── banner.js               # Banner interaktion & ARIA
│   │       └── cookie-consent.js       # Samtykke-logik & localStorage
│   └── templates/
│       ├── banner.php                  # Banner HTML-template
│       └── settings-panel.php          # Indstillingspanel HTML-template
├── languages/
│   └── cookiedk-da_DK.pot              # Oversættelsesskabelon
└── assets/                             # Delte ressourcer
```

---

## 🗄️ Database

Pluginen opretter to tabeller ved aktivering:

### `wp_cookiedk_cookies`
Gemmer detekterede og konfigurerede cookies.

| Kolonne          | Type         | Beskrivelse                        |
|------------------|--------------|------------------------------------|
| `id`             | BIGINT       | Primærnøgle                        |
| `name`           | VARCHAR(255) | Cookie-navn                        |
| `category`       | VARCHAR(50)  | Kategori (necessary/functional/...) |
| `description_da` | TEXT         | Dansk beskrivelse                  |
| `duration`       | VARCHAR(100) | Levetid                            |
| `provider`       | VARCHAR(255) | Udbyder                            |
| `source`         | VARCHAR(20)  | Kilde (server/client/manual)       |
| `necessary`      | TINYINT(1)   | Er nødvendig?                      |
| `detected_at`    | DATETIME     | Første gangs-detektion             |
| `last_updated`   | DATETIME     | Sidst opdateret                    |
| `enabled`        | TINYINT(1)   | Aktiv?                             |

### `wp_cookiedk_consent_log`
Logger bruger-samtykker.

| Kolonne              | Type         | Beskrivelse                    |
|----------------------|--------------|--------------------------------|
| `id`                 | BIGINT       | Primærnøgle                    |
| `user_fingerprint`   | VARCHAR(64)  | Anonymt bruger-fingerprint     |
| `consent_data`       | LONGTEXT     | JSON med samtykke-detaljer     |
| `consent_timestamp`  | DATETIME     | Tidsstempel for samtykket      |
| `ip_address`         | VARCHAR(45)  | IP-adresse (anonymiseres)      |
| `ip_anonymized`      | TINYINT(1)   | Er IP anonymiseret?            |
| `user_agent`         | VARCHAR(500) | Browser user-agent             |

---

## 🔐 Sikkerhed

- **Nonce-verificering** på alle AJAX-kald og formularer
- **Sanitering** af alt bruger-input med WordPress-funktioner
- **Escaping** af alt output
- **Capability-checks** med `current_user_can()`
- **Ingen hardcodede hemmeligheder**
- **IP-anonymisering** efter 30 dage (konfigurerbar)

---

## 📖 WordPress Hooks

### Actions
```php
// Tilføj egne cookies til auto-detektion.
add_filter( 'cookiedk_known_cookies', function( $cookies ) {
    $cookies['my_custom_cookie'] = array(
        'category'       => 'functional',
        'description_da' => 'Min tilpassede cookie.',
        'duration'       => '1 år',
        'provider'       => 'Mit website',
        'necessary'      => false,
    );
    return $cookies;
} );
```

---

## 🇩🇰 Dansk Cookielovgivning

Pluginen følger:
- **GDPR** (EU 2016/679)
- **ePrivacy-direktivet** (Cookie-direktivet)
- **Datatilsynets retningslinjer** ([datatilsynet.dk](https://www.datatilsynet.dk))

### GDPR-krav der overholdes:
- ✅ Eksplicit samtykke før ikke-nødvendige cookies
- ✅ Klar og forståelig information (dansk)
- ✅ Nem tilbagekaldelse af samtykke
- ✅ Dokumentation af samtykker (logning)
- ✅ Retten til at blive glemt (data-sletning)
- ✅ Data-minimerering (anonymisering af IP)

---

## 🤝 Bidrag

Bidrag er velkomne! Se [PROJEKTPLAN.md](PROJEKTPLAN.md) for kommende funktioner.

1. Fork repositoriet
2. Opret en feature-branch (`git checkout -b feature/min-funktion`)
3. Commit dine ændringer (`git commit -m 'Tilføj min funktion'`)
4. Push til branchen (`git push origin feature/min-funktion`)
5. Åbn en Pull Request

---

## 📄 Licens

GPL v2 eller nyere – Se [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 👨‍💻 Forfatter

**WebJax**  
[https://webjax.dk](https://webjax.dk)

---

## 📞 Support

- GitHub Issues: [github.com/WebJax/CookieDK/issues](https://github.com/WebJax/CookieDK/issues)
