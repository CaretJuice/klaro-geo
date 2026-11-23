/**
 * E2E Test: geo resolution modes
 *
 * Both modes hand the browser a data payload that js/klaro-geo-bootstrap.js
 * applies. These tests load a real page in each mode and check that Klaro
 * starts and that klaroConfigLoaded reports the mode. Unit tests cannot cover
 * this: it depends on the order WordPress prints the scripts in.
 */

const { test, expect } = require('@playwright/test');
const { KlaroHelper } = require('../helpers/klaro');
const { updateOptionViaCli } = require('../helpers/wordpress');

async function configLoadedEvents(page) {
  return await page.evaluate(() =>
    (window.dataLayer || []).filter(e => e && e.klaroEventName === 'klaroConfigLoaded')
  );
}

test.describe('Geo resolution modes', () => {
  test.describe.configure({ mode: 'serial' });

  let klaroHelper;

  test.beforeEach(async ({ page, context }) => {
    klaroHelper = new KlaroHelper(page);
    await context.clearCookies();
  });

  test.afterEach(async () => {
    updateOptionViaCli('klaro_geo_geo_resolution_mode', 'server');
  });

  test('server mode: payload is pre-resolved and Klaro starts', async ({ page }) => {
    updateOptionViaCli('klaro_geo_geo_resolution_mode', 'server');

    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    const payload = await page.evaluate(() => window.klaroGeoTemplatePayload);
    expect(payload.mode).toBe('server');
    expect(Object.keys(payload.templates)).toEqual([payload.resolved.template]);

    const events = await configLoadedEvents(page);
    expect(events).toHaveLength(1);
    expect(events[0].klaroGeoResolutionMode).toBe('server');
    expect(events[0].klaroGeoGeoSource).toBe('server');
    expect(events[0].klaroGeoConsentTemplate).toBe(payload.resolved.template);

    // The config Klaro runs with is the one from the payload
    const sameConfig = await page.evaluate(() =>
      window.klaroConfig === window.klaroGeoTemplatePayload.templates[window.klaroGeoTemplatePayload.resolved.template].config
    );
    expect(sameConfig).toBe(true);
  });

  test('client mode: geo is resolved in the browser and Klaro starts', async ({ page }) => {
    updateOptionViaCli('klaro_geo_geo_resolution_mode', 'client');

    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();

    const payload = await page.evaluate(() => ({
      mode: window.klaroGeoTemplatePayload.mode,
      hasResolved: 'resolved' in window.klaroGeoTemplatePayload,
      templates: Object.keys(window.klaroGeoTemplatePayload.templates),
    }));
    expect(payload.mode).toBe('client');
    expect(payload.hasResolved).toBe(false);

    await page.waitForFunction(() =>
      (window.dataLayer || []).some(e => e && e.klaroEventName === 'klaroConfigLoaded')
    );
    const events = await configLoadedEvents(page);
    expect(events).toHaveLength(1);
    expect(events[0].klaroGeoResolutionMode).toBe('client');
    expect(['geoip-ajax', 'timezone', 'fallback']).toContain(events[0].klaroGeoGeoSource);
    expect(payload.templates).toContain(events[0].klaroGeoConsentTemplate);
    expect(typeof events[0].klaroGeoGeoLatencyMs).toBe('number');

    // klaroConsentData was filled in by the resolver before Klaro started
    const consentData = await page.evaluate(() => window.klaroConsentData);
    expect(consentData.templateName).toBe(events[0].klaroGeoConsentTemplate);
  });

  test('client mode: accepting consent still produces the consent update', async ({ page }) => {
    updateOptionViaCli('klaro_geo_geo_resolution_mode', 'client');

    await page.goto('/');
    await klaroHelper.waitForKlaroLoad();
    await klaroHelper.waitForModal();
    await klaroHelper.acceptAll();

    await page.waitForFunction(() =>
      (window.dataLayer || []).some(e => e && e.event === 'Klaro Consent Update' && e.consent_trigger === 'saveConsents')
    );
    const update = await page.evaluate(() =>
      window.dataLayer.find(e => e && e.event === 'Klaro Consent Update' && e.consent_trigger === 'saveConsents')
    );
    expect(update.acceptedServices.length).toBeGreaterThan(0);
  });
});
