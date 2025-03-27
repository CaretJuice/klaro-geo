/**
 * Klaro Consent Event Listener Tests
 */

describe('Klaro Consent Event Listener', function() {

    // Helper function to simulate the event listener behavior
    function simulateConsentChange(manager) {
        // Generate a unique receipt number if logging is enabled
        var receiptNumber = window.klaroConsentData.enableConsentLogging && window.klaro_geo_logging_enabled ?
            'receipt_' + new Date().getTime() + '_' + Math.random().toString(36).substr(2, 9) :
            null;

        // Create a simple consent receipt for dataLayer
        var consentReceipt = {
            'event': 'klaro_geo_consent_receipt',
            'klaro_geo_consent_timestamp': new Date().toISOString(),
            'klaro_geo_consent_receipt_number': receiptNumber,
            'klaro_geo_logging_enabled': window.klaro_geo_logging_enabled || false,
            'klaro_geo_consent_choices': {}
        };

        // Add each service consent choice to the receipt
        for (var serviceName in manager.consents) {
            consentReceipt.klaro_geo_consent_choices[serviceName] = manager.consents[serviceName];
        }

        // Push the consent receipt to the dataLayer
        window.dataLayer.push(consentReceipt);
    }

    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = '';

        // Mock dataLayer
        window.dataLayer = [];

        // Initialize Klaro consent data object
        window.klaroConsentData = {};

        // Set up Klaro consent variables
        window.klaroConsentData.enableConsentLogging = true; // Boolean true
        window.klaro_geo_logging_enabled = false;
        window.klaroConsentData.templateName = 'default';
        window.klaroConsentData.templateSource = 'fallback';
        window.klaroConsentData.detectedCountry = 'US';
        window.klaroConsentData.detectedRegion = 'CA';
    });

    afterEach(function() {
        // Clean up
        window.dataLayer = [];
        jest.clearAllMocks();

        // Reset klaroConsentData
        window.klaroConsentData = {};

        // Reset other variables
        delete window.klaro_geo_logging_enabled;
    });

    test('should push to dataLayer when consent changes', function() {
        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true,
                'facebook-pixel': false
            }
        };

        // Simulate the event listener behavior
        simulateConsentChange(manager);

        // Check if dataLayer was updated
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].event).toBe('klaro_geo_consent_receipt');
        expect(window.dataLayer[0].klaro_geo_consent_choices).toEqual({
            'google-tag-manager': true,
            'facebook-pixel': false
        });
    });

    test('should include receipt number when logging is enabled', function() {
        // Enable logging
        window.klaro_geo_logging_enabled = true;

        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Simulate the event listener behavior
        simulateConsentChange(manager);

        // Check if receipt number was included
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toBeTruthy();
        expect(typeof window.dataLayer[0].klaro_geo_consent_receipt_number).toBe('string');
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toMatch(/^receipt_\d+_[a-z0-9]+$/);
    });

    test('should not include receipt number when logging is disabled', function() {
        // Ensure logging is disabled
        window.klaro_geo_logging_enabled = false;

        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Simulate the event listener behavior
        simulateConsentChange(manager);

        // Check if receipt number was not included
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toBeNull();
    });

    test('should not include receipt number when consent receipts are disabled', function() {
        // Enable logging but disable consent receipts
        window.klaro_geo_logging_enabled = true;
        window.klaroConsentData.enableConsentLogging = false;

        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Simulate the event listener behavior
        simulateConsentChange(manager);

        // Check if receipt number was not included
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_receipt_number).toBeNull();
    });

    test('should include timestamp in ISO format', function() {
        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Simulate the event listener behavior
        simulateConsentChange(manager);

        // Check if timestamp was included in ISO format
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_timestamp).toBeTruthy();

        // Verify it's a valid ISO timestamp
        const timestamp = new Date(window.dataLayer[0].klaro_geo_consent_timestamp);
        expect(timestamp instanceof Date).toBe(true);
        expect(isNaN(timestamp.getTime())).toBe(false);
    });

    test('should handle multiple consent changes', function() {
        // First consent change
        const manager1 = {
            consents: {
                'google-tag-manager': true,
                'facebook-pixel': false
            }
        };

        // Simulate the first event
        simulateConsentChange(manager1);

        // Second consent change
        const manager2 = {
            consents: {
                'google-tag-manager': false,
                'facebook-pixel': true
            }
        };

        // Simulate the second event
        simulateConsentChange(manager2);

        // Check if both events were recorded
        expect(window.dataLayer.length).toBe(2);

        // Check first event
        expect(window.dataLayer[0].klaro_geo_consent_choices).toEqual({
            'google-tag-manager': true,
            'facebook-pixel': false
        });

        // Check second event
        expect(window.dataLayer[1].klaro_geo_consent_choices).toEqual({
            'google-tag-manager': false,
            'facebook-pixel': true
        });
    });

    test('should handle empty consents object', function() {
        // Create a mock Klaro manager with no consents
        const manager = {
            consents: {}
        };

        // Simulate the event listener behavior
        simulateConsentChange(manager);

        // Check if the event was processed correctly
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_consent_choices).toEqual({});
    });

    test('should include logging enabled status', function() {
        // Test with logging disabled
        window.klaro_geo_logging_enabled = false;

        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-tag-manager': true
            }
        };

        // Simulate the event listener behavior
        simulateConsentChange(manager);

        // Check if logging status was included
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_logging_enabled).toBe(false);

        // Clear dataLayer
        window.dataLayer = [];

        // Test with logging enabled
        window.klaro_geo_logging_enabled = true;

        // Simulate the event listener behavior again
        simulateConsentChange(manager);

        // Check if logging status was updated
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaro_geo_logging_enabled).toBe(true);
    });
});