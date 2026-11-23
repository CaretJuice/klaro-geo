/**
 * The consent receipt klaro-geo.js sends to the server records Global Privacy
 * Control: whether the browser sent the signal, and which services the plugin
 * switched off by default because of it.
 */


const { setUpPage } = require('./helpers/page.js');

describe('Consent receipt records GPC', function() {
    function setGpcSignal(value) {
        Object.defineProperty(navigator, 'globalPrivacyControl', {
            value: value,
            writable: true,
            configurable: true
        });
    }

    /**
     * Load klaro-geo.js, save consent, and return the receipt it posted.
     *
     * @param {Object|undefined} gpc window.klaroGeo.gpc as the bootstrap sets it.
     */
    function savedReceipt(gpc) {
        let watchCallbacks;
        document.body.innerHTML = '';
        setUpPage({
            enableConsentLogging: true,
            detectedCountry: 'US',
            detectedRegion: 'CA',
            ajaxUrl: '/wp-admin/admin-ajax.php'
        }, gpc ? { gpc: gpc } : {});
        window.klaroConfig = { services: [{ name: 'google-ads', purposes: ['advertising'] }] };
        window.klaroGeoLog = jest.fn();
        window.klaroGeoWarn = jest.fn();
        global.klaroGeoLog = window.klaroGeoLog;
        global.klaroGeoWarn = window.klaroGeoWarn;
        window.localStorage.clear();

        const manager = {
            consents: { 'google-ads': false },
            watch: jest.fn(function(callbacks) { watchCallbacks = callbacks; }),
            config: window.klaroConfig
        };
        window.klaro = { getManager: jest.fn(function() { return manager; }) };
        global.fetch = jest.fn(function() {
            return Promise.resolve({ ok: true, json: function() { return Promise.resolve({ success: true }); } });
        });

        jest.resetModules();
        require('../../js/klaro-geo.js');
        watchCallbacks.update(manager, 'saveConsents', manager.consents);

        expect(global.fetch).toHaveBeenCalledTimes(1);
        return JSON.parse(global.fetch.mock.calls[0][1].body.get('receipt_data'));
    }

    afterEach(function() {
        setGpcSignal(undefined);
        delete global.fetch;
        delete window.klaro;
        delete window.klaroConsentData;
    });

    test('signal present and applied', function() {
        setGpcSignal(true);
        const receipt = savedReceipt({ detected: true, enabled: true, affectedServices: ['google-ads'] });

        expect(receipt.gpc_signal).toBe(true);
        expect(receipt.gpc_affected_services).toEqual(['google-ads']);
    });

    test('signal present but GPC handling is off for the template', function() {
        setGpcSignal(true);
        const receipt = savedReceipt({ detected: false, enabled: false, affectedServices: [] });

        expect(receipt.gpc_signal).toBe(true);
        expect(receipt.gpc_affected_services).toEqual([]);
    });

    test('no signal', function() {
        const receipt = savedReceipt(undefined);

        expect(receipt.gpc_signal).toBe(false);
        expect(receipt.gpc_affected_services).toEqual([]);
    });
});

/**
 * A visitor whose browser sends GPC is opted out without touching the banner.
 * One receipt per browser records that.
 */
describe('GPC receipt for a visitor who has not used the banner', function() {
    const FLAG = 'klaro_geo_gpc_receipt_recorded';
    const applied = { detected: true, enabled: true, affectedServices: ['google-ads'] };

    /**
     * Load klaro-geo.js as on a page load and let its start-up timers run.
     *
     * @param {Object} options gpc, confirmed, enableConsentLogging, serverSuccess.
     * @returns {Array} The receipts posted to the server.
     */
    async function pageLoad(options) {
        document.body.innerHTML = '';
        setUpPage({
            enableConsentLogging: options.enableConsentLogging !== false,
            detectedCountry: 'US',
            detectedRegion: 'CA',
            ajaxUrl: '/wp-admin/admin-ajax.php'
        }, options.gpc ? { gpc: options.gpc } : {});
        window.klaroConfig = { services: [{ name: 'google-ads', purposes: ['advertising'] }] };
        window.klaroGeoLog = jest.fn();
        window.klaroGeoWarn = jest.fn();
        global.klaroGeoLog = window.klaroGeoLog;
        global.klaroGeoWarn = window.klaroGeoWarn;

        const manager = {
            consents: { 'google-ads': false },
            confirmed: options.confirmed === true,
            watch: jest.fn(),
            config: window.klaroConfig
        };
        window.klaro = { getManager: jest.fn(function() { return manager; }) };
        global.fetch = jest.fn(function() {
            return Promise.resolve({
                ok: true,
                json: function() { return Promise.resolve({ success: options.serverSuccess !== false }); }
            });
        });

        jest.resetModules();
        require('../../js/klaro-geo.js');
        document.dispatchEvent(new Event('DOMContentLoaded'));
        await new Promise(function(resolve) { setTimeout(resolve, 20); });

        return global.fetch.mock.calls.map(function(call) {
            return JSON.parse(call[1].body.get('receipt_data'));
        });
    }

    beforeEach(function() {
        window.localStorage.clear();
        jest.spyOn(console, 'error').mockImplementation(function() {});
    });

    afterEach(function() {
        console.error.mockRestore();
        delete global.fetch;
        delete window.klaro;
        delete window.klaroConsentData;
        delete window.klaroGeo;
    });

    test('is written once, marked as a GPC receipt, and not on the next page load', async function() {
        const first = await pageLoad({ gpc: applied });

        expect(first).toHaveLength(1);
        expect(first[0].receipt_type).toBe('gpc');
        expect(first[0].gpc_affected_services).toEqual(['google-ads']);
        expect(first[0].consent_choices).toEqual({ 'google-ads': false });
        expect(window.localStorage.getItem(FLAG)).toBe('1');

        const events = window.dataLayer.filter(function(e) { return e && e.klaroEventName === 'generateConsentReceipt'; });
        expect(events).toHaveLength(1);
        expect(events[0].klaroGeoConsentReceiptType).toBe('gpc');

        expect(await pageLoad({ gpc: applied })).toHaveLength(0);
    });

    test('leaves no receipt ID in the browser', async function() {
        const receipts = await pageLoad({ gpc: applied });

        expect(window.localStorage.getItem('klaro_consent_receipts')).toBeNull();
        expect(JSON.stringify(window.localStorage)).not.toContain(receipts[0].receipt_id);
    });

    test('is not written when the visitor already saved a choice', async function() {
        expect(await pageLoad({ gpc: applied, confirmed: true })).toHaveLength(0);
    });

    test('is not written without the signal, or when GPC handling is off', async function() {
        expect(await pageLoad({})).toHaveLength(0);
        expect(await pageLoad({ gpc: { detected: false, enabled: false, affectedServices: [] } })).toHaveLength(0);
    });

    test('is not written when consent logging is off, and is not marked as done', async function() {
        expect(await pageLoad({ gpc: applied, enableConsentLogging: false })).toHaveLength(0);
        expect(window.localStorage.getItem(FLAG)).toBeNull();
    });

    test('is tried again on the next page load when the server did not store it', async function() {
        expect(await pageLoad({ gpc: applied, serverSuccess: false })).toHaveLength(1);
        expect(window.localStorage.getItem(FLAG)).toBeNull();

        expect(await pageLoad({ gpc: applied })).toHaveLength(1);
        expect(window.localStorage.getItem(FLAG)).toBe('1');
    });
});
