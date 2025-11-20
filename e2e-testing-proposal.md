# E2E Testing Implementation Plan for Klaro Geo

## Overview

Automated E2E testing setup using Playwright to verify critical integrations between WordPress, Klaro, and Klaro Geo plugin.

## Architecture

### Test Environment Setup

```yaml
services:
  wordpress_e2e:
    image: wordpress:latest
    environment:
      - WORDPRESS_DB_HOST=db_e2e
      - WP_DEBUG=true
    volumes:
      - ./:/var/www/html/wp-content/plugins/klaro-geo
    ports:
      - "8080:80"

  db_e2e:
    image: mysql:5.7
    environment:
      MYSQL_DATABASE: wordpress_e2e

  playwright:
    image: mcr.microsoft.com/playwright:latest
    volumes:
      - ./e2e:/e2e
    depends_on:
      - wordpress_e2e
    command: npx playwright test
```

## Tool Choice: Playwright

### Installation

```bash
npm install --save-dev @playwright/test
npx playwright install
```

### Configuration (`playwright.config.js`)

```javascript
module.exports = {
  testDir: './e2e',
  timeout: 30000,
  retries: 2,
  use: {
    baseURL: 'http://localhost:8080',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
    {
      name: 'firefox',
      use: { browserName: 'firefox' },
    },
  ],
};
```

## Test Structure

```
e2e/
├── fixtures/
│   ├── wordpress-setup.js       # WordPress login, plugin setup
│   ├── mock-geo-api.js          # Mock geo-detection responses
│   └── test-data.js             # Test templates, services
├── tests/
│   ├── 01-consent-flow.spec.js  # Basic consent modal flow
│   ├── 02-geo-detection.spec.js # Geo-based template loading
│   ├── 03-consent-receipts.spec.js # Receipt generation
│   ├── 04-floating-button.spec.js  # Floating button functionality
│   ├── 05-admin-config.spec.js     # Admin interface
│   └── 06-consent-mode.spec.js     # Google Consent Mode
├── helpers/
│   ├── wordpress.js             # WordPress API helpers
│   ├── klaro.js                 # Klaro interaction helpers
│   └── database.js              # Direct DB queries
└── playwright.config.js
```

## Sample Test: Consent Flow

```javascript
// e2e/tests/01-consent-flow.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Klaro Consent Modal Flow', () => {
  test.beforeEach(async ({ page }) => {
    // Clear cookies and localStorage
    await page.context().clearCookies();
    await page.goto('/');
  });

  test('should display consent modal on first visit', async ({ page }) => {
    // Wait for Klaro to load
    await page.waitForSelector('.klaro .cookie-modal');

    // Check modal is visible
    const modal = page.locator('.klaro .cookie-modal');
    await expect(modal).toBeVisible();

    // Check for expected elements
    await expect(page.locator('.cm-btn-accept-all')).toBeVisible();
    await expect(page.locator('.cm-btn-decline')).toBeVisible();
  });

  test('should save consent when Accept All clicked', async ({ page }) => {
    await page.waitForSelector('.klaro .cookie-modal');

    // Click Accept All
    await page.click('.cm-btn-accept-all');

    // Modal should close
    await expect(page.locator('.klaro .cookie-modal')).not.toBeVisible();

    // Check localStorage has consent data
    const consent = await page.evaluate(() => {
      return localStorage.getItem('klaro');
    });

    expect(consent).toBeTruthy();
    const consentData = JSON.parse(consent);
    expect(consentData.services).toBeDefined();
  });

  test('should persist consent on page reload', async ({ page }) => {
    await page.waitForSelector('.klaro .cookie-modal');
    await page.click('.cm-btn-accept-all');

    // Reload page
    await page.reload();

    // Modal should NOT appear
    await page.waitForTimeout(2000);
    const modal = page.locator('.klaro .cookie-modal');
    await expect(modal).not.toBeVisible();
  });
});
```

## Sample Test: Geo-Detection

```javascript
// e2e/tests/02-geo-detection.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Geo-Detection and Template Loading', () => {
  test('should load US template for US IP', async ({ page, context }) => {
    // Mock geo-detection API
    await context.route('**/wp-admin/admin-ajax.php*action=klaro_geo_detect*',
      async route => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            country: 'US',
            region: 'CA',
            template: 'us_template'
          })
        });
      }
    );

    await page.goto('/');

    // Check that US template is loaded
    const dataLayer = await page.evaluate(() => window.dataLayer);
    const configLoaded = dataLayer.find(e =>
      e.klaroEventName === 'klaroConfigLoaded'
    );

    expect(configLoaded).toBeTruthy();
    expect(configLoaded.klaroGeoTemplateName).toBe('us_template');
    expect(configLoaded.klaroGeoDetectedCountry).toBe('US');
  });

  test('should load EU template for German IP', async ({ page, context }) => {
    await context.route('**/wp-admin/admin-ajax.php*action=klaro_geo_detect*',
      async route => {
        await route.fulfill({
          status: 200,
          body: JSON.stringify({
            country: 'DE',
            template: 'eu_gdpr_template'
          })
        });
      }
    );

    await page.goto('/');

    const dataLayer = await page.evaluate(() => window.dataLayer);
    const configLoaded = dataLayer.find(e =>
      e.klaroEventName === 'klaroConfigLoaded'
    );

    expect(configLoaded.klaroGeoDetectedCountry).toBe('DE');
    expect(configLoaded.klaroGeoTemplateName).toBe('eu_gdpr_template');
  });
});
```

