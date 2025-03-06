/**
 * Klaro Geo Consent Receipts JavaScript Tests
 */

describe('Klaro Consent Receipts', function() {
    
    let consentModule;
    
    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = '';
        
        // Mock localStorage
        const localStorageMock = (function() {
            let store = {};
            return {
                getItem: function(key) {
                    return store[key] || null;
                },
                setItem: function(key, value) {
                    store[key] = value.toString();
                },
                clear: function() {
                    store = {};
                },
                removeItem: function(key) {
                    delete store[key];
                }
            };
        })();
        
        Object.defineProperty(window, 'localStorage', {
            value: localStorageMock
        });
        
        // Mock dataLayer
        window.dataLayer = [];
        
        // Set up Klaro consent variables
        window.klaroConsentReceiptsEnabled = true;
        window.klaroConsentTemplateName = 'default';
        window.klaroConsentTemplateSource = 'fallback';
        window.klaroDetectedCountry = 'US';
        window.klaroDetectedRegion = 'CA';
        window.klaroAjaxUrl = '/wp-admin/admin-ajax.php';
        window.klaroNonce = 'test_nonce';
        window.klaroTemplateSettings = {
            consentModalTitle: 'Privacy Settings',
            consentModalDescription: 'Test description',
            acceptAllText: 'Accept All',
            declineAllText: 'Decline All',
            defaultConsent: false,
            requiredConsent: false
        };
        
        // Mock fetch API
        window.fetch = jest.fn().mockImplementation(() => {
            return Promise.resolve({
                json: () => Promise.resolve({ success: true, data: { receipt_id: 123 } })
            });
        });
        
        // Load the script and get the exported functions
        consentModule = require('../../js/klaro-consent-receipts.js');
    });
    
    afterEach(function() {
        // Clean up
        window.localStorage.clear();
        window.dataLayer = [];
        jest.clearAllMocks();
    });
    
    test('should store receipt in localStorage on consent change', function() {
        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-analytics': true,
                'facebook-pixel': false
            }
        };
        
        // Create a mock event
        const event = {
            detail: { manager: manager }
        };
        
        // Call the function directly
        consentModule.handleConsentChange(event);
        
        // Check if receipt was stored in localStorage
        const storedReceipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
        expect(storedReceipts).toBeTruthy();
        expect(Array.isArray(storedReceipts)).toBe(true);
        expect(storedReceipts.length).toBe(1);
        
        // Check receipt structure
        const receipt = storedReceipts[0];
        expect(receipt.receipt_id).toBeTruthy();
        expect(receipt.timestamp).toBeTruthy();
        expect(receipt.template_name).toBe('default');
        expect(receipt.template_source).toBe('fallback');
        expect(receipt.country_code).toBe('US');
        expect(receipt.region_code).toBe('CA');
        expect(receipt.consent_choices).toEqual({
            'google-analytics': true,
            'facebook-pixel': false
        });
    });
    
    test('should push receipt to dataLayer on consent change', function() {
        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-analytics': true,
                'facebook-pixel': false
            }
        };
        
        // Create a mock event
        const event = {
            detail: { manager: manager }
        };
        
        // Call the function directly
        consentModule.handleConsentChange(event);
        
        // Check if receipt was pushed to dataLayer
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].event).toBe('klaro_geo_consent_receipt');
        expect(window.dataLayer[0].klaro_geo_consent_receipt).toBeTruthy();
        
        // Check receipt structure in dataLayer
        const receipt = window.dataLayer[0].klaro_geo_consent_receipt;
        expect(receipt.receipt_id).toBeTruthy();
        expect(receipt.template_name).toBe('default');
        expect(receipt.consent_choices).toEqual({
            'google-analytics': true,
            'facebook-pixel': false
        });
    });
    
    test('should send receipt to server on consent change', function() {
        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-analytics': true,
                'facebook-pixel': false
            }
        };
        
        // Create a mock event
        const event = {
            detail: { manager: manager }
        };
        
        // Call the function directly
        consentModule.handleConsentChange(event);
        
        // Check if fetch was called to send receipt to server
        expect(window.fetch).toHaveBeenCalled();
        
        // Check the fetch call
        const fetchCall = window.fetch.mock.calls[0];
        expect(fetchCall[0]).toBe('/wp-admin/admin-ajax.php');
        expect(fetchCall[1].method).toBe('POST');
        expect(fetchCall[1].credentials).toBe('same-origin');
        
        // Check that FormData was used with the correct action and nonce
        const formData = fetchCall[1].body;
        expect(formData instanceof FormData).toBe(true);
    });
    
    test('should limit stored receipts to 10', function() {
        // Create 11 receipts
        for (let i = 0; i < 11; i++) {
            // Create a mock Klaro manager
            const manager = {
                consents: {
                    'google-analytics': i % 2 === 0, // Alternate true/false
                    'facebook-pixel': i % 3 === 0
                }
            };
            
            // Create a mock event
            const event = {
                detail: { manager: manager }
            };
            
            // Call the function directly
            consentModule.handleConsentChange(event);
        }
        
        // Check if only 10 receipts were stored
        const storedReceipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
        expect(storedReceipts.length).toBe(10);
        
        // Check that the oldest receipt was removed (FIFO)
        const firstReceipt = storedReceipts[0];
        expect(firstReceipt.consent_choices['google-analytics']).toBe(false); // Second receipt (i=1)
    });
    
    test('should not process if consent receipts are disabled', function() {
        // Disable consent receipts
        window.klaroConsentReceiptsEnabled = false;
        
        // Create a mock Klaro manager
        const manager = {
            consents: {
                'google-analytics': true,
                'facebook-pixel': false
            }
        };
        
        // Create a mock event
        const event = {
            detail: { manager: manager }
        };
        
        // Call the function directly
        consentModule.handleConsentChange(event);
        
        // Check that nothing was stored or sent
        expect(window.localStorage.getItem('klaro_consent_receipts')).toBeNull();
        expect(window.dataLayer.length).toBe(0);
        expect(window.fetch).not.toHaveBeenCalled();
    });
});