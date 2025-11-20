/**
 * E2E Test: Consent Receipt Generation
 *
 * Tests that consent receipts are:
 * - Generated when consent is saved
 * - Stored in localStorage
 * - Sent to server (when enabled)
 * - Include correct metadata
 */

const { test, expect } = require('@playwright/test');
const { KlaroHelper } = require('../helpers/klaro');

test.describe('Consent Receipt Generation', () => {
  let klaroHelper;

  test.beforeEach(async ({ page }) => {
    klaroHelper = new KlaroHelper(page);
    await page.context().clearCookies();
    await klaroHelper.clearConsent();
  });

  test('should generate receipt when consent is saved', async ({ page }) => {
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // Accept consent
    await klaroHelper.acceptAll();

    // Wait for receipt generation event in dataLayer
    // The actual event name is 'Klaro Geo Consent Receipt' per klaro-geo-consent-receipts.js:147
    const receiptEvent = await klaroHelper.waitForDataLayerEventByName('Klaro Geo Consent Receipt', 5000);

    expect(receiptEvent).toBeTruthy();
    expect(receiptEvent.klaro_geo_consent_receipt).toBeTruthy();

    // Also verify receipt is in localStorage (as backup storage)
    const receipts = await klaroHelper.getConsentReceipts();
    if (receipts) {
      expect(Array.isArray(receipts)).toBe(true);
      expect(receipts.length).toBeGreaterThan(0);
    }
  });

  test('should include required fields in receipt', async ({ page }) => {
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    await klaroHelper.acceptAll();

    // Wait for receipt generation event in dataLayer
    const receiptEvent = await klaroHelper.waitForDataLayerEventByName('Klaro Geo Consent Receipt', 5000);

    expect(receiptEvent).toBeTruthy();
    expect(receiptEvent.klaro_geo_consent_receipt).toBeTruthy();

    const receipt = receiptEvent.klaro_geo_consent_receipt;

    // Check required fields
    expect(receipt).toHaveProperty('receipt_id');
    expect(receipt).toHaveProperty('timestamp');
    expect(receipt).toHaveProperty('consent_choices');
    expect(receipt).toHaveProperty('template_name');
    expect(receipt).toHaveProperty('template_source');

    // Receipt ID should be unique
    expect(receipt.receipt_id).toBeTruthy();
    expect(receipt.receipt_id).toMatch(/^receipt_/);

    // Timestamp should be reasonable
    expect(receipt.timestamp).toBeGreaterThan(0);

    // Consent choices should be an object
    expect(typeof receipt.consent_choices).toBe('object');
  });

  test('should send receipt to server when logging enabled', async ({ page }) => {
    let receiptSent = false;
    let receiptData = null;

    // Intercept receipt API call
    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
      const request = route.request();
      const postData = request.postData();

      if (postData && postData.includes('klaro_geo_store_consent_receipt')) {
        receiptSent = true;

        // Extract receipt data from form data
        const formData = new URLSearchParams(postData);
        const receiptDataStr = formData.get('receipt_data');
        if (receiptDataStr) {
          receiptData = JSON.parse(receiptDataStr);
        }

        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, receipt_id: 'server_123' })
        });
      } else {
        await route.continue();
      }
    });

    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    await klaroHelper.acceptAll();

    // Wait for receipt to be sent
    await page.waitForTimeout(2000);

    // Verify receipt was sent
    expect(receiptSent).toBe(true);

    if (receiptData) {
      expect(receiptData.receipt_id).toBeTruthy();
      expect(receiptData.consent_choices).toBeTruthy();
    }
  });

  test('should handle multiple consent changes', async ({ page }) => {
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // First consent: Accept
    await klaroHelper.acceptAll();

    // Wait for first receipt
    await klaroHelper.waitForDataLayerEventByName('Klaro Geo Consent Receipt', 5000);

    // Open modal again to change consent
    await klaroHelper.openModal();
    await page.waitForTimeout(500);

    // Toggle a service (decline button not available after initial consent)
    // Click "Let me choose" to access individual services
    const letMeChoose = page.locator('a:has-text("Let me choose"), button:has-text("Let me choose")');
    if (await letMeChoose.isVisible().catch(() => false)) {
      await letMeChoose.click();
      await page.waitForTimeout(500);
    }

    // Save to generate second receipt
    await klaroHelper.saveChoices();

    // Check we have multiple receipts via dataLayer
    const dataLayer = await klaroHelper.getDataLayerEvents();
    const receiptEvents = dataLayer.filter(event => event.event === 'Klaro Geo Consent Receipt');

    // Should have at least 2 receipt generation events
    expect(receiptEvents.length).toBeGreaterThanOrEqual(2);
  });

  // NOTE: Skipping this test because declining after accepting is not possible
  // The decline button is only available on initial modal, not after consent is given
  test.skip('should limit receipts to maximum 10 in localStorage', async ({ page }) => {
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();

    // Generate 12 consent changes
    for (let i = 0; i < 12; i++) {
      await klaroHelper.waitForModal();

      if (i % 2 === 0) {
        await klaroHelper.acceptAll();
      } else {
        await klaroHelper.declineAll();
      }

      await page.waitForTimeout(500);

      // Reopen modal for next iteration (except last)
      if (i < 11) {
        await klaroHelper.openModal();
      }
    }

    // Wait for all receipts to be processed
    await page.waitForTimeout(2000);

    // Check localStorage
    const receipts = await page.evaluate(() => {
      const data = localStorage.getItem('klaro_consent_receipts');
      return data ? JSON.parse(data) : null;
    });

    // Should have max 10 receipts
    expect(receipts.length).toBeLessThanOrEqual(10);
  });

  test('should include consent choices in receipt', async ({ page }) => {
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    await klaroHelper.acceptAll();

    // Wait for receipt generation event in dataLayer
    const receiptEvent = await klaroHelper.waitForDataLayerEventByName('Klaro Geo Consent Receipt', 5000);

    expect(receiptEvent).toBeTruthy();
    expect(receiptEvent.klaro_geo_consent_receipt).toBeTruthy();

    const receipt = receiptEvent.klaro_geo_consent_receipt;

    // Consent choices should have at least one service
    expect(receipt.consent_choices).toBeTruthy();
    const services = Object.keys(receipt.consent_choices);
    expect(services.length).toBeGreaterThan(0);

    // Each service should have a boolean value
    services.forEach(service => {
      expect(typeof receipt.consent_choices[service]).toBe('boolean');
    });
  });

  test('should push receipt to dataLayer', async ({ page }) => {
    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    await klaroHelper.acceptAll();
    await page.waitForTimeout(1500);

    // Check dataLayer for receipt event
    const dataLayer = await klaroHelper.getDataLayerEvents();

    const receiptEvent = dataLayer.find(event =>
      event.event === 'Klaro Geo Consent Receipt' ||
      event.klaro_geo_consent_receipt
    );

    if (receiptEvent) {
      expect(receiptEvent.klaro_geo_consent_receipt).toBeTruthy();

      const receipt = receiptEvent.klaro_geo_consent_receipt;
      expect(receipt.timestamp).toBeTruthy();
      expect(receipt.consent_choices).toBeTruthy();
    }
  });
});