## Sample Test: Consent Receipts

```javascript
// e2e/tests/03-consent-receipts.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Consent Receipt Generation', () => {
  test('should generate receipt when consent saved', async ({ page }) => {
    let receiptSent = false;

    // Intercept receipt API call
    await page.route('**/wp-admin/admin-ajax.php', async route => {
      const postData = route.request().postData();

      if (postData && postData.includes('klaro_geo_store_consent_receipt')) {
        receiptSent = true;
        await route.fulfill({
          status: 200,
          body: JSON.stringify({ success: true })
        });
      } else {
        await route.continue();
      }
    });

    await page.goto('/');
    await page.waitForSelector('.klaro .cookie-modal');

    // Accept consent
    await page.click('.cm-btn-accept-all');

    // Wait for receipt to be sent
    await page.waitForTimeout(1000);

    expect(receiptSent).toBe(true);

    // Check localStorage has receipt
    const receipts = await page.evaluate(() => {
      return localStorage.getItem('klaro_consent_receipts');
    });

    expect(receipts).toBeTruthy();
    const receiptData = JSON.parse(receipts);
    expect(receiptData.length).toBeGreaterThan(0);
    expect(receiptData[0].receipt_id).toBeTruthy();
  });
});
```

## Sample Test: Admin Configuration

```javascript
// e2e/tests/05-admin-config.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Admin Configuration', () => {
  test.beforeEach(async ({ page }) => {
    // Login to WordPress admin
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
  });

  test('should access Klaro Geo settings page', async ({ page }) => {
    // Navigate to plugin settings
    await page.goto('/wp-admin/admin.php?page=klaro-geo-settings');

    // Check page loaded
    await expect(page.locator('h1')).toContainText('Klaro Geo Settings');

    // Check main sections exist
    await expect(page.locator('text=Templates')).toBeVisible();
    await expect(page.locator('text=Services')).toBeVisible();
    await expect(page.locator('text=Debug Settings')).toBeVisible();
  });

  test('should enable debug logging', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=klaro-geo-settings');

    // Find debug checkbox
    const debugCheckbox = page.locator('#klaro_geo_enable_debug_logging');

    // Enable it
    await debugCheckbox.check();

    // Save settings
    await page.click('input[type="submit"]');

    // Check saved message
    await expect(page.locator('.updated')).toContainText('Settings saved');

    // Verify checkbox is still checked after reload
    await page.reload();
    await expect(debugCheckbox).toBeChecked();
  });

  test('should create new template', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=klaro-geo-templates');

    // Click "Add New Template"
    await page.click('text=Add New Template');

    // Fill template form
    await page.fill('#template_name', 'Test Template');
    await page.fill('#template_description', 'E2E Test Template');

    // Save
    await page.click('text=Save Template');

    // Verify template appears in list
    await page.goto('/wp-admin/admin.php?page=klaro-geo-templates');
    await expect(page.locator('text=Test Template')).toBeVisible();
  });
});
```

## Helpers and Fixtures

### WordPress Helper

```javascript
// e2e/helpers/wordpress.js
class WordPressHelper {
  constructor(page) {
    this.page = page;
  }

  async login(username = 'admin', password = 'password') {
    await this.page.goto('/wp-admin');
    await this.page.fill('#user_login', username);
    await this.page.fill('#user_pass', password);
    await this.page.click('#wp-submit');
    await this.page.waitForURL('**/wp-admin/**');
  }

  async installPlugin(pluginSlug) {
    await this.page.goto('/wp-admin/plugin-install.php');
    await this.page.fill('#search-plugins', pluginSlug);
    await this.page.click('.install-now');
    await this.page.waitForSelector('.activate-now');
    await this.page.click('.activate-now');
  }

  async activatePlugin(pluginFile) {
    await this.page.goto('/wp-admin/plugins.php');
    const activateLink = `a[href*="plugin=${pluginFile}"][href*="action=activate"]`;
    if (await this.page.locator(activateLink).isVisible()) {
      await this.page.click(activateLink);
    }
  }

  async getOption(optionName) {
    return await this.page.evaluate(async (name) => {
      const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: new URLSearchParams({
          action: 'get_option',
          option_name: name
        })
      });
      return await response.json();
    }, optionName);
  }
}

module.exports = { WordPressHelper };
```

### Klaro Helper

