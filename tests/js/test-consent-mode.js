/**
 * Consent mode: how service consents become Google Consent Mode keys
 * (js/klaro-geo-events.js), and how js/klaro-geo.js sends them.
 *
 * The contract for the keys is in specs/frontend-data.spec.yaml.
 */

const { setUpPage, loadConsentScript } = require('./helpers/page.js');

// The consent mode services as the default install has them: ad-user-data and
// ad-personalization are children of ad-storage.
const SERVICES = [
    { name: 'google-tag-manager', purposes: ['infrastructure'] },
    { name: 'google-analytics', purposes: ['analytics'] },
    { name: 'analytics-storage', is_consent_mode_service: true, consent_mode_key: 'analytics_storage' },
    { name: 'ad-storage', is_consent_mode_service: true, consent_mode_key: 'ad_storage' },
    { name: 'ad-user-data', is_consent_mode_service: true, consent_mode_key: 'ad_user_data', parent_service: 'ad-storage' },
    { name: 'ad-personalization', is_consent_mode_service: true, consent_mode_key: 'ad_personalization', parent_service: 'ad-storage' }
];

// What PHP puts in klaroConsentData for those services.
const MAPS = {
    consentModeServices: {
        analytics_storage: 'analytics-storage',
        ad_storage: 'ad-storage',
        ad_user_data: 'ad-user-data',
        ad_personalization: 'ad-personalization'
    },
    parentChildMap: { 'ad-storage': ['ad-user-data', 'ad-personalization'] }
};

function consents(overrides) {
    return Object.assign({
        'google-tag-manager': false,
        'google-analytics': false,
        'analytics-storage': false,
        'ad-storage': false,
        'ad-user-data': false,
        'ad-personalization': false
    }, overrides);
}

describe('KlaroGeoEvents.buildConsentMode', function() {
    beforeEach(function() {
        setUpPage(MAPS);
        window.klaroConfig = { services: SERVICES };
    });

    afterEach(function() {
        delete window.klaroConfig;
        delete window.klaroConsentData;
        delete window.klaroGeo;
    });

    test('every service gets a key, granted or denied, and none is omitted', function() {
        const mode = window.KlaroGeoEvents.buildConsentMode(consents({ 'google-analytics': true }));

        expect(mode).toEqual({
            analytics_storage: 'denied',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            google_tag_manager_consent: 'denied',
            google_analytics_consent: 'granted'
        });
    });

    test('a consent mode service is reported under its consent mode key only', function() {
        const mode = window.KlaroGeoEvents.buildConsentMode(consents({ 'analytics-storage': true }));

        expect(mode.analytics_storage).toBe('granted');
        expect(mode).not.toHaveProperty('analytics_storage_consent');
        expect(mode).not.toHaveProperty('ad_storage_consent');
    });

    test('children follow their own consent while the parent is granted', function() {
        const mode = window.KlaroGeoEvents.buildConsentMode(consents({
            'ad-storage': true,
            'ad-user-data': true,
            'ad-personalization': false
        }));

        expect(mode.ad_storage).toBe('granted');
        expect(mode.ad_user_data).toBe('granted');
        expect(mode.ad_personalization).toBe('denied');
    });

    test('children are denied when the parent is not granted, whatever their own consent', function() {
        const mode = window.KlaroGeoEvents.buildConsentMode(consents({
            'ad-storage': false,
            'ad-user-data': true,
            'ad-personalization': true
        }));

        expect(mode.ad_storage).toBe('denied');
        expect(mode.ad_user_data).toBe('denied');
        expect(mode.ad_personalization).toBe('denied');
    });

    test('a parent that has no consent entry at all counts as not granted', function() {
        const mode = window.KlaroGeoEvents.buildConsentMode({ 'ad-user-data': true });

        expect(mode.ad_user_data).toBe('denied');
    });

    test('the four Google keys are present even when Klaro has no consents yet', function() {
        expect(window.KlaroGeoEvents.buildConsentMode({})).toEqual({
            analytics_storage: 'denied',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied'
        });
    });
});

describe('KlaroGeoEvents.getServiceConsentKey', function() {
    beforeEach(function() {
        window.klaroConfig = { services: SERVICES };
    });

    afterEach(function() {
        delete window.klaroConfig;
    });

    test.each([
        ['google-analytics', 'google_analytics_consent'],
        ['google-tag-manager', 'google_tag_manager_consent'],
        ['ad-storage', 'ad_storage'],
        ['ad-user-data', 'ad_user_data'],
        ['not-a-configured-service', 'not_a_configured_service_consent']
    ])('%s => %s', function(serviceName, key) {
        expect(window.KlaroGeoEvents.getServiceConsentKey(serviceName)).toBe(key);
    });
});

