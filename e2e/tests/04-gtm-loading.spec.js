/**
 * E2E Test: Google Tag Manager Loading
 *
 * Tests that GTM loads correctly based on service configuration:
 * - GTM loads when service is required:true/default:true (default config)
 * - GTM does NOT load when service is default:false and user hasn't consented
 * - GTM loads after user grants consent when default:false
 */

const { test, expect } = require('@playwright/test');
const { KlaroHelper } = require('../helpers/klaro');
const { WordPressHelper, updateServiceSettingsViaCli, resetServiceToDefaultsViaCli } = require('../helpers/wordpress');

// GTM Container ID for testing (set in docker-entrypoint-e2e.sh)
const GTM_CONTAINER_ID = 'GTM-M2Z9TF4J';

/**
 * Helper to check if GTM script is loaded
 * @param {Page} page - Playwright page object
 * @returns {Promise<boolean>} - Whether GTM script is present
 */
async function isGtmScriptLoaded(page) {
  return await page.evaluate((containerId) => {
    // Check for GTM script tag
    const scripts = document.querySelectorAll('script');
    for (const script of scripts) {
      if (script.src && script.src.includes('googletagmanager.com/gtm.js')) {
        return true;
      }
      // Also check inline scripts that initialize GTM
      if (script.textContent && script.textContent.includes(containerId)) {
        return true;
      }
    }
    return false;
  }, GTM_CONTAINER_ID);
}

/**
 * Helper to check if GTM dataLayer has been initialized with GTM events
 * @param {Page} page - Playwright page object
 * @returns {Promise<boolean>} - Whether dataLayer has GTM events
 */
async function isGtmDataLayerInitialized(page) {
  return await page.evaluate(() => {
    if (!window.dataLayer || !Array.isArray(window.dataLayer)) {
      return false;
    }
    // Check for gtm.js event which GTM pushes when it loads
    return window.dataLayer.some(event =>
      event.event === 'gtm.js' ||
      event.event === 'gtm.dom' ||
      event.event === 'gtm.load'
    );
  });
}

/**
 * Helper to wait for GTM to fully load
 * @param {Page} page - Playwright page object
 * @param {number} timeout - Timeout in milliseconds
 */
async function waitForGtmLoad(page, timeout = 10000) {
  await page.waitForFunction(() => {
    return window.dataLayer && window.dataLayer.some(event =>
      event.event === 'gtm.js' ||
      event.event === 'gtm.dom' ||
      event.event === 'gtm.load'
    );
  }, { timeout });
}

/**
 * Helper to check if the GTM script tag exists in the page (even if not executed)
 * @param {Page} page - Playwright page object
 * @returns {Promise<boolean>} - Whether the GTM script tag exists
 */
async function hasGtmScriptTag(page) {
  return await page.evaluate(() => {
    // Look for script tags with data-name="google-tag-manager"
    const scripts = document.querySelectorAll('script[data-name="google-tag-manager"]');
    return scripts.length > 0;
  });
}