```javascript
// e2e/helpers/klaro.js
class KlaroHelper {
  constructor(page) {
    this.page = page;
  }

  async waitForKlaroLoad() {
    await this.page.waitForFunction(() => {
      return typeof window.klaro !== 'undefined';
    });
  }

  async getConsents() {
    return await this.page.evaluate(() => {
      const manager = window.klaro.getManager();
      return manager ? manager.consents : null;
    });
  }

  async acceptAll() {
    await this.page.waitForSelector('.cm-btn-accept-all');
    await this.page.click('.cm-btn-accept-all');
  }

  async declineAll() {
    await this.page.waitForSelector('.cm-btn-decline');
    await this.page.click('.cm-btn-decline');
  }

  async toggleService(serviceName) {
    await this.page.click(`input#service-item-${serviceName}`);
  }

  async saveChoices() {
    await this.page.click('.cm-btn-save');
  }

  async getDataLayerEvents() {
    return await this.page.evaluate(() => {
      return window.dataLayer || [];
    });
  }
}

module.exports = { KlaroHelper };
```

## Docker Integration

### docker-compose.e2e.yml

```yaml
version: '3.8'

services:
  wordpress_e2e:
    build: ./docker/wordpress
    container_name: klaro-geo-wordpress-e2e
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db_e2e:3306
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress_e2e
      WP_DEBUG: "true"
      WP_DEBUG_LOG: "true"
    volumes:
      - ./:/var/www/html/wp-content/plugins/klaro-geo
      - wordpress_e2e_data:/var/www/html
    depends_on:
      - db_e2e
    networks:
      - klaro-e2e

  db_e2e:
    image: mysql:5.7
    container_name: klaro-geo-db-e2e
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: wordpress_e2e
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
    volumes:
      - db_e2e_data:/var/lib/mysql
    networks:
      - klaro-e2e

  playwright:
    image: mcr.microsoft.com/playwright:v1.40.0-focal
    container_name: klaro-geo-playwright
    working_dir: /app
    volumes:
      - ./:/app
    depends_on:
      - wordpress_e2e
    networks:
      - klaro-e2e
    command: npx playwright test

volumes:
  wordpress_e2e_data:
  db_e2e_data:

networks:
  klaro-e2e:
```

## Running E2E Tests

### Scripts to Add to package.json

```json
{
  "scripts": {
    "e2e:setup": "docker-compose -f docker-compose.e2e.yml up -d && npm run e2e:wait",
    "e2e:wait": "wait-on http://localhost:8080 && sleep 10",
    "e2e:test": "playwright test",
    "e2e:test:headed": "playwright test --headed",
    "e2e:test:debug": "playwright test --debug",
    "e2e:down": "docker-compose -f docker-compose.e2e.yml down",
    "e2e": "npm run e2e:setup && npm run e2e:test && npm run e2e:down"
  }
}
```

### Running Tests

```bash
# Full E2E test run
npm run e2e

# Run tests with browser visible (debugging)
npm run e2e:test:headed

# Run specific test file
npx playwright test e2e/tests/01-consent-flow.spec.js

# Run tests in debug mode with inspector
npm run e2e:test:debug

# Run tests in specific browser
npx playwright test --project=firefox
```

## CI/CD Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  e2e-tests:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'

      - name: Install dependencies
        run: npm ci

      - name: Install Playwright browsers
        run: npx playwright install --with-deps

      - name: Start WordPress test environment
        run: npm run e2e:setup

      - name: Run E2E tests
        run: npm run e2e:test

      - name: Upload test artifacts
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: playwright-results
          path: |
            test-results/
            playwright-report/

      - name: Cleanup
        if: always()
        run: npm run e2e:down
```

## Benefits of This Approach

### 1. Confidence
- ✅ Verify real WordPress + Klaro + Klaro Geo integration
- ✅ Catch integration bugs before production
- ✅ Test actual user flows

### 2. Fast Feedback
- ✅ Playwright is fast (~10-30s per test)
- ✅ Run in CI on every PR
- ✅ Parallel test execution

### 3. Maintainability
- ✅ Clear test structure
- ✅ Reusable helpers
- ✅ Good documentation

### 4. Docker Integration
- ✅ Uses existing Docker setup
- ✅ Isolated test environment
- ✅ Easy to run locally and in CI

## Estimated Implementation Time

- **Initial Setup**: 4-6 hours
  - Docker configuration
  - Playwright setup
  - Helper classes

- **High Priority Tests**: 8-12 hours
  - Consent flow (2-3 hours)
  - Geo-detection (3-4 hours)
  - Consent receipts (2-3 hours)
  - Floating button (1-2 hours)

- **Medium Priority Tests**: 6-8 hours
  - Admin config (4-5 hours)
  - Consent mode (2-3 hours)

**Total**: ~18-26 hours for comprehensive E2E coverage

## Next Steps

1. **Install Playwright**: `npm install --save-dev @playwright/test`
2. **Create docker-compose.e2e.yml**
3. **Write first test** (consent flow)
4. **Add to CI pipeline**
5. **Expand coverage incrementally**

---

**Recommendation**: Start with the high-priority tests (consent flow, geo-detection, receipts). These give you the most value and verify the critical integration points between WordPress, Klaro, and your plugin.
