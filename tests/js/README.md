# Klaro Geo JavaScript tests

Jest tests for the scripts in `js/`. Run them from the repository root with `npx jest` (one file: `npx jest tests/js/test-consent-mode.js`).

Every test loads the real script and checks what it does. Do not copy a function from `js/` into a test and test the copy.

- `helpers/page.js` builds the page the scripts expect: `setUpPage()` provides what PHP's inline script defines (`window.dataLayer`, `window.gtag`, `window.klaroGeo`, `window.klaroConsentData`), `loadBootstrap()` loads `js/klaro-geo-bootstrap.js`, and `loadConsentScript()` loads `js/klaro-geo.js` with a Klaro manager.
- `setup.js` runs before each file: it replaces the log helpers with mocks and loads `js/klaro-geo-events.js`.

The admin scripts (`js/klaro-geo-admin*.js`) have no Jest tests; the Templates page is covered by the Playwright suite in `e2e/`.
