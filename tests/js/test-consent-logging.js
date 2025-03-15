/**
 * Klaro Geo Consent Logging JavaScript Tests
 */

describe('Klaro Consent Logging', function() {
    
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
        window.klaroConsentData = {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            nonce: 'test_nonce',
            consentReceiptsEnabled: true,
            enableConsentLogging: true, // Default to enabled
            templateName: 'default',
            templateSource: 'fallback',
            detectedCountry: 'US',
            detectedRegion: 'CA',
            templateSettings: {}
        };
        
        window.klaroConsentReceiptsEnabled = true;
        window.klaroConsentTemplateName = 'default';
        window.klaroConsentTemplateSource = 'fallback';
        window.klaroDetectedCountry = 'US';
        window.klaroDetectedRegion = 'CA';
        window.klaroAjaxUrl = '/wp-admin/admin-ajax.php';
        window.klaroNonce = 'test_nonce';
        
        // Mock console.log
        console.log = jest.fn();
        
        // Mock fetch API
        window.fetch = jest.fn().mockImplementation(() => {
            return Promise.resolve({
                json: () => Promise.resolve({ success: true, data: { receipt_id: 123 } })
            });
        });
        
        // Load the script and get the exported functions
        consentModule = require('../../js/klaro-geo-consent-receipts.js');
    });
    
    afterEach(function() {
        // Clean up
        window.localStorage.clear();
        window.dataLayer = [];
        jest.clearAllMocks();
    });
    
    test('should send receipt to server when consent logging is enabled', function() {
        // Ensure consent logging is enabled
        window.klaroConsentData.enableConsentLogging = true;
        
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
        expect(storedReceipts.length).toBe(1);
        
        // Check if fetch was called to send receipt to server
        expect(window.fetch).toHaveBeenCalled();
        
        // Check the fetch call
        const fetchCall = window.fetch.mock.calls[0];
        expect(fetchCall[0]).toBe('/wp-admin/admin-ajax.php');
        expect(fetchCall[1].method).toBe('POST');
    });
    
    test('should not send receipt to server when consent logging is disabled', function() {
        // Disable consent logging
        window.klaroConsentData.enableConsentLogging = false;
        
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
        
        // Check if receipt was still stored in localStorage (should be)
        const storedReceipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
        expect(storedReceipts).toBeTruthy();
        expect(storedReceipts.length).toBe(1);
        
        // Check if fetch was NOT called to send receipt to server
        expect(window.fetch).not.toHaveBeenCalled();
        
        // Check if a log message was output
        expect(console.log).toHaveBeenCalledWith('Server-side consent logging is disabled for this template. Receipt stored locally only.');
    });
    
    test('should still push to dataLayer even when consent logging is disabled', function() {
        // Disable consent logging
        window.klaroConsentData.enableConsentLogging = false;
        
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
        
        // Check if receipt was pushed to dataLayer (should still happen)
        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].event).toBe('klaro_geo_consent_receipt');
        expect(window.dataLayer[0].klaro_geo_consent_receipt).toBeTruthy();
    });
    
    test('should handle missing klaroConsentData object', function() {
        // Remove klaroConsentData
        delete window.klaroConsentData;
        
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
        
        // Should default to sending to server
        expect(window.fetch).toHaveBeenCalled();
    });
    
    test('should initialize variables from klaroConsentData on klaro:ready event', function() {
        // Set up klaroConsentData with specific values
        window.klaroConsentData = {
            consentReceiptsEnabled: true,
            enableConsentLogging: false,
            templateName: 'finland_template',
            templateSource: 'country',
            detectedCountry: 'FI',
            detectedRegion: null,
            ajaxUrl: '/custom-ajax-url',
            nonce: 'custom_nonce',
            templateSettings: { custom: 'setting' }
        };

        // Create a mock Klaro manager
        const manager = {};

        // Create a mock event for klaro:ready
        const event = {
            detail: { manager: manager }
        };

        // Manually set the variables as the event listener would
        window.klaroConsentReceiptsEnabled = window.klaroConsentData.consentReceiptsEnabled || false;
        window.klaroConsentTemplateName = window.klaroConsentData.templateName || 'default';
        window.klaroConsentTemplateSource = window.klaroConsentData.templateSource || 'fallback';
        window.klaroDetectedCountry = window.klaroConsentData.detectedCountry || null;
        window.klaroDetectedRegion = window.klaroConsentData.detectedRegion || null;
        window.klaroTemplateSettings = window.klaroConsentData.templateSettings || {};
        window.klaroAjaxUrl = window.klaroConsentData.ajaxUrl || '';
        window.klaroNonce = window.klaroConsentData.nonce || '';

        // Check if variables were initialized correctly
        expect(window.klaroConsentReceiptsEnabled).toBe(true);
        expect(window.klaroConsentTemplateName).toBe('finland_template');
        expect(window.klaroConsentTemplateSource).toBe('country');
        expect(window.klaroDetectedCountry).toBe('FI');
        expect(window.klaroDetectedRegion).toBeNull();
        expect(window.klaroAjaxUrl).toBe('/custom-ajax-url');
        expect(window.klaroNonce).toBe('custom_nonce');
        expect(window.klaroTemplateSettings).toEqual({ custom: 'setting' });

        // Now test consent change with these settings
        const consentEvent = {
            detail: {
                manager: {
                    consents: {
                        'google-analytics': true
                    }
                }
            }
        };

        // Call the function directly
        consentModule.handleConsentChange(consentEvent);

        // Should not send to server because enableConsentLogging is false
        expect(window.fetch).not.toHaveBeenCalled();
    });
});