describe('KlaroGeoEvents events', function() {
    beforeEach(function() {
        setUpPage(MAPS);
        window.klaroConfig = { services: SERVICES };
    });

    afterEach(function() {
        delete window.klaro;
        delete window.klaroConfig;
        delete window.klaroConsentData;
        delete window.klaroGeo;
    });

    test('an event forwarded from Klaro carries the services accepted at that moment', function() {
        const manager = { consents: consents({ 'google-analytics': true, 'ad-storage': true }) };
        window.klaro = { getManager: function() { return manager; } };

        const event = window.KlaroGeoEvents.createKlaroForwardedEvent('saveConsents', { changes: 2 });

        expect(event).toEqual({
            event: 'Klaro Event',
            eventSource: 'klaro',
            klaroEventName: 'saveConsents',
            klaroEventData: { changes: 2 },
            acceptedServices: ['google-analytics', 'ad-storage']
        });
    });

    test('the consent state is a copy: a later change in Klaro does not alter it', function() {
        const manager = { consents: consents({ 'google-analytics': true }) };
        window.klaro = { getManager: function() { return manager; } };

        const state = window.KlaroGeoEvents.getConsentState();
        manager.consents['google-analytics'] = false;

        expect(state.consents['google-analytics']).toBe(true);
        expect(state.acceptedServices).toEqual(['google-analytics']);
        expect(state.consentMode.google_analytics_consent).toBe('granted');
    });

    test.each([
        ['Klaro has not loaded', undefined],
        ['Klaro has no config yet, so getManager() throws', { getManager: function() { throw new Error('no config'); } }]
    ])('the consent state is empty when %s', function(name, klaro) {
        window.klaro = klaro;

        const state = window.KlaroGeoEvents.getConsentState();

        expect(state.manager).toBeNull();
        expect(state.consents).toEqual({});
        expect(state.acceptedServices).toEqual([]);
    });

    test('a plugin event is a Klaro Event from klaro-geo with the data merged in', function() {
        expect(window.KlaroGeoEvents.createKlaroGeoEvent('queueFlushed', { queueSize: 3 })).toEqual({
            event: 'Klaro Event',
            eventSource: 'klaro-geo',
            klaroEventName: 'queueFlushed',
            queueSize: 3
        });
    });
});

describe('Consent updates sent by klaro-geo.js', function() {
    function consentUpdates() {
        return window.gtag.mock.calls.filter(function(call) {
            return call[0] === 'consent' && call[1] === 'update';
        });
    }

    function updateEvents() {
        return window.dataLayer.filter(function(e) { return e && e.event === 'Klaro Consent Update'; });
    }

    function forwarded(name) {
        return window.dataLayer.filter(function(e) { return e && e.klaroEventName === name; });
    }

    // Load the script and let the initial consent update (a zero timer, then
    // the debounce) finish, so each test starts from a settled page.
    function settledPage(options) {
        const loaded = loadConsentScript(Object.assign({
            services: SERVICES,
            consents: consents(),
            consentData: Object.assign({ gtmId: 'GTM-TEST', consentModeType: 'advanced' }, MAPS)
        }, options));
        jest.advanceTimersByTime(100);
        window.gtag.mockClear();
        window.dataLayer.length = 0;
        return loaded;
    }

    function save(loaded, newConsents) {
        Object.assign(loaded.manager.consents, newConsents);
        loaded.watch.update(loaded.manager, 'saveConsents', loaded.manager.consents);
        jest.advanceTimersByTime(100);
    }

    beforeEach(function() {
        jest.useFakeTimers();
    });

    afterEach(function() {
        jest.useRealTimers();
        delete window.klaro;
        delete window.klaroConfig;
        delete window.klaroConsentData;
        delete window.klaroGeo;
    });

    test('saving consent sends one gtag update with every key, then Klaro Consent Update', function() {
        const loaded = settledPage();

        save(loaded, { 'google-analytics': true, 'analytics-storage': true });

        const expected = {
            analytics_storage: 'granted',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            google_tag_manager_consent: 'denied',
            google_analytics_consent: 'granted'
        };
        expect(consentUpdates()).toHaveLength(1);
        expect(consentUpdates()[0][2]).toEqual(expected);
        expect(updateEvents()).toEqual([{
            event: 'Klaro Consent Update',
            consent_trigger: 'saveConsents',
            consentMode: expected,
            acceptedServices: ['google-analytics', 'analytics-storage'],
            gpcActive: false
        }]);
    });

    test('the update reflects the parent/child rule', function() {
        const loaded = settledPage();

        save(loaded, { 'ad-user-data': true, 'ad-personalization': true });

        expect(consentUpdates()[0][2].ad_user_data).toBe('denied');
        expect(consentUpdates()[0][2].ad_personalization).toBe('denied');
    });

    test('gpcActive is true when GPC was applied to this page', function() {
        const loaded = settledPage({
            klaroGeo: { gpc: { detected: true, enabled: true, affectedServices: ['ad-storage'] } }
        });

        save(loaded, { 'google-analytics': true });

        expect(updateEvents()[0].gpcActive).toBe(true);
    });

    test('several changes in quick succession are sent as one update with the final state', function() {
        const loaded = settledPage();

        Object.assign(loaded.manager.consents, { 'google-analytics': true });
        loaded.watch.update(loaded.manager, 'saveConsents', { step: 1 });
        Object.assign(loaded.manager.consents, { 'ad-storage': true });
        loaded.watch.update(loaded.manager, 'saveConsents', { step: 2 });
        jest.advanceTimersByTime(100);

        expect(consentUpdates()).toHaveLength(1);
        expect(consentUpdates()[0][2].google_analytics_consent).toBe('granted');
        expect(consentUpdates()[0][2].ad_storage).toBe('granted');
        expect(updateEvents()).toHaveLength(1);
    });

    test('saveConsents is forwarded to the dataLayer as a Klaro event', function() {
        const loaded = settledPage();

        save(loaded, { 'google-analytics': true });

        expect(forwarded('saveConsents')).toHaveLength(1);
        expect(forwarded('saveConsents')[0].eventSource).toBe('klaro');
        expect(forwarded('saveConsents')[0].acceptedServices).toEqual(['google-analytics']);
    });

    test('the per-service "consents" events are not forwarded by default', function() {
        const loaded = settledPage();

        loaded.watch.update(loaded.manager, 'consents', {});

        expect(forwarded('consents')).toHaveLength(0);
    });

    test('they are forwarded when suppressConsentsEvents is off', function() {
        const loaded = settledPage({
            consentData: Object.assign({ suppressConsentsEvents: false }, MAPS)
        });

        loaded.watch.update(loaded.manager, 'consents', {});

        expect(forwarded('consents')).toHaveLength(1);
    });
});
