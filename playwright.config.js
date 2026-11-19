/**
 * Playwright Configuration for Klaro Geo E2E Tests
 *
 * Tests the integration between WordPress, Klaro library, and Klaro Geo plugin
 */

const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e/tests',

  // Maximum time one test can run
  timeout: 30 * 1000,

  // Expect timeout for assertions
  expect: {
    timeout: 5000
  },

  // One worker, no parallelism. Every test talks to the same WordPress site, and
  // several tests change site-wide options through WP-CLI (consent mode type,
  // the google-tag-manager service settings). With parallel workers, or with
  // the chromium and firefox projects running side by side, one test's page load
  // could see another test's settings, which produced failures that moved from
  // test to test between runs.
  fullyParallel: false,
  workers: 1,

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Reporter to use
  reporter: [
    ['html', { outputFolder: 'e2e-report' }],
    ['list']
  ],

  // Shared settings for all the projects below
  use: {
    // Base URL to use in actions like `await page.goto('/')`
    baseURL: 'http://localhost:8080',

    // Collect trace when retrying the failed test
    trace: 'on-first-retry',

    // Screenshot on failure
    screenshot: 'only-on-failure',

    // Video on failure
    video: 'retain-on-failure',

    // Emulate browser locale
    locale: 'en-US',

    // Emulate timezone
    timezoneId: 'America/New_York',
  },

  // Configure projects for major browsers
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },

    // Uncomment to test on webkit/Safari
    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },

    // Mobile viewports for responsive testing
    // {
    //   name: 'Mobile Chrome',
    //   use: { ...devices['Pixel 5'] },
    // },
  ],

  // Run your local dev server before starting the tests
  // We'll manage this separately via Docker
  // webServer: {
  //   command: 'npm run start',
  //   url: 'http://localhost:8080',
  //   reuseExistingServer: !process.env.CI,
  // },
});
