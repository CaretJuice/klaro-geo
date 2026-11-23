/**
 * E2E Test: the Services admin page
 *
 * The page is driven by js/klaro-geo-admin-services.js, which has no unit
 * tests. Each test checks what ends up stored. The stored services and
 * templates are put back afterwards, because every other test relies on them.
 */

const { test, expect } = require('@playwright/test');
const { WordPressHelper, readOption, writeOption } = require('../helpers/wordpress');

const NAME = 'e2e-service';

/**
 * The stored services, as a list. The plugin stores them as a JSON string;
 * other tests' helpers write them back as an array.
 */
function asList(option) {
  return typeof option === 'string' ? JSON.parse(option) : option;
}

function storedServices() {
  return asList(readOption('klaro_geo_services'));
}

function storedService(name) {
  return storedServices().find(service => service.name === name);
}

function row(page, name) {
  return page.locator('#klaro-services-list tr').filter({
    has: page.locator('td:first-child', { hasText: new RegExp('^' + name + '$') }),
  });
}

async function saveForm(page) {
  await Promise.all([
    page.waitForNavigation(),
    page.click('#service-form button[type="submit"]'),
  ]);
}

test.describe('Services admin page', () => {
  let servicesBefore;
  let templatesBefore;

  test.beforeEach(async ({ page }) => {
    servicesBefore = readOption('klaro_geo_services');
    templatesBefore = readOption('klaro_geo_templates');

    // One more service to edit and delete, and a template with a second
    // language so the form offers a tab for it.
    const services = JSON.parse(JSON.stringify(asList(servicesBefore)));
    services.push({
      name: NAME,
      required: false,
      default: false,
      purposes: ['analytics'],
      cookies: ['_e2e'],
      translations: { zz: { title: 'E2E Service', description: 'Stored description' } },
    });
    writeOption('klaro_geo_services', JSON.stringify(services));

    const templates = JSON.parse(JSON.stringify(templatesBefore));
    templates.default.config.translations.de = { acceptAll: 'Alle akzeptieren' };
    writeOption('klaro_geo_templates', templates);

    page.on('dialog', dialog => dialog.accept());
    const wp = new WordPressHelper(page);
    await wp.login();
    await wp.gotoAdminPage('klaro-geo-services');
  });

  test.afterEach(() => {
    writeOption('klaro_geo_services', servicesBefore);
    writeOption('klaro_geo_templates', templatesBefore);
  });

  test('lists every stored service', async ({ page }) => {
    const names = storedServices().map(service => service.name);

    await expect(page.locator('#klaro-services-list tr')).toHaveCount(names.length);
    for (const name of names) {
      await expect(row(page, name)).toHaveCount(1);
    }
    await expect(row(page, NAME).locator('td').nth(3)).toHaveText('analytics');
  });

  test('editing a service shows what is stored and saves the changes', async ({ page }) => {
    const countBefore = storedServices().length;

    await row(page, NAME).locator('.edit-service').click();

    await expect(page.locator('#service_name')).toHaveValue(NAME);
    await expect(page.locator('#service_default')).toHaveValue('false');
    await expect(page.locator('#service_purposes_container input[value="analytics"]')).toBeChecked();
    await expect(page.locator('#service_purposes_container input[value="advertising"]')).not.toBeChecked();
    await expect(page.locator('#service_translations_zz_title')).toHaveValue('E2E Service');
    await expect(page.locator('#service_translations_zz_description')).toHaveValue('Stored description');

    await page.selectOption('#service_default', 'true');
    await page.selectOption('#service_required', 'global');
    await page.check('#service_purposes_container input[value="advertising"]');
    await page.fill('#service_translations_zz_description', 'Edited description');
    await saveForm(page);

    const service = storedService(NAME);
    expect(service.default).toBe(true);
    expect(service.required ?? null).toBeNull();
    expect(service.purposes).toEqual(['analytics', 'advertising']);
    expect(service.translations.zz.description).toBe('Edited description');
    expect(service.translations.zz.title).toBe('E2E Service');
    expect(storedServices()).toHaveLength(countBefore);
    await expect(row(page, NAME).locator('td').nth(2)).toHaveText('Yes');
  });

  test('a language used by a template gets a tab, and its text is stored', async ({ page }) => {
    await row(page, NAME).locator('.edit-service').click();

    await page.click('.translations-tabs-nav a[href="#service-tab-de"]');
    await page.fill('#service_translations_de_title', 'E2E Dienst');
    await page.fill('#service_translations_de_description', 'Beschreibung');
    await saveForm(page);

    const translations = storedService(NAME).translations;
    expect(translations.de).toEqual({ title: 'E2E Dienst', description: 'Beschreibung' });
    expect(translations.zz.title).toBe('E2E Service');
  });

  test('a new service is added to the stored list', async ({ page }) => {
    const countBefore = storedServices().length;

    await page.click('#add-new-service');
    await page.fill('#service_name', 'e2e-added');
    await page.selectOption('#service_required', 'false');
    await page.selectOption('#service_default', 'true');
    await page.check('#service_purposes_container input[value="advertising"]');
    await saveForm(page);

    const service = storedService('e2e-added');
    expect(service.required).toBe(false);
    expect(service.default).toBe(true);
    expect(service.purposes).toEqual(['advertising']);
    expect(service.translations.zz.title).toBeTruthy();
    expect(storedServices()).toHaveLength(countBefore + 1);
    await expect(row(page, 'e2e-added')).toHaveCount(1);
  });

  test('a service can be deleted', async ({ page }) => {
    await Promise.all([
      page.waitForNavigation(),
      row(page, NAME).locator('.delete-service').click(),
    ]);

    expect(storedService(NAME)).toBeUndefined();
    await expect(row(page, NAME)).toHaveCount(0);
  });

  test('a consent mode service cannot be deleted', async ({ page }) => {
    const messages = [];
    page.removeAllListeners('dialog');
    page.on('dialog', dialog => { messages.push(dialog.message()); dialog.accept(); });

    await row(page, 'ad-storage').locator('.delete-service').click();

    await expect.poll(() => messages.length).toBe(2);
    expect(messages[1]).toContain('cannot be deleted');
    expect(storedService('ad-storage')).toBeDefined();
  });
});
