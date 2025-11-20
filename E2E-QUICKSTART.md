# E2E Testing Quick Start Guide

## What's Been Set Up

✅ **Playwright** - E2E testing framework installed
✅ **Docker E2E Environment** - Isolated WordPress + MySQL containers
✅ **Helper Utilities** - WordPress and Klaro interaction helpers
✅ **3 Test Suites** - Consent flow, geo-detection, and receipts
✅ **NPM Scripts** - Easy commands to run tests

## Quick Start (5 Minutes)

### 1. First Time Setup

Install Playwright browsers (already done):
```bash
npm run e2e:install
```

### 2. Run Your First E2E Test

**Option A: Full automated run** (recommended for first try)
```bash
npm run e2e:full
```
This will:
- Start WordPress Docker environment
- Wait for it to be ready
- Run all E2E tests
- Stop Docker environment
- Show results

**Option B: Manual control** (better for development)
```bash
# Start environment (do once)
npm run e2e:setup

# Run tests (do many times)
npm run e2e:test

# When done, stop environment
npm run e2e:docker:down
```

### 3. Watch Tests Run (Debugging Mode)

See the browser as tests run:
```bash
npm run e2e:setup
npm run e2e:test:headed
```

### 4. Interactive Test Debugging

Best way to debug failing tests:
```bash
npm run e2e:setup
npm run e2e:test:ui
```

This opens Playwright's UI mode where you can:
- Run tests one at a time
- See each step
- Inspect the page at any point
- Record new tests

## Test Coverage

### ✅ What's Tested

**Consent Modal Flow** (7 tests)
- Modal appears on first visit
- Accept All works
- Decline works
- Consent persists across reloads
- Individual service toggles
- DataLayer events
- Modal re-appears when consent cleared

**Geo-Detection** (5 tests)
- Location detection (mocked)
- Template loads based on location
- Geo data in dataLayer
- Fallback on detection failure
- Geo data included in receipts

**Consent Receipts** (8 tests)
- Receipt generated on consent save
- Required fields present
- Receipt sent to server
- Multiple consent changes tracked
- Receipt limit (max 10)
- Consent choices included
- DataLayer integration

**Total: 20 E2E tests** verifying WordPress + Klaro + Klaro Geo integration

## Common Commands

```bash
# Run all tests
npm run e2e:test

# Run specific test file
npx playwright test e2e/tests/01-consent-modal-flow.spec.js

# Run specific test by name
npx playwright test -g "should display consent modal"

# Run in specific browser
npx playwright test --project=chromium
npx playwright test --project=firefox

# View last test report
npm run e2e:report

# Debug specific test
npx playwright test --debug -g "should display consent modal"
```

## Troubleshooting

### Tests Timeout

**Problem:** Tests fail with "Timeout waiting for..."

**Solution:**
```bash
# Check WordPress is running
curl http://localhost:8080

# Check Docker containers
docker ps | grep klaro-geo

# Restart environment
npm run e2e:docker:down
npm run e2e:setup
```

### Port 8080 In Use

**Problem:** Can't start WordPress because port is busy

**Solution:**
```bash
# Find what's using port 8080
lsof -i :8080

# Stop it, or edit docker-compose.e2e.yml to use different port
```

### WordPress Not Initializing

**Problem:** WordPress container starts but isn't accessible

**Solution:**
```bash
# Check logs
docker logs klaro-geo-wordpress-e2e

# Wait longer (WordPress can take 30-60s first time)
npm run e2e:wait

# Reset everything
npm run e2e:docker:down
docker volume rm klaro-geo_wordpress_e2e_data
npm run e2e:setup
```

### Tests Pass Locally But Fail in CI

**Problem:** Tests work on your machine but not in CI

**Solution:**
- Increase timeouts in `playwright.config.js`
- Use `retries: 2` in config for CI
- Check screenshots/videos in CI artifacts

## File Structure

```
klaro-geo/
├── e2e/
│   ├── tests/
│   │   ├── 01-consent-modal-flow.spec.js    ← 7 tests
│   │   ├── 02-geo-detection.spec.js         ← 5 tests
│   │   └── 03-consent-receipts.spec.js      ← 8 tests
│   ├── helpers/
│   │   ├── wordpress.js                     ← WP utilities
│   │   └── klaro.js                         ← Klaro utilities
│   └── README.md                            ← Full documentation
├── playwright.config.js                     ← Playwright config
├── docker-compose.e2e.yml                   ← E2E Docker env
└── E2E-QUICKSTART.md                        ← This file
```

## Next Steps

### Add More Tests

Create new test file:
```javascript
// e2e/tests/04-my-new-test.spec.js
const { test, expect } = require('@playwright/test');
const { KlaroHelper } = require('../helpers/klaro');

test.describe('My Feature', () => {
  test('should do something', async ({ page }) => {
    const klaroHelper = new KlaroHelper(page);
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    // Your test logic
  });
});
```

Run it:
```bash
npx playwright test e2e/tests/04-my-new-test.spec.js
```

### Add to CI/CD

See `e2e/README.md` for GitHub Actions example.

### Visual Regression Testing

Playwright supports screenshot comparison:
```javascript
await expect(page).toHaveScreenshot();
```

### Test on More Browsers

Uncomment webkit in `playwright.config.js` to test Safari.

## Performance

- **Single test**: 5-15 seconds
- **Full suite**: 2-3 minutes
- **With Docker setup**: 3-4 minutes total

Tests run in parallel across browsers by default.

## Need Help?

1. Check `e2e/README.md` for detailed documentation
2. Run tests in debug mode: `npm run e2e:test:debug`
3. Use UI mode for interactive debugging: `npm run e2e:test:ui`
4. Check Playwright docs: https://playwright.dev

## What This Achieves

✅ **Automated verification** that WordPress + Klaro + Klaro Geo integration works
✅ **Catches integration bugs** before they reach production
✅ **Documents expected behavior** through executable tests
✅ **Fast feedback** - Know if changes break integration in 3 minutes
✅ **Confidence to refactor** - Tests verify everything still works

---

**You're all set!** Run `npm run e2e:full` to see your E2E tests in action.
