/**
 * klaro-geo.js decides whether to generate and send a consent receipt from
 * window.klaroConsentData.enableConsentLogging alone. It must not read the
 * setting back out of the dataLayer.
 */

describe('Consent receipt generation follows klaroConsentData.enableConsentLogging', function() {
    let watchCallbacks;

    function loadWith(consentData) {
        document.body.innerHTML = '';
        window.dataLayer = [];
        window.klaroConfig = { services: [{ name: 'google-analytics', purposes: ['analytics'] }] };
        window.gtag = jest.fn();
        window.klaroGeoLog = jest.fn();
        window.klaroGeoWarn = jest.fn();
        global.klaroGeoLog = window.klaroGeoLog;
        global.klaroGeoWarn = window.klaroGeoWarn;
        delete window.klaroGeo;
        window.localStorage.clear();

        const manager = {
            consents: { 'google-analytics': true },
            watch: jest.fn(function(callbacks) { watchCallbacks = callbacks; }),
            config: window.klaroConfig
        };
        window.klaro = { getManager: jest.fn(function() { return manager; }) };

        if (consentData === undefined) {
            delete window.klaroConsentData;
        } else {
            window.klaroConsentData = consentData;
        }

        global.fetch = jest.fn(function() {
            return Promise.resolve({ ok: true, json: function() { return Promise.resolve({ success: true }); } });
        });

        jest.resetModules();
        require('../../js/klaro-geo.js');
        return manager;
    }

    function receiptEvents() {
        return window.dataLayer.filter(function(e) {
            return e && e.klaroEventName === 'generateConsentReceipt';
        });
    }

    function receiptPosts() {
        return global.fetch.mock.calls.length;
    }

    const base = {
        templateName: 'default',
        templateSource: 'fallback',
        detectedCountry: 'US',
        detectedRegion: '',
        adminOverride: false,
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: 'test-nonce'
    };

    afterEach(function() {
        delete global.fetch;
        delete window.klaro;
        delete window.klaroConsentData;
    });

    test('true → receipt is generated and sent', function() {
        const manager = loadWith(Object.assign({ enableConsentLogging: true }, base));
        watchCallbacks.update(manager, 'saveConsents', manager.consents);

        expect(receiptEvents()).toHaveLength(1);
        expect(receiptPosts()).toBe(1);
    });

    test('false → no receipt, even if a klaroConfigLoaded event in the dataLayer says true', function() {
        const manager = loadWith(Object.assign({ enableConsentLogging: false }, base));
        window.dataLayer.push({
            event: 'Klaro Event',
            klaroEventName: 'klaroConfigLoaded',
            klaroGeoEnableConsentLogging: true
        });
        watchCallbacks.update(manager, 'saveConsents', manager.consents);

        expect(receiptEvents()).toHaveLength(0);
        expect(receiptPosts()).toBe(0);
    });

    test('klaroConsentData missing → no receipt and no error', function() {
        const manager = loadWith(undefined);
        window.dataLayer.push({
            event: 'Klaro Event',
            klaroEventName: 'klaroConfigLoaded',
            klaroGeoEnableConsentLogging: true
        });

        expect(function() {
            watchCallbacks.update(manager, 'saveConsents', manager.consents);
        }).not.toThrow();
        expect(receiptEvents()).toHaveLength(0);
        expect(receiptPosts()).toBe(0);
    });

    test('a string "1" is not treated as enabled (the value is always a real boolean now)', function() {
        const manager = loadWith(Object.assign({ enableConsentLogging: '1' }, base));
        watchCallbacks.update(manager, 'saveConsents', manager.consents);

        expect(receiptEvents()).toHaveLength(0);
        expect(receiptPosts()).toBe(0);
    });
});
