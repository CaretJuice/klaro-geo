/**
 * E2E Test: Consent Modal Flow
 *
 * Tests the basic Klaro consent modal functionality including:
 * - Modal appearance on first visit
 * - Accept/Decline functionality
 * - Consent persistence
 * - Modal re-opening
 */

const { test, expect } = require('@playwright/test');
const { KlaroHelper } = require('../helpers/klaro');

test.describe('Klaro Consent Modal Flow', () => {
  let klaroHelper;

  test.beforeEach(async ({ page }) => {
    klaroHelper = new KlaroHelper(page);

    // Clear all cookies and localStorage
    await page.context().clearCookies();
    await klaroHelper.clearConsent();

    // Go to homepage
    await page.goto('/');
  });

  test('should display consent modal on first visit', async ({ page }) => {
    // Wait for Klaro to load
    await klaroHelper.waitForKlaroLoad();

    // Modal should be visible
    await klaroHelper.waitForModal();
    const isVisible = await klaroHelper.isModalVisible();
    expect(isVisible).toBe(true);

    // Check for expected buttons
    await expect(page.locator('.cm-btn-accept-all')).toBeVisible();
    await expect(page.locator('.cm-btn-decline, .cm-btn-deny')).toBeVisible();
  });

  test('should save consent when "Accept All" is clicked', async ({ page }) => {
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // Click Accept All
    await klaroHelper.acceptAll();

    // Modal should be hidden
    const isVisible = await klaroHelper.isModalVisible();
    expect(isVisible).toBe(false);

    // Check localStorage has consent data
    const consentData = await klaroHelper.getLocalStorageConsent();
    expect(consentData).toBeTruthy();
    expect(consentData.services).toBeDefined();

    // At least one service should be accepted
    const hasAcceptedServices = Object.values(consentData.services).some(service => service === true);
    expect(hasAcceptedServices).toBe(true);
  });

  test('should save consent when "Decline" is clicked', async ({ page }) => {
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // Click Decline
    await klaroHelper.declineAll();

    // Modal should be hidden
    const isVisible = await klaroHelper.isModalVisible();
    expect(isVisible).toBe(false);

    // Check localStorage has consent data
    const consentData = await klaroHelper.getLocalStorageConsent();
    expect(consentData).toBeTruthy();

    // All non-required services should be declined (false or missing)
    // Required services (like google-tag-manager) will still be true
    if (consentData.services) {
      // Check that google-analytics (non-required) is declined
      if ('google-analytics' in consentData.services) {
        expect(consentData.services['google-analytics']).toBe(false);
      }
      // google-tag-manager is required, so it will be true
      // We just verify consent data exists
      expect(Object.keys(consentData.services).length).toBeGreaterThan(0);
    }
  });

  test('should persist consent on page reload', async ({ page }) => {
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // Accept consent
    await klaroHelper.acceptAll();

    // Reload page
    await page.reload();
    await klaroHelper.waitForKlaroLoad();

    // Wait a bit to see if modal appears
    await page.waitForTimeout(2000);

    // Modal should NOT appear
    const isVisible = await klaroHelper.isModalVisible();
    expect(isVisible).toBe(false);

    // Consent should still be in localStorage
    const consentData = await klaroHelper.getLocalStorageConsent();
    expect(consentData).toBeTruthy();
  });

  test('should push events to dataLayer when consent is saved', async ({ page }) => {
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // Accept consent
    await klaroHelper.acceptAll();

    // Wait a bit for dataLayer events
    await page.waitForTimeout(1000);

    // Check dataLayer has Klaro events
    const dataLayer = await klaroHelper.getDataLayerEvents();

    const klaroEvents = dataLayer.filter(event =>
      event.event === 'Klaro Event' ||
      event.eventSource === 'klaro' ||
      event.eventSource === 'klaro-geo'
    );

    expect(klaroEvents.length).toBeGreaterThan(0);
  });

  test('should show modal again if consent is cleared', async ({ page }) => {
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    // Accept consent
    await klaroHelper.acceptAll();

    // Clear consent
    await klaroHelper.clearConsent();

    // Reload page
    await page.reload();
    await klaroHelper.waitForKlaroLoad();

    // Modal should appear again
    await klaroHelper.waitForModal();
    const isVisible = await klaroHelper.isModalVisible();
    expect(isVisible).toBe(true);
  });
});
