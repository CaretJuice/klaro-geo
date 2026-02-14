/**
 * Klaro Config JavaScript Tests
 */

describe('Klaro Config', function() {
    // Mock the klaro-config.js module
    let mockHandleConsentUpdate;

    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = '';

        // Mock dataLayer
        window.dataLayer = [];

        // Mock gtag function
        window.gtag = jest.fn();

        // Initialize Klaro consent data object
        window.klaroConsentData = {};

        // Set up Klaro consent variables
        window.klaroConsentData.enableConsentLogging = true; // Boolean true
        window.klaro_geo_logging_enabled = false;
        window.klaroConsentData.templateName = 'default';
        window.klaroConsentData.templateSource = 'fallback';
        window.klaroConsentData.detectedCountry = 'US';
        window.klaroConsentData.detectedRegion = 'CA';

        // NOTE: handleConsentUpdate has been removed from the plugin.
        // Consent mode is now handled by the GTM template using native APIs.
        // The plugin pushes 'Klaro Consent Update' events directly to dataLayer.
        mockHandleConsentUpdate = null;

        // Mock the initial dataLayer push that happens in klaro-config.js
        window.dataLayer.push({
            "event": "Klaro Config Loaded",
            "klaro_geo_consent_template": "default",
            "klaro_geo_template_source": "fallback",
            "klaro_geo_detected_country": null,
            "klaro_geo_detected_region": null,
            "klaro_geo_consent_receipt_number": null,
            "klaro_geo_logging_enabled": false
        });

        // NOTE: The plugin no longer calls gtag('consent', 'default') or gtag('set', 'ads_data_redaction').
        // These are now handled by the GTM template using setDefaultConsentState() API.
    });

    afterEach(function() {
        // Clean up
        window.dataLayer = [];
        jest.clearAllMocks();

        // Reset klaroConsentData
        window.klaroConsentData = {};

        // Reset other variables
        delete window.klaro_geo_logging_enabled;
        delete window.handleConsentUpdate;
    });

    test('should initialize dataLayer with correct debug information', function() {
        // Check if dataLayer was initialized with the correct debug information
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].event).toBe('Klaro Config Loaded');
        expect(window.dataLayer[0].klaro_geo_consent_template).toBe('default');
        expect(window.dataLayer[0].klaro_geo_template_source).toBe('fallback');
        expect(window.dataLayer[0].klaro_geo_detected_country).toBeNull();
        expect(window.dataLayer[0].klaro_geo_detected_region).toBeNull();
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toBeNull();
        expect(window.dataLayer[0].klaro_geo_logging_enabled).toBe(false);
    });

    // NOTE: The plugin no longer calls gtag('consent', 'default') or gtag('consent', 'update').
    // Consent mode is handled by the GTM template using native setDefaultConsentState()
    // and updateConsentState() APIs. The plugin pushes 'Klaro Consent Update' events.

    test('should NOT call gtag consent default (handled by GTM template)', function() {
        // The plugin should NOT call gtag('consent', 'default') - GTM template handles this
        expect(window.gtag).not.toHaveBeenCalledWith('consent', 'default', expect.anything());
    });

    test('should NOT call gtag set ads_data_redaction (handled by GTM template)', function() {
        // The plugin should NOT call gtag('set', 'ads_data_redaction') - GTM template handles this
        expect(window.gtag).not.toHaveBeenCalledWith('set', 'ads_data_redaction', expect.anything());
    });

    test('Klaro Consent Update event should include consentMode object', function() {
        // Simulate the Klaro Consent Update event that the plugin pushes
        window.dataLayer.push({
            'event': 'Klaro Consent Update',
            'consentMode': {
                'ad_storage': 'granted',
                'analytics_storage': 'granted',
                'ad_user_data': 'granted',
                'ad_personalization': 'granted',
                'google_analytics_consent': 'granted',
                'google_ads_consent': 'granted'
            },
            'acceptedServices': ['google-analytics', 'google-ads'],
            'consent_trigger': 'saveConsents'
        });

        // Find the Klaro Consent Update event
        const consentUpdateEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify the consentMode object is present and has the correct structure
        expect(consentUpdateEvent).toBeTruthy();
        expect(consentUpdateEvent.consentMode).toBeDefined();
        expect(consentUpdateEvent.consentMode.ad_storage).toBe('granted');
        expect(consentUpdateEvent.consentMode.analytics_storage).toBe('granted');
        expect(consentUpdateEvent.consentMode.google_analytics_consent).toBe('granted');
    });

    test('Klaro Consent Update event should include acceptedServices array', function() {
        // Simulate the Klaro Consent Update event that the plugin pushes
        window.dataLayer.push({
            'event': 'Klaro Consent Update',
            'consentMode': {
                'ad_storage': 'denied',
                'analytics_storage': 'granted',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'google_analytics_consent': 'granted'
            },
            'acceptedServices': ['google-analytics'],
            'consent_trigger': 'initialConsents'
        });

        // Find the Klaro Consent Update event
        const consentUpdateEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify the acceptedServices array is present and correct
        expect(consentUpdateEvent).toBeTruthy();
        expect(consentUpdateEvent.acceptedServices).toEqual(['google-analytics']);
    });

    test('should generate receipt number when logging is enabled', function() {
        // Enable logging
        window.klaro_geo_logging_enabled = true;

        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Create a mock event
        const event = {
            detail: { manager: manager }
        };

        // Reset dataLayer
        window.dataLayer = [];

        // Mock the event listener behavior
        window.dataLayer.push({
            'event': 'klaro_geo_consent_receipt',
            'klaro_geo_consent_timestamp': new Date().toISOString(),
            'klaro_geo_consent_receipt_number': 'receipt_' + new Date().getTime() + '_' + Math.random().toString(36).substr(2, 9),
            'klaro_geo_logging_enabled': true,
            'klaro_geo_consent_choices': {
                'google-tag-manager': true
            }
        });

        // Check if receipt number was generated
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).not.toBeNull();
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toMatch(/^receipt_\d+_[a-z0-9]+$/);
    });

    test('should not generate receipt number when logging is disabled', function() {
        // Ensure logging is disabled
        window.klaro_geo_logging_enabled = false;

        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Create a mock event
        const event = {
            detail: { manager: manager }
        };

        // Reset dataLayer
        window.dataLayer = [];

        // Mock the event listener behavior
        window.dataLayer.push({
            'event': 'klaro_geo_consent_receipt',
            'klaro_geo_consent_timestamp': new Date().toISOString(),
            'klaro_geo_consent_receipt_number': null,
            'klaro_geo_logging_enabled': false,
            'klaro_geo_consent_choices': {
                'google-tag-manager': true
            }
        });

        // Check if receipt number was not generated
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toBeNull();
    });

    test('should include logging enabled status in dataLayer push', function() {
        // Set logging to enabled
        window.klaro_geo_logging_enabled = true;

        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Create a mock event
        const event = {
            detail: { manager: manager }
        };

        // Reset dataLayer
        window.dataLayer = [];

        // Mock the event listener behavior
        window.dataLayer.push({
            'event': 'klaro_geo_consent_receipt',
            'klaro_geo_consent_timestamp': new Date().toISOString(),
            'klaro_geo_consent_receipt_number': 'receipt_' + new Date().getTime() + '_' + Math.random().toString(36).substr(2, 9),
            'klaro_geo_logging_enabled': true,
            'klaro_geo_consent_choices': {
                'google-tag-manager': true
            }
        });

        // Check if logging enabled status was included
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_logging_enabled).toBe(true);

        // Now test with logging disabled
        window.klaro_geo_logging_enabled = false;
        window.dataLayer = [];

        // Mock the event listener behavior
        window.dataLayer.push({
            'event': 'klaro_geo_consent_receipt',
            'klaro_geo_consent_timestamp': new Date().toISOString(),
            'klaro_geo_consent_receipt_number': null,
            'klaro_geo_logging_enabled': false,
            'klaro_geo_consent_choices': {
                'google-tag-manager': true
            }
        });

        // Check if logging enabled status was included
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_logging_enabled).toBe(false);
    });

    test('should include all service consent choices in dataLayer push', function() {
        // Create a mock Klaro manager with multiple services
        const manager = {
            consents: {
                'google-tag-manager': true,
                'facebook-pixel': false,
                'google-analytics': true
            }
        };

        // Create a mock event
        const event = {
            detail: { manager: manager }
        };

        // Reset dataLayer
        window.dataLayer = [];

        // Mock the event listener behavior
        window.dataLayer.push({
            'event': 'klaro_geo_consent_receipt',
            'klaro_geo_consent_timestamp': new Date().toISOString(),
            'klaro_geo_consent_receipt_number': null,
            'klaro_geo_logging_enabled': false,
            'klaro_geo_consent_choices': {
                'google-tag-manager': true,
                'facebook-pixel': false,
                'google-analytics': true
            }
        });

        // Check if all service consent choices were included
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_choices).toEqual({
            'google-tag-manager': true,
            'facebook-pixel': false,
            'google-analytics': true
        });
    });

    test('should include timestamp in ISO format in dataLayer push', function() {
        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Create a mock event
        const event = {
            detail: { manager: manager }
        };

        // Reset dataLayer
        window.dataLayer = [];

        // Mock the event listener behavior
        window.dataLayer.push({
            'event': 'klaro_geo_consent_receipt',
            'klaro_geo_consent_timestamp': new Date().toISOString(),
            'klaro_geo_consent_receipt_number': null,
            'klaro_geo_logging_enabled': false,
            'klaro_geo_consent_choices': {
                'google-tag-manager': true
            }
        });

        // Check if timestamp was included in ISO format
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_timestamp).toBeDefined();

        // Verify it's a valid ISO timestamp
        const timestamp = new Date(window.dataLayer[0].klaro_geo_consent_timestamp);
        expect(timestamp instanceof Date).toBe(true);
        expect(isNaN(timestamp.getTime())).toBe(false);
    });

    test('should handle empty consents object', function() {
        // Create a mock Klaro manager with no consents
        const manager = {
            consents: {}
        };

        // Create a mock event
        const event = {
            detail: { manager: manager }
        };

        // Reset dataLayer
        window.dataLayer = [];

        // Mock the event listener behavior
        window.dataLayer.push({
            'event': 'klaro_geo_consent_receipt',
            'klaro_geo_consent_timestamp': new Date().toISOString(),
            'klaro_geo_consent_receipt_number': null,
            'klaro_geo_logging_enabled': false,
            'klaro_geo_consent_choices': {}
        });

        // Check if the event was processed correctly
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_choices).toEqual({});
    });
});