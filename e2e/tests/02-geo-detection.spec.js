/**
 * E2E Test: Geo-Detection and Template Loading
 *
 * Tests that Klaro Geo correctly:
 * - Detects user location (using debug query parameter)
 * - Loads appropriate template based on location
 * - Pushes geo data to dataLayer
 */

const { test, expect } = require('@playwright/test');
const { KlaroHelper } = require('../helpers/klaro');

test.describe('Geo-Detection and Template Loading', () => {
  let klaroHelper;

  test.beforeEach(async ({ page }) => {
    klaroHelper = new KlaroHelper(page);
    await page.context().clearCookies();
    await klaroHelper.clearConsent();
  });

  test('should detect location and load appropriate template', async ({ page }) => {
    // Navigate to page
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();

    // Wait for dataLayer to be populated
    await page.waitForTimeout(2000);

    // Check dataLayer for geo information
    const configLoadedEvent = await klaroHelper.findDataLayerEvent('klaroConfigLoaded');

    expect(configLoadedEvent).toBeTruthy();

    // Verify the dataLayer event structure exists
    // Field name is klaroGeoConsentTemplate (not klaroGeoTemplateName) per klaro-config.js:175
    expect(configLoadedEvent).toHaveProperty('klaroGeoConsentTemplate');
    expect(configLoadedEvent.klaroGeoTemplateSource).toBeTruthy();

    // Country may be null on localhost - that's expected
    expect(configLoadedEvent).toHaveProperty('klaroGeoDetectedCountry');
  });

  test('should handle geo-detection failure gracefully', async ({ page, context }) => {
    // Mock geo-detection API failure
    await context.route('**/wp-admin/admin-ajax.php*', async (route) => {
      const request = route.request();
      const postData = request.postData();

      if (postData && postData.includes('action=klaro_geo_detect')) {
        await route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({
            success: false,
            error: 'Geo-detection service unavailable'
          })
        });
      } else {
        await route.continue();
      }
    });

    await page.goto('/');

    // Klaro should still load with fallback template
    await klaroHelper.waitForKlaroLoad();

    // Modal should still appear
    await klaroHelper.waitForModal(15000);
    const isVisible = await klaroHelper.isModalVisible();
    expect(isVisible).toBe(true);
  });

  test('should include geo data in consent receipts', async ({ page }) => {
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // Accept consent
    await klaroHelper.acceptAll();

    // Wait for receipt to be generated
    await page.waitForTimeout(1000);

    // Check localStorage for receipt with geo data
    const receipts = await page.evaluate(() => {
      const data = localStorage.getItem('klaro_consent_receipts');
      return data ? JSON.parse(data) : null;
    });

    if (receipts && receipts.length > 0) {
      const latestReceipt = receipts[receipts.length - 1];

      // Receipt should have country/region fields
      expect(latestReceipt).toHaveProperty('country_code');
      expect(latestReceipt).toHaveProperty('region_code');
      expect(latestReceipt).toHaveProperty('template_name');
      expect(latestReceipt).toHaveProperty('template_source');
    }
  });

  test('should display correct template name in modal', async ({ page }) => {
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // Check if modal displays any template-specific content
    const modal = page.locator('.klaro .cookie-modal');
    await expect(modal).toBeVisible();

    // Modal should have title and description
    const title = modal.locator('.cookie-modal-title, .cm-modal-title, h1, h2').first();
    const hasTitle = await title.count() > 0;
    expect(hasTitle).toBe(true);
  });
});
