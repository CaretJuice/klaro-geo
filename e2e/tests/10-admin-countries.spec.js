/**
 * E2E Test: the Country Settings admin page
 *
 * The page is driven by js/klaro-geo-admin.js, which has no unit tests. Each
 * test checks what ends up stored. The stored country settings and the list
 * of visible countries are put back afterwards.
 */

const { test, expect } = require('@playwright/test');
const { WordPressHelper, readOption, writeOption } = require('../helpers/wordpress');

function countrySelect(page, code) {
  return page.locator(`select[name="klaro_geo_country_settings[${code}][template]"]`);
}

function countryRow(page, code) {
  return page.locator('tr.country-row').filter({ has: countrySelect(page, code) });
}

function storedCountries() {
  return readOption('klaro_geo_country_settings') || {};
}

test.describe('Country Settings admin page', () => {
  let countriesBefore;
  let visibleBefore;

  test.beforeEach(async ({ page }) => {
    countriesBefore = readOption('klaro_geo_country_settings');
    visibleBefore = readOption('klaro_geo_visible_countries');
    writeOption('klaro_geo_country_settings', { default_template: 'default' });
    writeOption('klaro_geo_visible_countries', false);

    page.on('dialog', dialog => dialog.accept());
    const wp = new WordPressHelper(page);
    await wp.login();
    await wp.gotoAdminPage('klaro-geo-country');
  });

  test.afterEach(() => {
    writeOption('klaro_geo_country_settings', countriesBefore);
    writeOption('klaro_geo_visible_countries', visibleBefore);
  });

  test('choosing a template for a country saves it without pressing Save', async ({ page }) => {
    await countrySelect(page, 'US').selectOption('relaxed');
    await expect(page.locator('.klaro-autosave-success')).toHaveText('Saved');

    expect(storedCountries().US.template).toBe('relaxed');

    // Back to the fallback template: the country no longer needs an entry.
    await countrySelect(page, 'US').selectOption('inherit');
    await expect.poll(() => 'US' in storedCountries()).toBe(false);
  });

  test('the fallback template is saved the same way', async ({ page }) => {
    await page.selectOption('select[name="klaro_geo_country_settings[default_template]"]', 'strict');
    await expect(page.locator('.klaro-autosave-success')).toHaveText('Saved');

    expect(storedCountries().default_template).toBe('strict');
  });

  test('the Save button stores the form and confirms it', async ({ page }) => {
    // Set the value without the change event, so only the button saves it.
    await countrySelect(page, 'DE').evaluate(select => { select.value = 'strict'; });
    await Promise.all([
      page.waitForNavigation(),
      page.click('#submit'),
    ]);

    await expect(page.locator('.notice-success')).toContainText('Country settings saved.');
    await expect(countrySelect(page, 'DE')).toHaveValue('strict');
    expect(storedCountries().DE.template).toBe('strict');
  });

  test('a region can be given its own template, and set back', async ({ page }) => {
    await countryRow(page, 'US').locator('.manage-regions').click();
    const california = page.locator('#region-modal-US select[data-region="CA"]');
    await expect(california).toHaveValue('inherit');
    await expect(page.locator('#region-modal-US .region-list tr', { hasText: 'US-CA' })).toContainText('California');

    await california.selectOption('strict');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#save-regions-US'),
    ]);

    expect(storedCountries().US.regions).toEqual({ CA: 'strict' });
    await expect(countryRow(page, 'US').locator('.regions-list')).toHaveText('CA');

    await countryRow(page, 'US').locator('.manage-regions').click();
    await expect(california).toHaveValue('strict');
    await california.selectOption('inherit');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#save-regions-US'),
    ]);

    expect(Object.keys((storedCountries().US || {}).regions || {})).toEqual([]);
    await expect(countryRow(page, 'US').locator('.no-regions')).toHaveText('None');
  });

  test('a country template set while a region override exists keeps the override', async ({ page }) => {
    writeOption('klaro_geo_country_settings', {
      default_template: 'default',
      US: { template: 'inherit', regions: { CA: 'strict' } },
    });
    await page.reload();

    await countrySelect(page, 'US').selectOption('relaxed');
    await expect(page.locator('.klaro-autosave-success')).toHaveText('Saved');

    expect(storedCountries().US).toEqual({ template: 'relaxed', regions: { CA: 'strict' } });
  });

  test('a country can be removed from the list and added back', async ({ page }) => {
    await expect(countryRow(page, 'JP')).toHaveCount(1);

    await page.click('#manage-countries');
    await page.uncheck('#country-modal input[value="JP"]');
    await page.check('#country-modal input[value="NZ"]');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#country-modal .save-countries'),
    ]);

    await expect(countryRow(page, 'JP')).toHaveCount(0);
    await expect(countryRow(page, 'NZ')).toHaveCount(1);
    const visible = readOption('klaro_geo_visible_countries');
    expect(visible).not.toContain('JP');
    expect(visible).toContain('NZ');
    expect(visible).toContain('US');
  });
});
