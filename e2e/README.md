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

This will:
- Start WordPress and MySQL containers
- Wait for WordPress to be ready (http://localhost:8080)

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
docker logs klaro-geo-wordpress-e2e

# Restart environment
npm run e2e:docker:down
npm run e2e:setup
```

### Port 8080 Already in Use

Stop any services using port 8080 or change the port in `docker-compose.e2e.yml`

### Tests Fail Intermittently

- Increase wait times in tests
- Check for network issues
- Verify Docker has enough resources

## Manual Docker Management

### Start Environment Only

```bash
npm run e2e:docker:up
```

### Stop Environment

```bash
npm run e2e:docker:down
```

### View Logs

```bash
docker logs -f klaro-geo-wordpress-e2e
```

### Access WordPress Container

```bash
docker exec -it klaro-geo-wordpress-e2e bash
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm ci
      - run: npm run e2e:install
      - run: npm run e2e:full
      - uses: actions/upload-artifact@v3
        if: failure()
        with:
          name: playwright-report
          path: e2e-report/
```

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