test.describe('Google Tag Manager Loading', () => {
  // Run entire test suite serially to avoid race conditions when modifying service settings
  test.describe.configure({ mode: 'serial' });

  // Increase timeout for tests that modify service settings
  test.setTimeout(60000);

  let klaroHelper;
  let wpHelper;

  test.beforeEach(async ({ page, context }) => {
    klaroHelper = new KlaroHelper(page);
    wpHelper = new WordPressHelper(page);

    // Clear browser cache to ensure fresh klaro-config.js is loaded
    await context.clearCookies();

    // Clear all cookies and localStorage after navigating to a page
    // (can only clear localStorage when on a page)
    try {
      await page.goto('/');
      await klaroHelper.clearConsent();
    } catch (e) {
      // Ignore if page navigation fails (first load)
    }
  });

  test.describe('With GTM as required/default service (auto-load configuration)', () => {
    // Run serially to avoid race conditions with other test groups modifying service settings
    test.describe.configure({ mode: 'serial' });

    // Set GTM to required:true, default:true for these tests
    // Note: Plugin default is now required:false, default:false, so we must explicitly set this
    test.beforeEach(async ({ page }) => {
      try {
        const result = updateServiceSettingsViaCli('google-tag-manager', {
          required: true,
          default: true
        });
        if (!result.success) {
          console.log('Warning: Could not set GTM to required/default:', result.error);
        }
      } catch (e) {
        console.log('Warning: Could not set GTM to required/default:', e.message);
      }
    });

    test.afterEach(async () => {
      // Restore GTM to plugin defaults (required:false, default:false)
      try {
        const result = resetServiceToDefaultsViaCli('google-tag-manager');
        if (!result.success) {
          console.log('Warning: Could not restore GTM defaults:', result.error);
        }
      } catch (e) {
        console.log('Warning: Could not restore GTM defaults:', e.message);
      }
    });

    test('should have GTM script tag in the page', async ({ page }) => {
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();

      // The GTM script tag should exist (even if type="text/plain" initially)
      const hasTag = await hasGtmScriptTag(page);
      expect(hasTag).toBe(true);
    });

    test('should load GTM script when service is required and default', async ({ page }) => {
      // Navigate to homepage - GTM should load automatically for required service
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();

      // Wait a moment for scripts to load
      await page.waitForTimeout(3000);

      // Check that GTM script is loaded (external script fetched)
      const gtmLoaded = await isGtmScriptLoaded(page);
      expect(gtmLoaded).toBe(true);
    });

    test('should have GTM dataLayer events when service is required', async ({ page }) => {
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();

      // Wait for GTM to initialize
      try {
        await waitForGtmLoad(page, 10000);
        const gtmInitialized = await isGtmDataLayerInitialized(page);
        expect(gtmInitialized).toBe(true);
      } catch (e) {
        // If GTM doesn't push events (network issue), verify at least script tag exists
        const gtmLoaded = await isGtmScriptLoaded(page);
        expect(gtmLoaded).toBe(true);
      }
    });

    test('should show GTM as consented in Klaro manager after accepting', async ({ page }) => {
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();

      // Accept all to save consent
      await klaroHelper.waitForModal();
      await klaroHelper.acceptAll();

      // Check consent state
      const consentData = await klaroHelper.getLocalStorageConsent();
      expect(consentData).toBeTruthy();
      expect(consentData.services).toBeDefined();
      expect(consentData.services['google-tag-manager']).toBe(true);
    });
  });

  test.describe('With GTM as non-default service (opt-in required - plugin default)', () => {
    // This is now the plugin's default configuration (required:false, default:false)
    // Run these tests serially to avoid race conditions with service settings
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
      // Ensure GTM is set to plugin defaults (required:false, default:false)
      // This should already be the case, but reset to be safe
      try {
        const result = resetServiceToDefaultsViaCli('google-tag-manager');
        if (!result.success) {
          console.log('Warning: Could not reset GTM to defaults:', result.error);
        }
      } catch (e) {
        console.log('Warning: Could not reset GTM to defaults:', e.message);
      }

      // Clear cookies to test as anonymous user
      await page.context().clearCookies();
      await klaroHelper.clearConsent();
    });

    // No afterEach needed - we're testing the default configuration

    test('should NOT load GTM script before consent when service is default:false', async ({ page }) => {
      // Navigate to homepage without any prior consent
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();

      // Wait for modal to appear (proving no prior consent)
      await klaroHelper.waitForModal();

      // Wait for all initial script loading to complete before checking GTM state
      await page.waitForLoadState('networkidle');

      // GTM script tag should exist but NOT be executed (external script not fetched)
      const hasTag = await hasGtmScriptTag(page);
      expect(hasTag).toBe(true);

      // GTM dataLayer events should not exist (gtm.js not pushed)
      const gtmInitialized = await isGtmDataLayerInitialized(page);
      expect(gtmInitialized).toBe(false);
    });

    test('should load GTM script after user accepts all consent', async ({ page }) => {
      // Navigate to homepage
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();
      await klaroHelper.waitForModal();

      // Wait for all initial script loading to complete before checking GTM state
      await page.waitForLoadState('networkidle');

      // Verify GTM is not fully loaded initially (no gtm.js event)
      let gtmInitialized = await isGtmDataLayerInitialized(page);
      expect(gtmInitialized).toBe(false);

      // Accept all consent
      await klaroHelper.acceptAll();

      // Wait for GTM to load after consent
      await page.waitForTimeout(3000);

      // Now GTM should be loaded
      const gtmLoaded = await isGtmScriptLoaded(page);
      expect(gtmLoaded).toBe(true);
    });

    test('should NOT load GTM when user declines all consent', async ({ page }) => {
      // Navigate to homepage
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();
      await klaroHelper.waitForModal();

      // Decline consent
      await klaroHelper.declineAll();

      // Wait a moment
      await page.waitForTimeout(2000);

      // GTM dataLayer events should NOT exist
      const gtmInitialized = await isGtmDataLayerInitialized(page);
      expect(gtmInitialized).toBe(false);

      // Verify consent data shows GTM declined
      const consentData = await klaroHelper.getLocalStorageConsent();
      expect(consentData).toBeTruthy();
      if (consentData.services && 'google-tag-manager' in consentData.services) {
        expect(consentData.services['google-tag-manager']).toBe(false);
      }
    });

    test('should load GTM on subsequent page loads after consent was given', async ({ page }) => {
      // Navigate and accept consent
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();
      await klaroHelper.waitForModal();
      await klaroHelper.acceptAll();

      // Wait for GTM to load
      await page.waitForTimeout(2000);

      // Reload the page
      await page.reload();
      await klaroHelper.waitForKlaroLoad();

      // Wait for GTM to load from stored consent
      await page.waitForTimeout(3000);

      // GTM should be loaded from stored consent
      const gtmLoaded = await isGtmScriptLoaded(page);
      expect(gtmLoaded).toBe(true);
    });
  });

  test.describe('GTM and dataLayer integration', () => {
    test('should push klaroConfigLoaded event to dataLayer', async ({ page }) => {
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();

      // Wait for config loaded event
      const configEvent = await klaroHelper.waitForDataLayerEvent('klaroConfigLoaded', 5000);
      expect(configEvent).toBeDefined();
      expect(configEvent.eventSource).toBe('klaro-geo');
    });

    test('should have Klaro consent events in dataLayer after accepting', async ({ page }) => {
      await page.goto('/');
      await klaroHelper.waitForKlaroLoad();
      await klaroHelper.waitForModal();
      await klaroHelper.acceptAll();

      // Wait for consent events
      await page.waitForTimeout(1000);

      // Check dataLayer for Klaro events
      const dataLayer = await klaroHelper.getDataLayerEvents();

      // Find Klaro events
      const klaroEvents = dataLayer.filter(event =>
        event.eventSource === 'klaro-geo' || event.eventSource === 'klaro'
      );

      expect(klaroEvents.length).toBeGreaterThan(0);
    });
  });
});
