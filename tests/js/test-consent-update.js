/**
 * Klaro Consent Update Function Tests
 */

describe('Consent Update Function', function() {

    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = '';

        // Mock dataLayer
        window.dataLayer = [];

        // Mock gtag function
        window.gtag = jest.fn();

        // Define the handleConsentUpdate function directly
        window.handleConsentUpdate = function(type, granted) {
            window.gtag('consent', 'update', {
                [type]: granted ? 'granted' : 'denied'
            });
        };
    });

    afterEach(function() {
        // Clean up
        window.dataLayer = [];
        jest.clearAllMocks();
        delete window.handleConsentUpdate;
    });

    test('should update ad_storage consent correctly', function() {
        // Test granting ad_storage consent
        window.handleConsentUpdate('ad_storage', true);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_storage': 'granted'
        });

        // Reset mock
        window.gtag.mockClear();

        // Test denying ad_storage consent
        window.handleConsentUpdate('ad_storage', false);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_storage': 'denied'
        });
    });

    test('should update analytics_storage consent correctly', function() {
        // Test granting analytics_storage consent
        window.handleConsentUpdate('analytics_storage', true);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'analytics_storage': 'granted'
        });

        // Reset mock
        window.gtag.mockClear();

        // Test denying analytics_storage consent
        window.handleConsentUpdate('analytics_storage', false);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'analytics_storage': 'denied'
        });
    });

    test('should update ad_user_data consent correctly', function() {
        // Test granting ad_user_data consent
        window.handleConsentUpdate('ad_user_data', true);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_user_data': 'granted'
        });

        // Reset mock
        window.gtag.mockClear();

        // Test denying ad_user_data consent
        window.handleConsentUpdate('ad_user_data', false);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_user_data': 'denied'
        });
    });

    test('should update ad_personalization consent correctly', function() {
        // Test granting ad_personalization consent
        window.handleConsentUpdate('ad_personalization', true);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_personalization': 'granted'
        });

        // Reset mock
        window.gtag.mockClear();

        // Test denying ad_personalization consent
        window.handleConsentUpdate('ad_personalization', false);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_personalization': 'denied'
        });
    });

    test('should handle custom consent types', function() {
        // Test with a custom consent type
        window.handleConsentUpdate('custom_storage', true);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'custom_storage': 'granted'
        });

        // Reset mock
        window.gtag.mockClear();

        // Test denying custom consent
        window.handleConsentUpdate('custom_storage', false);

        // Check if gtag was called with the correct parameters
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'custom_storage': 'denied'
        });
    });

    test('should handle boolean values correctly', function() {
        // Test with explicit boolean true
        window.handleConsentUpdate('ad_storage', true);
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_storage': 'granted'
        });

        // Reset mock
        window.gtag.mockClear();

        // Test with explicit boolean false
        window.handleConsentUpdate('ad_storage', false);
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_storage': 'denied'
        });

        // Reset mock
        window.gtag.mockClear();

        // Test with truthy value (1)
        window.handleConsentUpdate('ad_storage', 1);
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_storage': 'granted'
        });

        // Reset mock
        window.gtag.mockClear();

        // Test with falsy value (0)
        window.handleConsentUpdate('ad_storage', 0);
        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_storage': 'denied'
        });
    });

    test('should handle multiple consecutive updates', function() {
        // Update multiple consent types in sequence
        window.handleConsentUpdate('ad_storage', true);
        window.handleConsentUpdate('analytics_storage', false);
        window.handleConsentUpdate('ad_user_data', true);

        // Check if gtag was called for each update
        expect(window.gtag).toHaveBeenCalledTimes(3);

        // Check the calls in order
        expect(window.gtag.mock.calls[0]).toEqual(['consent', 'update', { 'ad_storage': 'granted' }]);
        expect(window.gtag.mock.calls[1]).toEqual(['consent', 'update', { 'analytics_storage': 'denied' }]);
        expect(window.gtag.mock.calls[2]).toEqual(['consent', 'update', { 'ad_user_data': 'granted' }]);
    });
});