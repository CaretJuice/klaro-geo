/**
 * E2E Test: consent receipts admin page
 *
 * Receipts are submitted by anonymous visitors, so the admin page that shows
 * them must never interpret their content as HTML.
 */

const { test, expect } = require('@playwright/test');
const { WordPressHelper, wpCli } = require('../helpers/wordpress');

const RECEIPT_ID = 'e2e_hostile_receipt';
const PAYLOAD = '</pre><img src=x onerror=window.__klaroReceiptXss=1>';

function deleteTestReceipt() {
  wpCli(`eval 'global $wpdb; $wpdb->delete($wpdb->prefix . "klaro_geo_consent_receipts", array("receipt_id" => "${RECEIPT_ID}"));'`);
}

test.describe('Consent receipts admin page', () => {
  test.beforeEach(() => {
    deleteTestReceipt();
    // Write the row directly, bypassing the endpoint's validation, so this
    // test covers the page on its own: it must be safe even with hostile data
    // already in the table.
    const row = {
      receipt_id: RECEIPT_ID,
      // Far in the future so the row is first on the page (newest first)
      timestamp: '2099-01-01 00:00:00',
      consent_data: JSON.stringify({ x: PAYLOAD }),
      template_name: PAYLOAD,
      template_source: 'fallback',
      klaro_config: JSON.stringify({ a: PAYLOAD }),
    };
    wpCli(
      `eval 'global $wpdb; $wpdb->insert($wpdb->prefix . "klaro_geo_consent_receipts", json_decode(file_get_contents("php://stdin"), true));'`,
      { stdin: JSON.stringify(row) }
    );
  });

  test.afterEach(() => {
    deleteTestReceipt();
  });

  test('receipt details are shown as text, not parsed as HTML', async ({ page }) => {
    const wp = new WordPressHelper(page);
    await wp.login();
    await page.goto('/wp-admin/admin.php?page=klaro-geo-consent-receipts');

    const row = page.locator('tr', { hasText: RECEIPT_ID });
    await expect(row).toHaveCount(1);
    await row.locator('.view-receipt').click();
    await expect(page.locator('#receipt-modal')).toBeVisible();
    await expect(page.locator('#receipt-details')).toContainText(RECEIPT_ID);

    // The payload is visible as text...
    await expect(page.locator('#receipt-details')).toContainText('onerror=window.__klaroReceiptXss=1');
    // ...and created no element and ran no script
    await expect(page.locator('#receipt-details img')).toHaveCount(0);
    await page.waitForTimeout(500);
    expect(await page.evaluate(() => window.__klaroReceiptXss)).toBeUndefined();
  });
});
