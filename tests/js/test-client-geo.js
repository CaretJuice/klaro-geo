/**
 * Client-Side Geo Resolution Tests
 *
 * Tests the JS side of the PHP↔JS parity fixture, the geoip record parsing,
 * the resolution fallback chain (geoip-ajax → timezone → fallback), GPC
 * application, dataLayer events, and Klaro initialization.
 *
 * Spec: specs/client-side-geo.spec.yaml
 */

const clientGeo = require('../../js/klaro-geo-client-geo.js');
const parityFixture = require('../fixtures/geo-resolution-parity.json');

// Build a rules object + candidate templates map from the shared fixture
function fixtureRules() {
    return {
        defaultTemplate: parityFixture.defaultTemplate,
        visibleCountries: parityFixture.visibleCountries,
        countries: parityFixture.countrySettings
    };
}

function fixtureTemplates() {
    const templates = {};
    parityFixture.availableTemplates.forEach(function(key) {
        templates[key] = { config: { services: [] }, consentDefaults: {}, gtagSettings: {} };
    });
    return templates;
}

function splitLocation(location) {
    if (!location) {
        return { country: '', region: '' };
    }
    const parts = location.split('-');
    return { country: parts[0], region: parts[1] || '' };
}

// Minimal payload factory for applyResolution / resolveAndApply tests
function makePayload(overrides) {
    const payload = {
        mode: 'client',
        geoAjax: { url: 'https://example.test/wp-admin/admin-ajax.php', action: 'geoip_detect2_get_info_from_current_ip' },
        timeoutMs: 200,
        timezoneFallback: true,
        rules: {
            defaultTemplate: 'default',
            visibleCountries: ['US', 'CA'],
            countries: {
                US: { template: 'relaxed' },
                CA: { template: 'strict' }
            }
        },
        templates: {
            'default': {
                config: { services: [{ name: 'google-analytics', gpc_sensitive: true }], storageMethod: 'cookie' },
                consentDefaults: { analytics_storage: 'denied', google_analytics_consent: 'denied' },
                gtagSettings: { ads_data_redaction: 'true', url_passthrough: 'false' },
                enableConsentLogging: true,
                gpcEnabled: true,
                templateSettings: { consentModalTitle: 'Privacy', consentModalDescription: '', acceptAllText: 'Accept', declineAllText: 'Decline', defaultConsent: false, requiredConsent: false }
            },
            'relaxed': {
                config: { services: [{ name: 'google-analytics', gpc_sensitive: true, default: true }], storageMethod: 'cookie' },
                consentDefaults: { analytics_storage: 'granted', google_analytics_consent: 'granted' },
                gtagSettings: { ads_data_redaction: 'true', url_passthrough: 'true' },
                enableConsentLogging: true,
                gpcEnabled: true,
                templateSettings: { consentModalTitle: 'Privacy', consentModalDescription: '', acceptAllText: 'Accept', declineAllText: 'Decline', defaultConsent: true, requiredConsent: false }
            },
            'strict': {
                config: { services: [{ name: 'google-analytics', gpc_sensitive: true }], storageMethod: 'cookie' },
                consentDefaults: { analytics_storage: 'denied', google_analytics_consent: 'denied' },
                gtagSettings: { ads_data_redaction: 'true', url_passthrough: 'false' },
                enableConsentLogging: true,
                gpcEnabled: false,
                templateSettings: { consentModalTitle: 'Privacy', consentModalDescription: '', acceptAllText: 'Accept', declineAllText: 'Decline', defaultConsent: false, requiredConsent: false }
            }
        }
    };
    return Object.assign(payload, overrides || {});
}

describe('Parity fixture (JS side)', function() {
    parityFixture.cases.forEach(function(testCase) {
        test(testCase.name + ' (location "' + testCase.location + '")', function() {
            const loc = splitLocation(testCase.location);
            const result = clientGeo.resolveEffectiveTemplate(
                fixtureRules(),
                fixtureTemplates(),
                loc.country,
                loc.region
            );
            expect(result.template).toBe(testCase.expected.template);
            expect(result.source).toBe(testCase.expected.source);
        });
    });
});

describe('parseGeoRecord', function() {
    test('parses a GeoLite2-City record (country + most specific subdivision)', function() {
        const record = {
            is_empty: false,
            country: { iso_code: 'CA' },
            subdivisions: [{ iso_code: 'BC' }]
        };
        expect(clientGeo.parseGeoRecord(record)).toEqual({ country: 'CA', region: 'BC' });
    });

    test('uses the LAST subdivision (most specific)', function() {
        const record = {
            country: { iso_code: 'GB' },
            subdivisions: [{ iso_code: 'ENG' }, { iso_code: 'WSM' }]
        };
        expect(clientGeo.parseGeoRecord(record)).toEqual({ country: 'GB', region: 'WSM' });
    });

    test('country without subdivisions (country-level source)', function() {
        const record = { country: { iso_code: 'DE' } };
        expect(clientGeo.parseGeoRecord(record)).toEqual({ country: 'DE', region: '' });
    });

    test('is_empty record is a lookup failure', function() {
        expect(clientGeo.parseGeoRecord({ is_empty: true, country: { iso_code: '' } })).toBeNull();
    });

    test('missing country is a lookup failure', function() {
        expect(clientGeo.parseGeoRecord({ country: {} })).toBeNull();
        expect(clientGeo.parseGeoRecord({})).toBeNull();
        expect(clientGeo.parseGeoRecord(null)).toBeNull();
    });
});

