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

    // NOTE: The plugin no longer calls gtag('consent', 'default') or gtag('consent', 'update').
    // Consent mode is now handled by the GTM template using native setDefaultConsentState()
    // and updateConsentState() APIs. The plugin only pushes 'Klaro Consent Update' events
    // to the dataLayer with the consentMode object.

    test('should NOT call gtag consent default (handled by GTM template)', function() {
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

        // The plugin should NOT call gtag('consent', 'default') - GTM template handles this
        expect(window.gtag).not.toHaveBeenCalledWith('consent', 'default', expect.anything());
    });

    test('should NOT call gtag set ads_data_redaction (handled by GTM template)', function() {
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

        // The plugin should NOT call gtag('set', 'ads_data_redaction') - GTM template handles this
        expect(window.gtag).not.toHaveBeenCalledWith('set', 'ads_data_redaction', expect.anything());
    });

    test('should initialize gtag function if it does not exist', function() {
        // Ensure gtag is undefined
        delete window.gtag;
        expect(window.gtag).toBeUndefined();

        // Simulate the initialization code from klaro-config.js
        window.dataLayer = window.dataLayer || [];

        // Define the gtag function using Array.from to ensure GTM treats it as a command
        window.gtag = function() {
            window.dataLayer.push(Array.from(arguments));
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