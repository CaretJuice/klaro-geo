/**
 * GPC (Global Privacy Control) Tests
 *
 * Drives the real template-application code (applyResolution in
 * js/klaro-geo-bootstrap.js) with a server-mode payload, and checks what GPC
 * does to service defaults, the consent state and the dataLayer.
 */

const { setUpPage, loadBootstrap } = require('./helpers/page.js');
const clientGeo = loadBootstrap();

describe('GPC', function() {
    function setGpcSignal(value) {
        Object.defineProperty(navigator, 'globalPrivacyControl', {
            value: value,
            writable: true,
            configurable: true
        });
    }

    /**
     * Apply a server-mode payload holding one template.
     *
     * @param {Array}   services   Services for klaroConfig.
     * @param {boolean} gpcEnabled Whether GPC is enabled for the template.
     * @returns {Object} window.klaroGeo.gpc
     */
    function apply(services, gpcEnabled) {
        const payload = {
            mode: 'server',
            resolved: {
                template: 'default',
                templateSource: 'fallback',
                country: 'US',
                region: '',
                geoSource: 'server',
                adminOverride: false
            },
            rules: { defaultTemplate: 'default' },
            templates: {
                'default': {
                    config: { services: services, storageMethod: 'cookie' },
                    consentDefaults: {},
                    gtagSettings: { ads_data_redaction: 'true', url_passthrough: 'false' },
                    enableConsentLogging: false,
                    gpcEnabled: gpcEnabled !== false,
                    templateSettings: {}
                }
            },
            consentReceipts: false
        };
        clientGeo.applyResolution(payload, { country: 'US', region: '', source: 'server', latencyMs: null });
        return window.klaroGeo.gpc;
    }

    function consentUpdates() {
        return window.gtag.mock.calls.filter(function(call) {
            return call[0] === 'consent' && call[1] === 'update';
        });
    }

    function events(name) {
        return window.dataLayer.filter(function(e) {
            return e && e.klaroEventName === name;
        });
    }

    beforeEach(function() {
        setUpPage();
        window.klaro = { setup: jest.fn() };
        delete window.klaroConfig;
        window.localStorage.clear();
        setGpcSignal(undefined);
    });

    afterEach(function() {
        delete window.klaroGeo;
        delete window.klaroConfig;
        delete window.klaro;
        jest.clearAllMocks();
    });

    describe('signal detection', function() {
        test('detected when navigator.globalPrivacyControl is true', function() {
            setGpcSignal(true);
            const gpc = apply([]);
            expect(gpc.detected).toBe(true);
            expect(gpc.enabled).toBe(true);
        });

        test.each([
            ['false', false],
            ['undefined', undefined],
            ['the string "1"', '1']
        ])('not detected when the signal is %s', function(label, value) {
            setGpcSignal(value);
            expect(apply([]).detected).toBe(false);
        });

        test('not detected when GPC is disabled for the template', function() {
            setGpcSignal(true);
            const services = [{ name: 'ads', gpc_sensitive: true, default: true }];
            const gpc = apply(services, false);

            expect(gpc.enabled).toBe(false);
            expect(gpc.detected).toBe(false);
            expect(gpc.affectedServices).toEqual([]);
            expect(window.klaroConfig.services[0].default).toBe(true);
        });
    });

    describe('service defaults', function() {
        test('gpc_sensitive services default to false', function() {
            setGpcSignal(true);
            const gpc = apply([
                { name: 'ads', gpc_sensitive: true, default: true },
                { name: 'analytics', gpc_sensitive: true }
            ]);

            expect(window.klaroConfig.services[0].default).toBe(false);
            expect(window.klaroConfig.services[1].default).toBe(false);
            expect(gpc.affectedServices).toEqual(['ads', 'analytics']);
        });

        test('required services are left alone even if gpc_sensitive', function() {
            setGpcSignal(true);
            const gpc = apply([{ name: 'gtm', gpc_sensitive: true, required: true, default: true }]);

            expect(window.klaroConfig.services[0].default).toBe(true);
            expect(gpc.affectedServices).toEqual([]);
        });

        test('services that are not gpc_sensitive are left alone', function() {
            setGpcSignal(true);
            const gpc = apply([
                { name: 'ads', gpc_sensitive: true, default: true },
                { name: 'functional', gpc_sensitive: false, default: true }
            ]);

            expect(window.klaroConfig.services[1].default).toBe(true);
            expect(gpc.affectedServices).toEqual(['ads']);
        });

        test('no signal → defaults untouched', function() {
            const gpc = apply([{ name: 'ads', gpc_sensitive: true, default: true }]);

            expect(window.klaroConfig.services[0].default).toBe(true);
            expect(gpc.affectedServices).toEqual([]);
        });
    });

    describe('consent mode', function() {
        // A consent mode service is denied under its consent_mode_key only; it
        // has no *_consent key (specs/frontend-data.spec.yaml).
        test('denies the keys of gpc_sensitive services', function() {
            setGpcSignal(true);
            apply([
                { name: 'ad-storage', gpc_sensitive: true, is_consent_mode_service: true, consent_mode_key: 'ad_storage' },
                { name: 'facebook-pixel', gpc_sensitive: true },
                { name: 'functional', gpc_sensitive: false }
            ]);

            const updates = consentUpdates();
            expect(updates).toHaveLength(1);
            expect(updates[0][2]).toEqual({
                ad_storage: 'denied',
                facebook_pixel_consent: 'denied'
            });
        });

        test('no consent update when no service is affected', function() {
            setGpcSignal(true);
            apply([{ name: 'functional', gpc_sensitive: false }]);
            expect(consentUpdates()).toHaveLength(0);
        });

        test('no consent update without the signal', function() {
            apply([{ name: 'ads', gpc_sensitive: true }]);
            expect(consentUpdates()).toHaveLength(0);
        });
    });

    describe('dataLayer', function() {
        test('klaroConfigLoaded carries the GPC fields', function() {
            setGpcSignal(true);
            apply([{ name: 'ads', gpc_sensitive: true }]);

            const loaded = events('klaroConfigLoaded');
            expect(loaded).toHaveLength(1);
            expect(loaded[0].klaroGeoGPCDetected).toBe(true);
            expect(loaded[0].klaroGeoGPCEnabled).toBe(true);
            expect(loaded[0].klaroGeoGPCAffectedServices).toEqual(['ads']);
        });

        test('gpcDetected fires after klaroConfigLoaded when the signal is active', function() {
            setGpcSignal(true);
            apply([{ name: 'ads', gpc_sensitive: true }]);

            const names = window.dataLayer
                .filter(function(e) { return e && e.klaroEventName; })
                .map(function(e) { return e.klaroEventName; });
            expect(names).toEqual(['klaroConfigLoaded', 'gpcDetected']);
            expect(events('gpcDetected')[0].klaroGeoGPCAffectedServices).toEqual(['ads']);
        });

        test('gpcDetected does not fire without the signal', function() {
            apply([{ name: 'ads', gpc_sensitive: true }]);

            expect(events('gpcDetected')).toHaveLength(0);
            expect(events('klaroConfigLoaded')[0].klaroGeoGPCDetected).toBe(false);
        });
    });

    describe('window.klaroGeo.gpc', function() {
        test('exposes detected, enabled and affectedServices', function() {
            setGpcSignal(true);
            apply([{ name: 'ads', gpc_sensitive: true }]);

            expect(window.klaroGeo.gpc).toEqual({ detected: true, enabled: true, affectedServices: ['ads'] });
        });

        test('keeps other klaroGeo properties', function() {
            window.klaroGeo = { queue: [{ event: 'x' }], useGTM: true };
            apply([]);

            expect(window.klaroGeo.queue).toEqual([{ event: 'x' }]);
            expect(window.klaroGeo.useGTM).toBe(true);
        });
    });
});