describe('Timezone fallback map', function() {
    let dtfSpy;

    afterEach(function() {
        if (dtfSpy) {
            dtfSpy.mockRestore();
            dtfSpy = null;
        }
    });

    function mockTimezone(tz) {
        dtfSpy = jest.spyOn(Intl, 'DateTimeFormat').mockImplementation(function() {
            return { resolvedOptions: function() { return { timeZone: tz }; } };
        });
    }

    test('maps common zones to countries', function() {
        expect(clientGeo.TZ_COUNTRY['America/Vancouver']).toBe('CA');
        expect(clientGeo.TZ_COUNTRY['Europe/London']).toBe('GB');
        expect(clientGeo.TZ_COUNTRY['Asia/Tokyo']).toBe('JP');
        expect(clientGeo.TZ_COUNTRY['America/New_York']).toBe('US');
        expect(clientGeo.TZ_COUNTRY['Europe/Berlin']).toBe('DE');
    });

    test('timezoneToCountry resolves via Intl', function() {
        mockTimezone('America/Vancouver');
        expect(clientGeo.timezoneToCountry()).toBe('CA');
    });

    test('unknown timezone yields empty string', function() {
        mockTimezone('Klaro/Nowhere');
        expect(clientGeo.timezoneToCountry()).toBe('');
    });
});

