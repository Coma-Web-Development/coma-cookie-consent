# Investigation notes

Date: 2026-11-13

Scope: read-only inspection of `coma-cookie-consent`, PR #2, GitHub Code Scanning, and `https://holship.comadev.lv/`.

## Live language result

- The repository is checked out at merge commit `f8edc4d` (`v1.7.5`).
- The live page uses plugin assets with `ver=1.7.5`.
- The live banner HTML is English: `This website uses cookies`.
- The live localized settings contain `"lang":"en"`.
- The live `frontend/js/consent.js` SHA-256 is identical to the repository file.
- A request with English, Latvian, or Russian `Accept-Language` produced the same English banner. This plugin does not use the browser `Accept-Language` header for language selection.

The Latvian banner was not reproducible in the current public response. If it appears in a browser, check the exact URL, cached HTML, and whether the browser is showing an older page response. The current single-language fallback is controlled by the plugin's `default_lang` option.

## PR #2 bot review

PR #2 is already merged. The Codex review was run against commit `8ea24a4d3f`. Its unresolved P2 finding is on `frontend/class-frontend.php:394`.

The iframe rewrite uses `#\bsrc\s*=#i`. Because a hyphen is a word boundary, this also matches the `src=` suffix in `data-src=`. For example:

```html
<iframe src="about:blank" data-src="https://www.facebook.com/plugins/page.php">
```

becomes an iframe with `data-ccc-src="about:blank"` and `data-data-ccc-src="https://..."`. After consent, the lazy-loading `data-src` attribute is no longer present, so the widget can stay blank.

The same regex appears in the script rewrite at `frontend/class-frontend.php:369` and should be reviewed together with the iframe fix.

## Current Code Scanning alerts

The current `main` branch has four open alerts:

- Alert 1, medium: `.github/workflows/deploy.yml:13`. The deploy workflow has no explicit `permissions` block. Minimum fix: grant only `contents: read` to the workflow or deploy job.
- Alert 2, high: `frontend/js/consent.js:258`. `replace('*', '')` removes only the first wildcard. Current cookie display names contain one wildcard, so this is mainly a future matching bug rather than a demonstrated security exploit.
- Alert 4, high: `frontend/js/consent.js:383`. A DOM attribute is copied into an iframe `src` without validating the URL scheme.
- Alert 5, high: `frontend/js/consent.js:367`. A DOM attribute is copied into a script `src` without validating the URL scheme.

Alert 3 is fixed and belongs to the older `v1.6.3` commit.

The two DOM XSS alerts are reachable through the server-side tracker/iframe rewriting path. Exploitability still depends on an attacker being able to influence a matching HTML tag and on the visitor granting the relevant consent. They should nevertheless be fixed as real unsafe-sink findings: accept only intended `http:`/`https:` URLs before assigning `script.src` or `iframe.src`, and preserve the original lazy-loading attributes.

Additional deployment observations not raised by these alerts: the workflow learns the SSH host key with `ssh-keyscan` on every run instead of pinning a known fingerprint, and deploys to `root`. Review both before relying on this workflow for production releases.

No production or GitHub changes were made during this inspection.
