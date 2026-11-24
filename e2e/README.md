# E2E Testing for Klaro Geo

End-to-end tests for verifying the integration between WordPress, Klaro library, and Klaro Geo plugin.

## Setup

### 1. Install Playwright Browsers

```bash
npm run e2e:install
```

This installs Chromium and Firefox browsers for testing.

### 2. Start the E2E Environment

```bash
npm run e2e:setup
```

This starts the site if it is not running (`bin/site.sh`, http://localhost:8080) and resets it to a fresh install, so every run starts from the same state. It returns when the site is ready.

## Running Tests

### Run All E2E Tests

```bash
npm run e2e:test
```

### Run Tests with Browser Visible (Debugging)

```bash
npm run e2e:test:headed
```

### Run Tests in Debug Mode (Step Through)

```bash
npm run e2e:test:debug
```

### Run Tests in UI Mode (Interactive)

```bash
npm run e2e:test:ui
```

### Run Specific Test File

```bash
npx playwright test e2e/tests/01-consent-modal-flow.spec.js
```

### Run Tests in Specific Browser

```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
```

## Full E2E Test Cycle

Run tests and clean up automatically:

```bash
npm run e2e:full
```

This will:
1. Start Docker environment
2. Run all E2E tests
3. Stop Docker environment

## Test Reports

After running tests, view the HTML report:

```bash
npm run e2e:report
```

## Test Structure

```
e2e/
├── tests/
│   ├── 01-consent-modal-flow.spec.js   # Basic consent modal functionality
│   ├── 02-geo-detection.spec.js        # Geo-detection and template loading
│   └── 03-consent-receipts.spec.js     # Consent receipt generation
├── helpers/
│   ├── wordpress.js                     # WordPress helper functions
│   └── klaro.js                         # Klaro helper functions
└── README.md                            # This file
```

## What's Tested

### 1. Consent Modal Flow
- ✅ Modal appears on first visit
- ✅ Accept All functionality
- ✅ Decline functionality
- ✅ Consent persistence across page reloads
- ✅ Individual service consent toggles
- ✅ DataLayer event tracking

### 2. Geo-Detection
- ✅ Location detection (mocked)
- ✅ Template loading based on location
- ✅ Geo data in dataLayer
- ✅ Fallback behavior on detection failure
- ✅ Geo data in consent receipts

### 3. Consent Receipts
- ✅ Receipt generation on consent save
- ✅ Required fields in receipt
- ✅ Receipt storage in localStorage
- ✅ Receipt transmission to server
- ✅ Multiple consent changes
- ✅ Receipt limit (max 10)
- ✅ DataLayer integration

## Helper Utilities

### WordPressHelper

Provides utilities for WordPress interactions:

```javascript
const { WordPressHelper } = require('../helpers/wordpress');

const wpHelper = new WordPressHelper(page);
await wpHelper.login('admin', 'password');
await wpHelper.gotoAdminPage('klaro-geo-settings');
```

### KlaroHelper

Provides utilities for Klaro interactions:

```javascript
const { KlaroHelper } = require('../helpers/klaro');

const klaroHelper = new KlaroHelper(page);
await klaroHelper.waitForKlaroLoad();
await klaroHelper.acceptAll();
const consents = await klaroHelper.getConsents();
```

## Troubleshooting

### Tests Timeout

If tests timeout waiting for WordPress:
1. Check Docker containers are running: `docker ps`
2. Check WordPress is accessible: `curl http://localhost:8080`
3. Increase timeout in `playwright.config.js`

### WordPress Not Starting

```bash
# Check container logs
docker logs klaro-geo-site

# Start again from nothing
npm run site:down
npm run e2e:setup
```

### Port 8080 Already in Use

Stop any services using port 8080.

### Tests Fail Intermittently

- Run `npm run e2e:setup` again: it resets the site, in case an interrupted test left its options changed
- Check for network issues
- Verify Docker has enough resources

## Managing the site by hand

```bash
bin/site.sh up        # start (installs WordPress the first time)
bin/site.sh reset     # back to a fresh install
bin/site.sh down      # stop and delete its data
bin/site.sh wp ...    # WP-CLI
bin/site.sh log       # follow the WordPress debug log
docker exec -it klaro-geo-site bash
```

## CI

`.github/workflows/checks.yml` runs this suite on every pull request, with the same commands as above.

## Writing New Tests

1. Create new test file in `e2e/tests/`
2. Use helpers for common operations
3. Clear state in `beforeEach`
4. Use descriptive test names
5. Add to this README

Example:

```javascript
const { test, expect } = require('@playwright/test');
const { KlaroHelper } = require('../helpers/klaro');

test.describe('My New Feature', () => {
  let klaroHelper;

  test.beforeEach(async ({ page }) => {
    klaroHelper = new KlaroHelper(page);
    await page.context().clearCookies();
    await klaroHelper.clearConsent();
    await page.goto('/');
  });

  test('should do something', async ({ page }) => {
    await klaroHelper.waitForKlaroLoad();
    // Test logic here
  });
});
```

## Best Practices

1. **Always clear state** - Use `beforeEach` to reset cookies/localStorage
2. **Use helpers** - Don't duplicate selector logic
3. **Wait appropriately** - Use `waitFor*` methods instead of `waitForTimeout`
4. **Mock external APIs** - Use route interception for geo-detection
5. **Test real integration** - Don't mock Klaro or WordPress
6. **Keep tests independent** - Each test should run in isolation

## Performance

Typical test run time:
- Single test: 5-15 seconds
- Full suite: 2-3 minutes
- With setup/teardown: 3-4 minutes

## Next Steps

### Additional Tests to Add

1. **Floating Consent Button** - Test button appearance and functionality
2. **Admin Configuration** - Test WordPress admin settings
3. **Google Consent Mode** - Test consent mode signals
4. **Multi-language** - Test template translations
5. **Mobile Viewports** - Test responsive behavior

### Future Enhancements

- Visual regression testing
- Performance testing
- Accessibility testing (a11y)
- Cross-browser matrix expansion
- Video recordings of all test runs
