/**
 * Initial 'Klaro Consent Update' on page load (issue #4)
 *
 * When the visitor's consent state matches the defaults set in <head> (always
 * true for a fresh visitor), the gtag('consent', 'update') call is redundant
 * and is skipped. The 'Klaro Consent Update' event must still be pushed: it is
 * the trigger tags fire on and it flushes the consent queue.
 *
 * The Basic GTM mode rule is unchanged: when GTM is a consent-gated Klaro
 * service that the visitor has not consented to, GTM is not loaded, so the
 * initial event is still skipped.
 */

describe('Initial Klaro Consent Update', function() {
    const WAIT_MS = 150; // pushConsentData defers a tick, then a 50ms debounce

    function wait() {
        return new Promise(resolve => setTimeout(resolve, WAIT_MS));
    }

    function consentUpdates() {
        return window.dataLayer.filter(item => item && item.event === 'Klaro Consent Update');
    }

    function gtagConsentUpdateCalls() {
        return window.gtag.mock.calls.filter(args => args[0] === 'consent' && args[1] === 'update');
    }

    /**
     * Load klaro-geo.js against a mocked Klaro manager.
     *
     * @param {Object} opts
     * @param {Object} opts.consents       Manager consent state on page load.
     * @param {Object} opts.defaults       consent_defaults emitted in <head>.
     * @param {boolean} opts.gtmRequired   Whether the GTM service is required.
     * @param {string} opts.consentModeType 'basic' or 'advanced'.
     */
    function load(opts) {
        jest.resetModules();
        window.dataLayer = [];
        window.gtag = jest.fn();
        delete window.klaroGeo;

        window.klaroConfig = {
            services: [
                { name: 'google-tag-manager', purposes: ['functional'], required: !!opts.gtmRequired },
                { name: 'google-analytics', purposes: ['analytics'] }
            ]
        };

        const manager = {
            consents: opts.consents,
            watch: jest.fn()
        };
        window.klaro = { getManager: jest.fn(() => manager) };

        window.klaroConsentData = {
            gtmId: '',
            consentModeType: opts.consentModeType,
            consentModeServices: {},
            parentChildMap: {},
            templateSettings: {
                config: {
                    consent_mode_settings: { consent_defaults: opts.defaults }
                }
            }
        };

        require('../../js/klaro-geo.js');
    }

    const freshVisitorDefaults = {
        google_tag_manager_consent: 'denied',
        google_analytics_consent: 'denied'
    };

    test('fresh visitor in Advanced mode gets the initial event, without a redundant gtag update', function() {
        load({
            consents: { 'google-tag-manager': false, 'google-analytics': false },
            defaults: freshVisitorDefaults,
            gtmRequired: false,
            consentModeType: 'advanced'
        });

        return wait().then(() => {
            const updates = consentUpdates();
            expect(updates).toHaveLength(1);
            expect(updates[0].consent_trigger).toBe('initialConsents');
            expect(updates[0].consentMode.google_analytics_consent).toBe('denied');
            expect(gtagConsentUpdateCalls()).toHaveLength(0);
            expect(window.klaroGeo.consentConfirmed).toBe(true);
        });
    });

    test('fresh visitor in Basic mode with GTM required gets the initial event', function() {
        // Required services default to 'granted', so the state still matches the defaults
        load({
            consents: { 'google-tag-manager': true, 'google-analytics': false },
            defaults: { google_tag_manager_consent: 'granted', google_analytics_consent: 'denied' },
            gtmRequired: true,
            consentModeType: 'basic'
        });

        return wait().then(() => {
            const updates = consentUpdates();
            expect(updates).toHaveLength(1);
            expect(updates[0].consent_trigger).toBe('initialConsents');
            expect(updates[0].acceptedServices).toEqual(['google-tag-manager']);
            expect(gtagConsentUpdateCalls()).toHaveLength(0);
        });
    });

    test('fresh visitor in Basic mode with GTM consent-gated still skips the initial event', function() {
        load({
            consents: { 'google-tag-manager': false, 'google-analytics': false },
            defaults: freshVisitorDefaults,
            gtmRequired: false,
            consentModeType: 'basic'
        });

        return wait().then(() => {
            expect(consentUpdates()).toHaveLength(0);
            expect(window.klaroGeo.consentConfirmed).toBe(false);
        });
    });

    test('returning visitor whose consent differs from defaults gets gtag update and event', function() {
        load({
            consents: { 'google-tag-manager': true, 'google-analytics': true },
            defaults: freshVisitorDefaults,
            gtmRequired: false,
            consentModeType: 'basic'
        });

        return wait().then(() => {
            expect(consentUpdates()).toHaveLength(1);
            const calls = gtagConsentUpdateCalls();
            expect(calls).toHaveLength(1);
            expect(calls[0][2].google_analytics_consent).toBe('granted');
        });
    });
});
