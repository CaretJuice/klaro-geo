/**
 * js/klaro-geo.js start-up: waiting for Klaro's consent manager, and the
 * initialConsents event it pushes once it has one.
 */

const { setUpPage, loadConsentScript } = require('./helpers/page.js');

describe('klaro-geo.js start-up', function() {
    function forwarded(name) {
        return window.dataLayer.filter(function(e) { return e && e.klaroEventName === name; });
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

    test('watches Klaro\'s manager as soon as it loads when the manager exists', function() {
        const loaded = loadConsentScript({ services: [{ name: 'google-analytics' }] });

        expect(loaded.manager.watch).toHaveBeenCalledTimes(1);
        expect(typeof loaded.watch.update).toBe('function');
    });

    test.each([
        ['Klaro has not loaded yet', function() { delete window.klaro; }],
        ['Klaro has no config yet, so getManager() throws', function() {
            window.klaro = { getManager: function() { throw new Error('no config'); } };
        }]
    ])('keeps trying while %s, then watches the manager once', function(name, notReady) {
        setUpPage();
        notReady();
        jest.resetModules();
        require('../../js/klaro-geo.js');
        jest.advanceTimersByTime(500);

        const manager = { consents: {}, watch: jest.fn() };
        window.klaroConfig = { services: [] };
        window.klaro = { getManager: function() { return manager; } };
        jest.advanceTimersByTime(500);

        expect(manager.watch).toHaveBeenCalledTimes(1);
    });

    test('pushes initialConsents with the consents Klaro holds, as a copy', function() {
        const loaded = loadConsentScript({
            services: [{ name: 'google-analytics' }, { name: 'google-ads' }],
            consents: { 'google-analytics': true, 'google-ads': false }
        });
        jest.advanceTimersByTime(1);

        expect(forwarded('initialConsents')).toEqual([{
            event: 'Klaro Event',
            eventSource: 'klaro',
            klaroEventName: 'initialConsents',
            klaroEventData: { 'google-analytics': true, 'google-ads': false },
            acceptedServices: ['google-analytics']
        }]);

        loaded.manager.consents['google-ads'] = true;
        expect(forwarded('initialConsents')[0].klaroEventData['google-ads']).toBe(false);
    });

    test('Klaro\'s own initialConsents notification does not push the event a second time', function() {
        const loaded = loadConsentScript({ services: [{ name: 'google-analytics' }], consents: { 'google-analytics': true } });
        jest.advanceTimersByTime(1);

        loaded.watch.update(loaded.manager, 'initialConsents', loaded.manager.consents);

        expect(forwarded('initialConsents')).toHaveLength(1);
    });

    test('what other code already pushed to the dataLayer is left in place', function() {
        setUpPage();
        window.dataLayer.push({ event: 'gtm.js' });
        const manager = { consents: {}, watch: jest.fn() };
        window.klaroConfig = { services: [] };
        window.klaro = { getManager: function() { return manager; } };
        jest.resetModules();
        require('../../js/klaro-geo.js');
        jest.advanceTimersByTime(100);

        expect(window.dataLayer[0]).toEqual({ event: 'gtm.js' });
    });
});
