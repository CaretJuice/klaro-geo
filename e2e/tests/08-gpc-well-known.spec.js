/**
 * E2E Test: /.well-known/gpc.json
 *
 * The E2E site uses plain permalinks, where WordPress does not apply rewrite
 * rules, so the handler is reached through its query variable. That the
 * /.well-known/gpc.json URL maps to that variable is covered by
 * tests/phpunit/klaroGeoGpcWellKnownTest.php.
 */

const { test, expect } = require('@playwright/test');
const { wpCli } = require('../helpers/wordpress');

const URL = '/?klaro_geo_gpc_json=1';

test.describe('GPC support statement', () => {
  test.afterEach(() => {
    wpCli('option delete klaro_geo_gpc_well_known');
  });

  test('is a 404 while the setting is off', async ({ request }) => {
    wpCli('option delete klaro_geo_gpc_well_known');

    const response = await request.get(URL);

    expect(response.status()).toBe(404);
    expect(await response.text()).toBe('');
  });

  test('is served as cacheable JSON when the setting is on', async ({ request }) => {
    wpCli('option update klaro_geo_gpc_well_known 1');

    const response = await request.get(URL);

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/json');
    expect(response.headers()['cache-control']).toBe('public, max-age=86400');
    const body = await response.json();
    expect(body.gpc).toBe(true);
    expect(body.lastUpdate).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });
});
