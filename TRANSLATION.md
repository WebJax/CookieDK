# Oversættelsesvejledning – CookieDK

Denne vejledning beskriver, hvordan du bidrager med oversættelser til CookieDK WordPress-pluginen.

---

## 📁 Mappestruktur

```
languages/
├── cookiedk-da_DK.pot   # Oversættelsesskabelon (Translation Template)
├── cookiedk-da_DK.po    # Dansk kildetekst & oversættelse
└── cookiedk-da_DK.mo    # Kompileret binær-fil (genereres fra .po)
```

---

## 🌍 Tilføj et nyt sprog

### Trin 1: Kopiér skabelonfilen

Kopiér `.pot`-filen som udgangspunkt for din oversættelse:

```bash
cp languages/cookiedk-da_DK.pot languages/cookiedk-SPROG_LAND.po
```

Eksempler på sprogkoder:
- `de_DE` – Tysk
- `sv_SE` – Svensk
- `nb_NO` – Norsk (Bokmål)
- `fi` – Finsk
- `nl_NL` – Nederlandsk
- `fr_FR` – Fransk

### Trin 2: Redigér `.po`-filen

Åbn filen i en PO-editor (f.eks. [Poedit](https://poedit.net)) eller en teksteditor.

Opdatér følgende i filhovedet:
```
"Language: de_DE\n"
"Last-Translator: Dit Navn <din@email.dk>\n"
"Language-Team: German <dit@email.dk>\n"
```

Oversæt alle `msgstr ""`-strenge til dit sprog. Eksempel:

```po
msgid "Vi bruger cookies"
msgstr "Wir verwenden Cookies"

msgid "Accepter alle"
msgstr "Alle akzeptieren"

msgid "Kun nødvendige"
msgstr "Nur notwendige"
```

### Trin 3: Kompilér `.mo`-filen

Brug `msgfmt` til at kompilere `.po` til `.mo`:

```bash
msgfmt languages/cookiedk-de_DE.po -o languages/cookiedk-de_DE.mo
```

Eller brug Poedit, som kompilerer automatisk ved at gemme.

### Trin 4: Test oversættelsen

1. Skift WordPress-sproget til dit nye sprog i **Indstillinger → Generelt**
2. Aktivér pluginen og tjek at teksterne vises korrekt

---

## 🔄 Opdatering af oversættelsesskabelon

Når nye tekststrenge tilføjes til pluginen, skal `.pot`-filen opdateres.

### Med WP-CLI (anbefalet)

```bash
wp i18n make-pot . languages/cookiedk-da_DK.pot --domain=cookiedk
```

### Manuel opdatering

1. Find alle nye `__()`, `_e()`, `_x()`, `_n()`-kald i PHP-filerne
2. Tilføj dem til `.pot`-filen som nye `msgid`-entries
3. Opdatér eksisterende `.po`-filer med `msgmerge`:

```bash
msgmerge --update languages/cookiedk-da_DK.po languages/cookiedk-da_DK.pot
```

4. Kompilér `.mo`-filen igen

---

## 📝 i18n-funktioner i pluginen

CookieDK bruger WordPress' standard i18n-funktioner:

| Funktion | Brug | Eksempel |
|----------|------|---------|
| `__()` | Hent oversat tekst | `__( 'Accepter alle', 'cookiedk' )` |
| `_e()` | Echo oversat tekst | `_e( 'Gem indstillinger', 'cookiedk' )` |
| `_x()` | Context-aware tekst | `_x( 'Indstillinger', 'menu', 'cookiedk' )` |
| `_n()` | Pluralisering | `_n( '%d cookie', '%d cookies', $n, 'cookiedk' )` |
| `esc_html__()` | Escaped hentning | `esc_html__( 'Cookiepolitik', 'cookiedk' )` |
| `esc_attr_e()` | Escaped echo i attr | `esc_attr_e( 'Luk', 'cookiedk' )` |
| `wp_kses_post()` | Tillad HTML i tekster | `wp_kses_post( $html_content )` |

Tekstdomænet er altid `cookiedk`.

---

## 🏗 Byggeværktøjer

### Forudsætninger

- [gettext](https://www.gnu.org/software/gettext/) (`msgfmt`, `msgmerge`, `msginit`)
- [WP-CLI](https://wp-cli.org/) (valgfrit, til automatisk ekstraktion)
- [Poedit](https://poedit.net/) (grafisk editor, valgfrit)

### Installation af gettext (Linux/macOS)

```bash
# Ubuntu/Debian
sudo apt-get install gettext

# macOS med Homebrew
brew install gettext
```

### Kompilér alle .po-filer

```bash
for f in languages/*.po; do
    msgfmt "$f" -o "${f%.po}.mo"
done
```

---

## 📋 Bidrag

1. Fork repositoriet på GitHub
2. Opret din oversættelsesfil i `languages/`
3. Test oversættelsen
4. Åbn en Pull Request med `.po` OG `.mo`-filen

---

## 🔑 Vigtige tekststrenge

Disse strenge er særlig vigtige for brugervenlighed:

### Cookie-banner
- `Vi bruger cookies` – Overskrift
- `Accepter alle` – Primær knap
- `Kun nødvendige` – Sekundær knap
- `Indstillinger` – Åbner panel

### Kategorier
- `Nødvendige` – Altid aktive cookies
- `Funktionelle` – Præference-cookies
- `Analyser` – Statistik-cookies
- `Marketing` – Annonce-cookies

### GDPR-beskeder
- `Samtykke gemt.` – Bekræftelse
- `Samtykke tilbagekaldt.` – Efter sletning
- `[anonymiseret]` – Vises i stedet for IP

---

## ❓ Spørgsmål

Åbn en [GitHub Issue](https://github.com/WebJax/CookieDK/issues) eller kontakt [info@webjax.dk](mailto:info@webjax.dk).
