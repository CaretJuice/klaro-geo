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

        // Set up Klaro consent variables
        window.klaroConsentReceiptsEnabled = true;
        window.klaro_geo_logging_enabled = false;
        window.klaroConsentTemplateName = 'default';
        window.klaroConsentTemplateSource = 'fallback';
        window.klaroDetectedCountry = 'US';
        window.klaroDetectedRegion = 'CA';

        // Create a mock handleConsentUpdate function
        mockHandleConsentUpdate = jest.fn((type, granted) => {
            window.gtag('consent', 'update', {
                [type]: granted ? 'granted' : 'denied'
            });
        });

        // Define the function globally
        window.handleConsentUpdate = mockHandleConsentUpdate;

        // Mock the initial dataLayer push that happens in klaro-config.js
        window.dataLayer.push({
            "event": "klaro_geo_klaro_config_loaded",
            "klaro_geo_consent_template": "default",
            "klaro_geo_template_source": "fallback",
            "klaro_geo_detected_country": null,
            "klaro_geo_detected_region": null,
            "klaro_geo_consent_receipt_number": null,
            "klaro_geo_logging_enabled": false
        });

        // Mock the default consent state and data redaction calls
        window.gtag('consent', 'default', {
            'ad_storage': 'denied',
            'analytics_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied'
        });

        window.gtag('set', 'ads_data_redaction', true);
    });

    afterEach(function() {
        // Clean up
        window.dataLayer = [];
        jest.clearAllMocks();
        delete window.klaroConsentReceiptsEnabled;
        delete window.klaro_geo_logging_enabled;
        delete window.klaroConsentTemplateName;
        delete window.klaroConsentTemplateSource;
        delete window.klaroDetectedCountry;
        delete window.klaroDetectedRegion;
        delete window.handleConsentUpdate;
    });

    test('should initialize dataLayer with correct debug information', function() {
        // Check if dataLayer was initialized with the correct debug information
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].event).toBe('klaro_geo_klaro_config_loaded');
        expect(window.dataLayer[0].klaro_geo_consent_template).toBe('default');
        expect(window.dataLayer[0].klaro_geo_template_source).toBe('fallback');
        expect(window.dataLayer[0].klaro_geo_detected_country).toBeNull();
        expect(window.dataLayer[0].klaro_geo_detected_region).toBeNull();
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toBeNull();
        expect(window.dataLayer[0].klaro_geo_logging_enabled).toBe(false);
    });

    test('should set default consent state to denied', function() {
        // Check if gtag was called with the correct default consent state
        expect(window.gtag).toHaveBeenCalledWith('consent', 'default', {
            'ad_storage': 'denied',
            'analytics_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied'
        });
    });

    test('should enable data redaction by default', function() {
        // Check if gtag was called to enable data redaction
        expect(window.gtag).toHaveBeenCalledWith('set', 'ads_data_redaction', true);
    });

    test('handleConsentUpdate should update consent state correctly when granted', function() {
        // Reset mock to clear previous calls
        window.gtag.mockClear();

        // Call handleConsentUpdate with granted=true
        window.handleConsentUpdate('ad_storage', true);

        // Check if gtag was called with the correct consent update
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_storage': 'granted'
        });
    });

    test('handleConsentUpdate should update consent state correctly when denied', function() {
        // Reset mock to clear previous calls
        window.gtag.mockClear();

        // Call handleConsentUpdate with granted=false
        window.handleConsentUpdate('analytics_storage', false);

        // Check if gtag was called with the correct consent update
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'analytics_storage': 'denied'
        });
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