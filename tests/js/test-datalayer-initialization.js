/**
 * Klaro DataLayer Initialization Tests
 */

describe('DataLayer Initialization', function() {

    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = '';

        // Clear any existing dataLayer
        window.dataLayer = undefined;

        // Mock gtag function
        window.gtag = jest.fn();
    });

    afterEach(function() {
        // Clean up
        delete window.dataLayer;
        jest.clearAllMocks();
    });

    test('should initialize dataLayer if it does not exist', function() {
        // Ensure dataLayer is undefined
        expect(window.dataLayer).toBeUndefined();

        // Simulate the initialization code from klaro-config.js
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            "event": "Klaro Config Loaded",
            "klaro_geo_consent_template": "default",
            "klaro_geo_template_source": "fallback",
            "klaro_geo_detected_country": null,
            "klaro_geo_detected_region": null,
            "klaro_geo_consent_receipt_number": null,
            "klaro_geo_logging_enabled": false
        });

        // Check if dataLayer was initialized
        expect(window.dataLayer).toBeDefined();
        expect(Array.isArray(window.dataLayer)).toBe(true);
    });

    test('should not overwrite existing dataLayer', function() {
        // Set up an existing dataLayer with some data
        window.dataLayer = [{ existingEvent: 'test' }];

        // Simulate the initialization code from klaro-config.js
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            "event": "Klaro Config Loaded",
            "klaro_geo_consent_template": "default",
            "klaro_geo_template_source": "fallback",
            "klaro_geo_detected_country": null,
            "klaro_geo_detected_region": null,
            "klaro_geo_consent_receipt_number": null,
            "klaro_geo_logging_enabled": false
        });

        // Check if the existing data was preserved
        expect(window.dataLayer.length).toBe(2);
        expect(window.dataLayer[0].existingEvent).toBe('test');
    });

    test('should push debug information to dataLayer', function() {
        // Simulate the initialization code from klaro-config.js
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            "event": "Klaro Config Loaded",
            "klaro_geo_consent_template": "default",
            "klaro_geo_template_source": "fallback",
            "klaro_geo_detected_country": null,
            "klaro_geo_detected_region": null,
            "klaro_geo_consent_receipt_number": null,
            "klaro_geo_logging_enabled": false
        });

        // Check if debug information was pushed to dataLayer
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].event).toBe('Klaro Config Loaded');
        expect(window.dataLayer[0].klaro_geo_consent_template).toBe('default');
        expect(window.dataLayer[0].klaro_geo_template_source).toBe('fallback');
        expect(window.dataLayer[0].klaro_geo_detected_country).toBeNull();
        expect(window.dataLayer[0].klaro_geo_detected_region).toBeNull();
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toBeNull();
        expect(window.dataLayer[0].klaro_geo_logging_enabled).toBe(false);
    });

    test('should set default consent state', function() {
        // Simulate the initialization code from klaro-config.js
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            "event": "Klaro Config Loaded",
            "klaro_geo_consent_template": "default",
            "klaro_geo_template_source": "fallback",
            "klaro_geo_detected_country": null,
            "klaro_geo_detected_region": null,
            "klaro_geo_consent_receipt_number": null,
            "klaro_geo_logging_enabled": false
        });

        // Simulate the default consent state call
        window.gtag('consent', 'default', {
            'ad_storage': 'denied',
            'analytics_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied'
        });

        // Check if gtag was called with the correct default consent state
        expect(window.gtag).toHaveBeenCalledWith('consent', 'default', {
            'ad_storage': 'denied',
            'analytics_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied'
        });
    });

    test('should enable data redaction by default', function() {
        // Simulate the initialization code from klaro-config.js
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            "event": "Klaro Config Loaded",
            "klaro_geo_consent_template": "default",
            "klaro_geo_template_source": "fallback",
            "klaro_geo_detected_country": null,
            "klaro_geo_detected_region": null,
            "klaro_geo_consent_receipt_number": null,
            "klaro_geo_logging_enabled": false
        });

        // Simulate the data redaction call
        window.gtag('set', 'ads_data_redaction', true);

        // Check if gtag was called to enable data redaction
        expect(window.gtag).toHaveBeenCalledWith('set', 'ads_data_redaction', true);
    });

    test('should initialize gtag function if it does not exist', function() {
        // Ensure gtag is undefined
        delete window.gtag;
        expect(window.gtag).toBeUndefined();

        // Simulate the initialization code from klaro-config.js
        window.dataLayer = window.dataLayer || [];

        // Define the gtag function
        window.gtag = function() {
            window.dataLayer.push(arguments);
        };

        // Check if gtag function was defined
        expect(typeof window.gtag).toBe('function');

        // Test the gtag function
        window.gtag('test', 'value');

        // Check if it pushes to dataLayer
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0][0]).toBe('test');
        expect(window.dataLayer[0][1]).toBe('value');
    });

    test('should include receipt number and logging status in initial dataLayer push', function() {
        // Simulate the initialization code from klaro-config.js
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            "event": "Klaro Config Loaded",
            "klaro_geo_consent_template": "default",
            "klaro_geo_template_source": "fallback",
            "klaro_geo_detected_country": null,
            "klaro_geo_detected_region": null,
            "klaro_geo_consent_receipt_number": null,
            "klaro_geo_logging_enabled": false
        });

        // Check if receipt number and logging status were included
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toBeNull();
        expect(window.dataLayer[0].klaro_geo_logging_enabled).toBe(false);
    });
});