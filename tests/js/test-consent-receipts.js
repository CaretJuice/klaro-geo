/**
 * Consent receipts in js/klaro-geo.js: what a receipt holds, the copy kept in
 * the browser, the request to the server and the dataLayer event.
 *
 * Whether a receipt is generated at all is in test-consent-logging-flag.js;
 * the GPC fields and the GPC receipt are in test-receipt-gpc.js.
 */

const { loadConsentScript } = require('./helpers/page.js');

describe('Consent receipt', function() {
    const SERVICES = [
        { name: 'google-analytics', purposes: ['analytics'] },
        { name: 'google-ads', purposes: ['advertising'] }
    ];

    function load(consentData) {
        return loadConsentScript({
            services: SERVICES,
            consents: { 'google-analytics': true, 'google-ads': false },
            consentData: Object.assign({
                consentReceipts: true,
                enableConsentLogging: true,
                templateName: 'strict',
                templateSource: 'country',
                detectedCountry: 'CA',
                detectedRegion: 'BC',
                ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php'
            }, consentData)
        });
    }

    function save(loaded) {
        loaded.watch.update(loaded.manager, 'saveConsents', loaded.manager.consents);
    }

    function serverResponds(response) {
        global.fetch = jest.fn(function() { return Promise.resolve(response); });
    }

    function postedBody() {
        return global.fetch.mock.calls[0][1].body;
    }

    function storedReceipts() {
        return JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
    }

    // Let the promise chain of the request finish.
    function settle() {
        return new Promise(function(resolve) { setTimeout(resolve, 0); });
    }

    beforeEach(function() {
        window.localStorage.clear();
        global.klaroGeoError = jest.fn();
        serverResponds({ ok: true, json: function() { return Promise.resolve({ success: true }); } });
    });

    afterEach(function() {
        delete global.fetch;
        delete window.klaro;
        delete window.klaroConfig;
        delete window.klaroConsentData;
        delete window.klaroGeo;
    });

    test('holds the choices as booleans, the template and the location', function() {
        const loaded = load();
        save(loaded);

        const receipt = JSON.parse(postedBody().get('receipt_data'));
        expect(receipt.receipt_id).toMatch(/^receipt_\d+_[a-z0-9]+$/);
        expect(receipt.receipt_type).toBe('choice');
        expect(Math.abs(receipt.timestamp - Date.now() / 1000)).toBeLessThan(5);
        expect(receipt.consent_choices).toEqual({ 'google-analytics': true, 'google-ads': false });
        expect(receipt.template_name).toBe('strict');
        expect(receipt.template_source).toBe('country');
        expect(receipt.country_code).toBe('CA');
        expect(receipt.region_code).toBe('BC');
        expect(receipt.admin_override).toBe(false);
        // The server stores its own copy of the Klaro config with the receipt.
        expect(receipt).not.toHaveProperty('klaro_config');
    });

    test('is posted to the receipt action at the URL PHP gave', function() {
        save(load());

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch.mock.calls[0][0]).toBe('https://example.test/wp-admin/admin-ajax.php');
        expect(global.fetch.mock.calls[0][1].method).toBe('POST');
        expect(postedBody().get('action')).toBe('klaro_geo_store_consent_receipt');
    });

    test('carries the nonce for a logged-in visitor, and none for an anonymous one', function() {
        save(load({ nonce: 'abc123' }));
        expect(postedBody().get('nonce')).toBe('abc123');

        global.fetch.mockClear();
        save(load());
        expect(postedBody().has('nonce')).toBe(false);
    });

    test('pushes generateConsentReceipt with the receipt ID', function() {
        save(load());

        const events = window.dataLayer.filter(function(e) { return e && e.klaroEventName === 'generateConsentReceipt'; });
        expect(events).toHaveLength(1);
        expect(events[0]).toEqual({
            event: 'Klaro Event',
            eventSource: 'klaro-geo',
            klaroEventName: 'generateConsentReceipt',
            klaroGeoConsentReceipt: JSON.parse(postedBody().get('receipt_data')).receipt_id,
            klaroGeoConsentReceiptType: 'choice',
            klaroGeoTemplateSource: 'country',
            klaroGeoAdminOverride: false,
            klaroGeoEnableConsentLogging: true
        });
    });

    test('a copy is kept in the browser', function() {
        save(load());

        expect(storedReceipts()).toHaveLength(1);
        expect(storedReceipts()[0].receipt_id).toBe(JSON.parse(postedBody().get('receipt_data')).receipt_id);
    });

    test('the browser keeps the ten most recent receipts', function() {
        const older = [];
        for (let i = 0; i < 10; i++) {
            older.push({ receipt_id: 'old_' + i });
        }
        window.localStorage.setItem('klaro_consent_receipts', JSON.stringify(older));

        save(load());

        const stored = storedReceipts();
        expect(stored).toHaveLength(10);
        expect(stored[0].receipt_id).toBe('old_1');
        expect(stored[9].receipt_id).toMatch(/^receipt_/);
    });

    test.each([
        ['is not JSON', 'not json'],
        ['is JSON but not a list', '{"receipt_id":"x"}']
    ])('stored receipts are started again when what is stored %s', function(name, existing) {
        window.localStorage.setItem('klaro_consent_receipts', existing);

        save(load());

        expect(storedReceipts()).toHaveLength(1);
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    test('an error status from the server is reported, with the start of its response', async function() {
        serverResponds({ ok: false, status: 429, text: function() { return Promise.resolve('{"success":false,"data":"Too many requests"}'); } });

        save(load());
        await settle();

        expect(global.klaroGeoError).toHaveBeenCalledTimes(1);
        expect(global.klaroGeoError.mock.calls[0][0]).toBe('Error sending consent receipt:');
        expect(global.klaroGeoError.mock.calls[0][1].message).toContain('429');
        expect(global.klaroGeoError.mock.calls[0][1].message).toContain('Too many requests');
    });

    test('a refusal from the server is reported', async function() {
        serverResponds({ ok: true, json: function() { return Promise.resolve({ success: false, data: 'Invalid receipt' }); } });

        save(load());
        await settle();

        expect(global.klaroGeoError).toHaveBeenCalledWith('Failed to store consent receipt:', { success: false, data: 'Invalid receipt' });
    });

    test('a network failure is reported and does not stop the consent update', async function() {
        global.fetch = jest.fn(function() { return Promise.reject(new Error('offline')); });
        const loaded = load();

        save(loaded);
        await new Promise(function(resolve) { setTimeout(resolve, 80); });

        expect(global.klaroGeoError.mock.calls[0][0]).toBe('Error sending consent receipt:');
        expect(window.dataLayer.some(function(e) { return e && e.event === 'Klaro Consent Update'; })).toBe(true);
    });
});
