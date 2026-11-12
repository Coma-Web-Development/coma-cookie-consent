# Coma Cookie Consent

GDPR-compliant cookie consent banner for WordPress with **Google Consent Mode v2**.
Multilingual (Latvian / Russian / English / Lithuanian / Estonian), auto-detecting the visitor's language.

- **Version:** 1.7.5
- **Author:** [Coma](https://coma.lv)

## Features

- Consent banner + preferences modal (necessary / analytics / marketing).
- Google Consent Mode v2 — denies storage by default, updates on consent.
- Auto-detected cookie table: scans `document.cookie` and loaded tracker scripts
  (GA, Facebook, Google Ads, TikTok, LinkedIn, reCAPTCHA, WooCommerce, Sourcebuster).
- **Open Cookie Database** fallback — classifies ~2,200 additional third-party cookies.
- **Multilingual** chrome and content in LV / RU / EN / LT / ET.
- Configurable colour palette (accent, accent 2, background, text) — **light by default**, inherits site fonts.
- Floating "revisit" button to reopen preferences.
- Optional alignment with the theme's content container (`align_selector`, e.g. `#header_main>.container` on Enfold).
- Optional consent-aware `gtag.js` loader (off by default).
- **Tracker blocking** — holds non-Google trackers until consent.
- **Server-side consent log**, configurable duration + re-prompt, and region gating.

## Compliance (Consent Mode v2 and beyond)

- **Google Consent Mode v2** — emits all four signals (`analytics_storage`, `ad_storage`,
  `ad_user_data`, `ad_personalization`) plus `functionality_storage` / `security_storage`,
  default-denied before tags fire, `wait_for_update`, `ads_data_redaction`, and updates on consent.
- **Tracker blocking** (`block_trackers`, default on) — non-Google tracker `<script>` tags
  (Meta, TikTok, LinkedIn, Pinterest, X, Snapchat, Hotjar, Clarity, Yandex) are rewritten to
  `type="text/plain"` server-side and re-activated by category once the visitor consents.
  Google tags are **not** blocked by default (Consent Mode handles them). Extend the list via the
  `ccc_block_signatures` filter. WooCommerce order attribution (Sourcebuster `sbjs_*`) is held
  as marketing; draugiem.lv / Facebook plugin iframes are held too (`ccc_block_iframe_signatures`).
  Signatures also match JS-escaped URLs (`https:\/\/…`), e.g. Enfold's injected GA snippet.
- **Strict Google mode** (`block_google`, default off) — also holds GTM, `gtag.js` and
  `analytics.js` until consent. Use it when the GTM container fires non-Google tags
  (Meta, TikTok, Pinterest, Bing, Hotjar…) without consent checks — Consent Mode cannot stop
  those. GTM loads only once **both** analytics and marketing are granted (compound category
  `analytics+marketing`, changeable via the `ccc_gtm_category` filter); gtag.js / analytics.js
  need analytics; Google Ads (`AW-`) needs marketing.
- **YouTube privacy mode** (`youtube_nocookie`, default off) — rewrites YouTube embeds to
  `youtube-nocookie.com`.
- **Configurable duration + re-prompt** — `consent_duration_days` (default 180); bump
  `consent_version` to force everyone to re-consent.
- **Server-side consent log** — one row per choice in `{prefix}ccc_consent_log`
  (timestamp, choices, version, language, action, **salted IP hash** — no raw IP).
  Written via a public REST route `POST /wp-json/coma-cc/v1/log`.
- **Region gating** — `gating_region`: `global` (default) or `eea`. In EEA mode the
  Consent Mode defaults are region-scoped (granted baseline + denied for EEA/UK) and the
  banner targets EEA/UK visitors when a country signal (e.g. Cloudflare `CF-IPCountry`,
  or the `ccc_visitor_country` filter) is available.

> Not included: IAB TCF v2.2 / Google-certified-CMP (needed only to serve *personalized
> Google ads* in the EEA). This plugin covers measurement-grade Consent Mode + cookie blocking.

## Cookie classification

Cookies in the preferences table are classified in three layers:

1. **Curated database** (`frontend/js/consent.js`) — common cookies with hand-written,
   localized descriptions and durations. Takes priority.
2. **Open Cookie Database** (`frontend/data/open-cookie-db.json`) — a compact, bundled
   copy of [Open-Cookie-Database](https://github.com/jkwakman/Open-Cookie-Database)
   (~2,200 cookies). **Lazy-loaded only when the preferences modal is first opened**, so it
   never affects initial page load. Categories map to necessary / analytics / marketing.
3. **Fallback** — anything still unknown is disclosed under "Necessary" as *unclassified*.

To refresh the bundled database, regenerate `open-cookie-db.json` from the upstream CSV
(see `ocd-convert.php` in the project notes) and bump the plugin version to bust the cache.

> Open Cookie Database is © its contributors, MIT-licensed.

## Language detection

The active language is resolved per request, in this order:

1. **Polylang** — current language locale, then slug
2. **WPML** — current language `default_locale`, then `ICL_LANGUAGE_CODE`
3. **Configured default** — *Settings → Cookie Consent → Default language*

The multilingual plugin's locale is checked before its code because codes are arbitrary
(e.g. a site cloned from a Latvian install may keep code `lv` for its Estonian language).

Only `lv`, `ru`, `en`, `lt`, `et` are supported; anything unsupported falls back to the default.

> The WordPress **site locale is intentionally not used** for detection. Many
> single-language sites run an English admin locale (`en_US`) while serving
> localized content, so the locale is an unreliable signal. On a single-language
> site the configured default wins; Polylang/WPML override it per request.

## Configuration

**Settings → Cookie Consent** (`/wp-admin/options-general.php?page=coma-cookie-consent`):

- Default/fallback language, banner position, accent color, category toggles, revisit button.
- Google Analytics Measurement ID + optional "load GA from this plugin" toggle.
- Per-language banner title, description, and privacy-policy URL.
  - **Privacy URL auto-detects:** if left blank, the banner uses WordPress' native
    **Privacy Policy page** (Settings → Privacy), localized per language via WPML/Polylang.
- Colours: background/text **default to a light theme**; set Background to apply a custom palette.

> **GA note:** The Consent Mode signal is always sent. Only enable *"Load GA from this
> plugin"* if GA/GTM isn't already loaded elsewhere, or you'll load it twice.

## Adding or editing a language

1. Edit `includes/strings.php` — add the language to `ccc_languages()` and a block in `ccc_strings()`.
2. Edit `frontend/js/consent.js` — add the language key to `LANG`, the `DUR` / `OCD_DESC` maps and each cookie `desc` object.
3. Edit `frontend/class-frontend.php` — add the language to `msg_unclassified()` / `msg_none()`.

## File structure

```
coma-cookie-consent/
├── coma-cookie-consent.php     # bootstrap, defaults, activation
├── uninstall.php               # removes the option on uninstall
├── includes/
│   └── strings.php             # LV/RU/EN translation tables
├── admin/
│   └── class-admin.php         # settings page
└── frontend/
    ├── class-frontend.php      # language detection, banner markup, GCM, GA loader
    ├── css/banner.css
    └── js/consent.js           # consent logic + cookie scanner
```

## Install

Copy the `coma-cookie-consent` folder into `wp-content/plugins/` and activate it in
**Plugins**. Then configure under **Settings → Cookie Consent**.