describe('Resolution fallback chain', function() {
    let dtfSpy;

    beforeEach(function() {
        window.dataLayer = [];
        window.gtag = jest.fn();
        window.klaro = { setup: jest.fn() };
        window.klaroGeo = {};
        window.klaroConsentData = { detectedCountry: '', detectedRegion: '', templateName: '' };
        delete window.klaroConfig;
        delete window.klaroGeoGPC;
        Object.defineProperty(navigator, 'globalPrivacyControl', {
            value: undefined,
            writable: true,
            configurable: true
        });
        window.localStorage.clear();
    });

    afterEach(function() {
        delete global.fetch;
        delete window.fetch;
        delete window.klaro;
        delete window.klaroConfig;
        delete window.klaroConsentData;
        delete window.klaroGeoGPC;
        if (dtfSpy) {
            dtfSpy.mockRestore();
            dtfSpy = null;
        }
        jest.clearAllMocks();
    });

    function mockFetchSuccess(record) {
        window.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: function() { return Promise.resolve(record); }
        });
    }

    function mockFetchFailure() {
        window.fetch = jest.fn().mockRejectedValue(new Error('network down'));
    }

    function mockTimezone(tz) {
        dtfSpy = jest.spyOn(Intl, 'DateTimeFormat').mockImplementation(function() {
            return { resolvedOptions: function() { return { timeZone: tz }; } };
        });
    }

    function lastConfigLoadedEvent() {
        const events = window.dataLayer.filter(function(e) {
            return e && e.klaroEventName === 'klaroConfigLoaded';
        });
        return events[events.length - 1];
    }

    test('AJAX success → geoip-ajax source, correct template, Klaro initialized', async function() {
        mockFetchSuccess({ country: { iso_code: 'CA' }, subdivisions: [{ iso_code: 'BC' }] });

        const payload = makePayload();
        await clientGeo.resolveAndApply(payload);

        const evt = lastConfigLoadedEvent();
        expect(evt).toBeDefined();
        expect(evt.klaroGeoGeoSource).toBe('geoip-ajax');
        expect(evt.klaroGeoResolutionMode).toBe('client');
        expect(evt.klaroGeoDetectedCountry).toBe('CA');
        expect(evt.klaroGeoDetectedRegion).toBe('BC');
        expect(evt.klaroGeoConsentTemplate).toBe('strict');
        expect(evt.klaroGeoTemplateSource).toBe('country');
        expect(typeof evt.klaroGeoGeoLatencyMs).toBe('number');

        expect(window.klaroConfig).toBe(payload.templates.strict.config);
        expect(window.klaro.setup).toHaveBeenCalledWith(window.klaroConfig);

        // klaroConsentData filled before Klaro init
        expect(window.klaroConsentData.detectedCountry).toBe('CA');
        expect(window.klaroConsentData.detectedRegion).toBe('BC');
        expect(window.klaroConsentData.templateName).toBe('strict');
        expect(window.klaroConsentData.templateSource).toBe('country');
    });

    test('AJAX failure + timezone fallback on → timezone source (region empty)', async function() {
        mockFetchFailure();
        mockTimezone('America/Vancouver');

        await clientGeo.resolveAndApply(makePayload());

        const evt = lastConfigLoadedEvent();
        expect(evt.klaroGeoGeoSource).toBe('timezone');
        expect(evt.klaroGeoDetectedCountry).toBe('CA');
        expect(evt.klaroGeoDetectedRegion).toBeNull();
        expect(evt.klaroGeoConsentTemplate).toBe('strict');
    });

    test('AJAX failure + timezone fallback off → fallback template', async function() {
        mockFetchFailure();

        await clientGeo.resolveAndApply(makePayload({ timezoneFallback: false }));

        const evt = lastConfigLoadedEvent();
        expect(evt.klaroGeoGeoSource).toBe('fallback');
        expect(evt.klaroGeoDetectedCountry).toBeNull();
        expect(evt.klaroGeoConsentTemplate).toBe('default');
        expect(evt.klaroGeoTemplateSource).toBe('default');
        expect(window.klaro.setup).toHaveBeenCalled();
    });

    test('empty record → falls through, not treated as success', async function() {
        mockFetchSuccess({ is_empty: true });
        mockTimezone('Europe/Berlin');

        await clientGeo.resolveAndApply(makePayload());

        const evt = lastConfigLoadedEvent();
        expect(evt.klaroGeoGeoSource).toBe('timezone');
        expect(evt.klaroGeoDetectedCountry).toBe('DE');
    });

    test('timeout → falls through to timezone', async function() {
        // A fetch that never settles within the 50ms timeout
        window.fetch = jest.fn().mockImplementation(function() {
            return new Promise(function() {});
        });
        mockTimezone('America/Vancouver');

        await clientGeo.resolveAndApply(makePayload({ timeoutMs: 50 }));

        const evt = lastConfigLoadedEvent();
        expect(evt.klaroGeoGeoSource).toBe('timezone');
    });

    test('selected template ≠ fallback → gtag consent update with its defaults', async function() {
        mockFetchSuccess({ country: { iso_code: 'US' } });

        await clientGeo.resolveAndApply(makePayload());

        expect(window.gtag).toHaveBeenCalledWith(
            'consent',
            'update',
            expect.objectContaining({ analytics_storage: 'granted', google_analytics_consent: 'granted' })
        );
    });

    test('fallback template selected without GPC → no consent update', async function() {
        mockFetchFailure();

        await clientGeo.resolveAndApply(makePayload({ timezoneFallback: false }));

        const consentUpdates = window.gtag.mock.calls.filter(function(call) {
            return call[0] === 'consent' && call[1] === 'update';
        });
        expect(consentUpdates.length).toBe(0);
    });

    test('GPC detected → service defaults overridden and consent denied', async function() {
        Object.defineProperty(navigator, 'globalPrivacyControl', {
            value: true,
            writable: true,
            configurable: true
        });
        mockFetchSuccess({ country: { iso_code: 'US' } });

        const payload = makePayload();
        await clientGeo.resolveAndApply(payload);

        expect(window.klaroGeoGPC.detected).toBe(true);
        expect(window.klaroGeoGPC.affectedServices).toContain('google-analytics');
        expect(window.klaroConfig.services[0].default).toBe(false);

        // GPC forces denied even though the relaxed template granted by default
        expect(window.gtag).toHaveBeenCalledWith(
            'consent',
            'update',
            expect.objectContaining({ google_analytics_consent: 'denied' })
        );

        const gpcEvents = window.dataLayer.filter(function(e) {
            return e && e.klaroEventName === 'gpcDetected';
        });
        expect(gpcEvents.length).toBe(1);
    });

    test('GPC disabled on template → no GPC application', async function() {
        Object.defineProperty(navigator, 'globalPrivacyControl', {
            value: true,
            writable: true,
            configurable: true
        });
        mockFetchSuccess({ country: { iso_code: 'CA' } }); // strict template: gpcEnabled false

        await clientGeo.resolveAndApply(makePayload());

        expect(window.klaroGeoGPC.detected).toBe(false);
        expect(window.klaroGeoGPC.enabled).toBe(false);
    });

    test('Klaro not loaded yet → config set, setup deferred to auto-init', async function() {
        delete window.klaro;
        mockFetchSuccess({ country: { iso_code: 'CA' } });

        await clientGeo.resolveAndApply(makePayload());

        expect(window.klaroConfig).toBeDefined();
        expect(window.klaroConfig.services).toBeDefined();
    });

    test('unknown geo template reference → terminal fallback keeps Klaro alive', async function() {
        mockFetchSuccess({ country: { iso_code: 'US' } });
        const payload = makePayload();
        // Corrupt the payload: US points at a template that did not ship
        payload.rules.countries.US.template = 'ghost';

        await clientGeo.resolveAndApply(payload);

        const evt = lastConfigLoadedEvent();
        expect(evt.klaroGeoConsentTemplate).toBe('default');
        expect(evt.klaroGeoTemplateSource).toBe('fallback');
        expect(window.klaro.setup).toHaveBeenCalled();
    });
});
