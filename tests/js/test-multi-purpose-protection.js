/**
 * Multi-purpose protection in js/klaro-geo.js.
 *
 * With groupByPurpose, switching a purpose off in Klaro's modal switches off
 * every service in it. A service that also belongs to a purpose that is still
 * on is switched back on.
 */

const { setUpPage } = require('./helpers/page.js');

describe('Multi-purpose protection', function() {
    let manager;

    // The script adds a document listener each time it is loaded; they are
    // removed after each test so one test's listener cannot act in the next.
    let listeners;
    let addEventListener;

    const config = {
        groupByPurpose: true,
        services: [
            { name: 'google-tag-manager', purposes: ['analytics', 'advertising'] },
            { name: 'google-ads', purposes: ['advertising'] }
        ]
    };

    function purposeToggles() {
        document.body.innerHTML =
            '<input type="checkbox" id="purpose-item-analytics" checked>' +
            '<input type="checkbox" id="purpose-item-advertising" checked>';
    }

    // The visitor switches a purpose off; Klaro has already switched off its services.
    function switchOff(purpose) {
        const input = document.getElementById('purpose-item-' + purpose);
        input.checked = false;
        manager.consents['google-tag-manager'] = false;
        manager.consents['google-ads'] = false;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        jest.advanceTimersByTime(1);
    }

    beforeEach(function() {
        jest.useFakeTimers();
        jest.resetModules();
        setUpPage();
        purposeToggles();
        listeners = [];
        addEventListener = document.addEventListener;
        document.addEventListener = function(type, handler) {
            listeners.push([type, handler]);
            return addEventListener.apply(document, arguments);
        };
        manager = {
            consents: { 'google-tag-manager': true, 'google-ads': true },
            watch: jest.fn(),
            updateConsent: jest.fn(function(name, value) { manager.consents[name] = value; })
        };
    });

    afterEach(function() {
        jest.useRealTimers();
        document.addEventListener = addEventListener;
        listeners.forEach(function(l) { document.removeEventListener(l[0], l[1]); });
        delete window.klaro;
        delete window.klaroConfig;
        delete window.klaroGeo;
        delete window.klaroConsentData;
    });

    test('a service with another purpose still on is switched back on; a single-purpose service is not', function() {
        window.klaroConfig = config;
        window.klaro = { getManager: function() { return manager; } };
        require('../../js/klaro-geo.js');

        switchOff('advertising');

        expect(manager.updateConsent).toHaveBeenCalledTimes(1);
        expect(manager.updateConsent).toHaveBeenCalledWith('google-tag-manager', true);
    });

    // Client geo mode: the script runs while the geo lookup is still out, so
    // there is no klaroConfig yet. The protection must still be set up.
    test('is set up when klaroConfig only exists after the script ran', function() {
        window.klaro = {
            getManager: function() {
                if (!window.klaroConfig) {
                    throw new Error('no config');
                }
                return manager;
            }
        };
        require('../../js/klaro-geo.js');

        window.klaroConfig = config;
        jest.advanceTimersByTime(60);

        switchOff('advertising');

        expect(manager.updateConsent).toHaveBeenCalledWith('google-tag-manager', true);
    });

    test('nothing is switched back on without groupByPurpose', function() {
        window.klaroConfig = { groupByPurpose: false, services: config.services };
        window.klaro = { getManager: function() { return manager; } };
        require('../../js/klaro-geo.js');

        switchOff('advertising');

        expect(manager.updateConsent).not.toHaveBeenCalled();
    });
});
