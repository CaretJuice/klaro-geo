/**
 * E2E Test: the Templates admin page
 *
 * The translation tabs, the JSON editor and the buttons that keep the two in
 * step are driven by js/klaro-geo-admin-templates.js, which has no unit
 * tests. Each test starts from a template with two languages, stored
 * directly, and checks what ends up stored.
 */

const { test, expect } = require('@playwright/test');
const { WordPressHelper, wpCli } = require('../helpers/wordpress');

const KEY = 'e2e-translations';
const CREATED_KEY = 'e2e-created';

/** Run PHP on the site; the code reads its input, if any, from stdin as JSON. */
function php(code, input) {
  return wpCli(`eval '${code}'`, input === undefined ? {} : { stdin: JSON.stringify(input) });
}

function storeTemplate() {
  php(
    '$t = get_option("klaro_geo_templates"); $in = json_decode(file_get_contents("php://stdin"), true);' +
    ' $new = $t["default"]; $new["name"] = "E2E Translations";' +
    ' $new["config"]["translations"] = $in; $t["' + KEY + '"] = $new; update_option("klaro_geo_templates", $t);',
    {
      zz: { acceptAll: 'Accept all', decline: 'I decline', consentModal: { title: 'Privacy Settings' } },
      de: { acceptAll: 'Alle akzeptieren', decline: 'Ablehnen', consentModal: { title: 'Datenschutz' } },
    }
  );
}

function removeTemplates() {
  php(
    '$t = get_option("klaro_geo_templates"); unset($t["' + KEY + '"], $t["' + CREATED_KEY + '"]);' +
    ' update_option("klaro_geo_templates", $t);'
  );
}

/** The template as stored now, or null. */
function stored(key) {
  return JSON.parse(php('$t = get_option("klaro_geo_templates"); echo json_encode(isset($t["' + key + '"]) ? $t["' + key + '"] : null);'));
}

function field(page, lang, name) {
  return page.locator(`[name="template_config[translations][${lang}][${name}]"]`);
}

function tab(page, lang) {
  return page.locator(`.translations-tabs-nav a[href="#tab-${lang}"]`);
}

async function editorJson(page) {
  return JSON.parse(await page.inputValue('#translations_json_editor'));
}

test.describe('Templates admin page', () => {
  test.beforeEach(async ({ page }) => {
    removeTemplates();
    storeTemplate();
    page.on('dialog', dialog => dialog.accept());
    const wp = new WordPressHelper(page);
    await wp.login();
    await page.goto(`/wp-admin/admin.php?page=klaro-geo-templates&template=${KEY}`);
    await expect(page.locator('#template_selector')).toHaveValue(KEY);
  });

  test.afterEach(() => {
    removeTemplates();
  });

  test('shows a tab and the stored text for each language', async ({ page }) => {
    await expect(tab(page, 'zz')).toBeVisible();
    await expect(tab(page, 'de')).toBeVisible();

    await expect(field(page, 'zz', 'acceptAll')).toHaveValue('Accept all');
    await expect(field(page, 'de', 'acceptAll')).toHaveValue('Alle akzeptieren');
    expect((await editorJson(page)).de.decline).toBe('Ablehnen');
  });

  test('adding a language copied from another stores it with that text', async ({ page }) => {
    await page.click('.translations-tabs-nav a[href="#tab-add"]');
    await page.selectOption('#new_language_code', 'fr');
    await page.selectOption('#copy_from_language', 'de');
    await page.click('#add_language_translation');

    await expect(tab(page, 'fr')).toBeVisible();
    await expect(field(page, 'fr', 'acceptAll')).toHaveValue('Alle akzeptieren');
    await expect(page.locator('.klaro-autosave-success')).toContainText('added successfully');

    const translations = stored(KEY).config.translations;
    expect(Object.keys(translations).sort()).toEqual(['de', 'fr', 'zz']);
    expect(translations.fr.acceptAll).toBe('Alle akzeptieren');
    expect(translations.de.acceptAll).toBe('Alle akzeptieren');
  });

  test('deleting a language removes its tab and its stored text', async ({ page }) => {
    await tab(page, 'de').click();
    await page.click('.delete-language-btn[data-lang="de"]');

    await expect(tab(page, 'de')).toHaveCount(0);
    await expect(page.locator('.klaro-autosave-success')).toContainText('deleted successfully');

    const translations = stored(KEY).config.translations;
    expect(Object.keys(translations)).toEqual(['zz']);
    expect(translations.zz.acceptAll).toBe('Accept all');
  });

  test('the JSON editor updates the fields, and the fields update the JSON editor', async ({ page }) => {
    const json = await editorJson(page);
    json.zz.acceptAll = 'Accept everything';
    json.es = { acceptAll: 'Aceptar todo' };
    await page.fill('#translations_json_editor', JSON.stringify(json));
    await page.click('#update_form_from_json');

    await expect(field(page, 'zz', 'acceptAll')).toHaveValue('Accept everything');
    await expect(tab(page, 'es')).toBeVisible();
    await expect(field(page, 'es', 'acceptAll')).toHaveValue('Aceptar todo');

    await tab(page, 'de').click();
    await field(page, 'de', 'decline').fill('Nein danke');
    await page.click('#update_json_from_form');

    expect((await editorJson(page)).de.decline).toBe('Nein danke');
  });

  test('invalid JSON in the editor is reported and changes nothing', async ({ page }) => {
    const messages = [];
    page.removeAllListeners('dialog');
    page.on('dialog', dialog => { messages.push(dialog.message()); dialog.accept(); });

    await page.fill('#translations_json_editor', '{"zz": {"acceptAll": "broken"');
    await page.click('#update_form_from_json');

    expect(messages).toHaveLength(1);
    expect(messages[0]).toContain('Error parsing JSON');
    await expect(field(page, 'zz', 'acceptAll')).toHaveValue('Accept all');
  });

  test('saving the form stores the edited text and keeps every language', async ({ page }) => {
    await tab(page, 'de').click();
    await field(page, 'de', 'acceptAll').fill('Alles akzeptieren');
    await Promise.all([
      page.waitForNavigation(),
      page.click('input[name="submit_template"]'),
    ]);

    await expect(page.locator('#template_selector')).toHaveValue(KEY);
    await expect(field(page, 'de', 'acceptAll')).toHaveValue('Alles akzeptieren');

    const translations = stored(KEY).config.translations;
    expect(translations.de.acceptAll).toBe('Alles akzeptieren');
    expect(translations.de.consentModal.title).toBe('Datenschutz');
    expect(translations.zz.acceptAll).toBe('Accept all');
  });

  test('a new template is created as a copy of the one chosen', async ({ page }) => {
    await page.click('#add_template');
    await page.fill('#new_template_name', 'E2E Created');
    await page.selectOption('#inherit_from_template', KEY);
    await page.click('#create_template');

    await expect.poll(() => stored(CREATED_KEY) !== null).toBe(true);

    const created = stored(CREATED_KEY);
    expect(created.name).toBe('E2E Created');
    expect(created.config.translations.de.acceptAll).toBe('Alle akzeptieren');
    expect(created.config.default).toBe(stored(KEY).config.default);
  });
});
