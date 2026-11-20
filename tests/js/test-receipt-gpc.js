/**
 * The consent receipt klaro-geo.js sends to the server records Global Privacy
 * Control: whether the browser sent the signal, and which services the plugin
 * switched off by default because of it.
 */

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
        window.dataLayer = [];
        window.klaroConfig = { services: [{ name: 'google-ads', purposes: ['advertising'] }] };
        window.gtag = jest.fn();
        window.klaroGeoLog = jest.fn();
        window.klaroGeoWarn = jest.fn();
        global.klaroGeoLog = window.klaroGeoLog;
        global.klaroGeoWarn = window.klaroGeoWarn;
        delete window.klaroGeo;
        window.localStorage.clear();

        const manager = {
            consents: { 'google-ads': false },
            watch: jest.fn(function(callbacks) { watchCallbacks = callbacks; }),
            config: window.klaroConfig
        };
        window.klaro = { getManager: jest.fn(function() { return manager; }) };
        window.klaroConsentData = {
            enableConsentLogging: true,
            templateName: 'default',
            templateSource: 'fallback',
            detectedCountry: 'US',
            detectedRegion: 'CA',
            adminOverride: false,
            ajaxUrl: '/wp-admin/admin-ajax.php'
        };
        global.fetch = jest.fn(function() {
            return Promise.resolve({ ok: true, json: function() { return Promise.resolve({ success: true }); } });
        });

        jest.resetModules();
        require('../../js/klaro-geo.js');
        if (gpc) {
            window.klaroGeo.gpc = gpc;
        }
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
