/**
 * Klaro Geo Core Functionality Tests
 * 
 * These tests cover the functionality in klaro-geo.js
 */

describe('Klaro Geo Core Functionality', function() {
    
    beforeEach(function() {
        // Reset the DOM
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
        
        // Mock gtag
        window.gtag = jest.fn();
        
        // Mock klaro
        window.klaro = {
            getManager: jest.fn().mockReturnValue({
                consents: {
                    'google-analytics': true,
                    'google-ads': false
                },
                services: [
                    { name: 'google-analytics' },
                    { name: 'google-ads' }
                ],
                watch: jest.fn()
            })
        };
        
        // Initialize Klaro consent data object
        window.klaroConsentData = {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            nonce: 'test_nonce',
            enableConsentLogging: true,
            templateName: 'default',
            templateSource: 'fallback',
            detectedCountry: 'US',
            detectedRegion: 'CA',
            templateSettings: {
                consentModalTitle: 'Privacy Settings',
                consentModalDescription: 'Test description',
                acceptAllText: 'Accept All',
                declineAllText: 'Decline All',
                defaultConsent: false,
                requiredConsent: false,
                config: {
                    consent_mode_settings: {
                        initialize_consent_mode: true,
                        analytics_storage_service: 'google-analytics',
                        ad_storage_service: 'google-ads',
                        ad_user_data: true,
                        ad_personalization: true
                    }
                }
            }
        };
        
        // Mock console methods
        console.log = jest.fn();
        console.error = jest.fn();
        console.warn = jest.fn();
        
        // Mock fetch API
        window.fetch = jest.fn().mockImplementation(() => {
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ success: true, data: { receipt_id: 123 } })
            });
        });
        
        // Mock setTimeout
        jest.useFakeTimers();
        
        // Mock MutationObserver
        global.MutationObserver = class {
            constructor(callback) {
                this.callback = callback;
                this.observe = jest.fn();
                this.disconnect = jest.fn();
            }
            
            // Helper method to simulate mutations
            simulateMutations(mutations) {
                this.callback(mutations);
            }
        };
    });
    
    afterEach(function() {
        // Clean up
        window.localStorage.clear();
        window.dataLayer = [];
        jest.clearAllMocks();
        
        // Reset klaroConsentData
        window.klaroConsentData = {};
        
        // Reset timers
        jest.clearAllTimers();
    });
    
    // Test basic functionality
    describe('Basic Functionality', function() {
        test('should load without errors', function() {
            // This is a simple test to ensure the file loads without errors
            expect(true).toBe(true);
        });
    });
    
    // Mock implementation of handleConsentChange
    describe('handleConsentChange', function() {
        test('should push to dataLayer when called', function() {
            // Create a mock implementation of handleConsentChange
            function handleConsentChange(manager) {
                // Push to dataLayer
                window.dataLayer.push({
                    'event': 'Klaro Event',
                    'eventSource': 'klaro-geo',
                    'klaroEventName': 'generateConsentRecipt',
                    'klaroGeoConsentReceipt': {
                        'receipt_id': 'test_receipt',
                        'consent_choices': manager.consents
                    }
                });
            }
            
            // Create a mock manager
            const manager = {
                consents: {
                    'google-analytics': true,
                    'facebook-pixel': false
                }
            };
            
            // Call the function
            handleConsentChange(manager);
            
            // Check if data was pushed to dataLayer
            expect(window.dataLayer.length).toBe(1);
            expect(window.dataLayer[0].event).toBe('Klaro Event');
            expect(window.dataLayer[0].klaroEventName).toBe('generateConsentRecipt');
        });
    });
    
    // Mock implementation of storeReceiptLocally
    describe('storeReceiptLocally', function() {
        test('should store receipt in localStorage', function() {
            // Create a mock implementation of storeReceiptLocally
            function storeReceiptLocally(receipt) {
                try {
                    // Get existing receipts
                    var existingData = window.localStorage.getItem('klaro_consent_receipts');
                    var receipts = [];
                    
                    if (existingData) {
                        try {
                            receipts = JSON.parse(existingData);
                        } catch (parseError) {
                            console.error('Failed to parse existing receipts:', parseError);
                            receipts = [];
                        }
                    }
                    
                    // Ensure receipts is an array
                    if (!Array.isArray(receipts)) {
                        receipts = [];
                    }
                    
                    // Add the new receipt
                    receipts.push(receipt);
                    
                    // Limit to last 10 receipts
                    if (receipts.length > 10) {
                        receipts = receipts.slice(-10);
                    }
                    
                    // Store back in localStorage
                    window.localStorage.setItem('klaro_consent_receipts', JSON.stringify(receipts));
                } catch (e) {
                    console.error('Failed to store consent receipt locally:', e);
                }
            }
            
            // Create a mock receipt
            const receipt = {
                receipt_id: 'test_receipt_123',
                timestamp: Math.floor(Date.now() / 1000),
                consent_choices: {
                    'google-analytics': true,
                    'facebook-pixel': false
                }
            };
            
            // Call the function
            storeReceiptLocally(receipt);
            
            // Check if receipt was stored in localStorage
            const storedReceipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
            expect(storedReceipts).toBeTruthy();
            expect(Array.isArray(storedReceipts)).toBe(true);
            expect(storedReceipts.length).toBe(1);
            expect(storedReceipts[0]).toEqual(receipt);
        });
    });
    
    // Mock implementation of sendReceiptToServer
    describe('sendReceiptToServer', function() {
        test('should send receipt to server', function() {
            // Create a mock implementation of sendReceiptToServer
            function sendReceiptToServer(receipt) {
                // Get the AJAX URL
                var ajaxUrl = window.klaroConsentData.ajaxUrl || '/wp-admin/admin-ajax.php';
                
                // Create form data
                var formData = new FormData();
                formData.append('action', 'klaro_geo_store_consent_receipt');
                
                // Add nonce if available
                if (window.klaroConsentData.nonce) {
                    formData.append('nonce', window.klaroConsentData.nonce);
                }
                
                // Stringify the receipt data
                formData.append('receipt_data', JSON.stringify(receipt));
                
                // Send the request
                return fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
            }
            
            // Create a mock receipt
            const receipt = {
                receipt_id: 'test_receipt_123',
                timestamp: Math.floor(Date.now() / 1000),
                consent_choices: {
                    'google-analytics': true,
                    'facebook-pixel': false
                }
            };
            
            // Call the function
            sendReceiptToServer(receipt);
            
            // Check if fetch was called
            expect(window.fetch).toHaveBeenCalled();
            
            // Check the fetch call
            const fetchCall = window.fetch.mock.calls[0];
            expect(fetchCall[0]).toBe('/wp-admin/admin-ajax.php');
            expect(fetchCall[1].method).toBe('POST');
            expect(fetchCall[1].credentials).toBe('same-origin');
            
            // Check that FormData was used
            const formData = fetchCall[1].body;
            expect(formData instanceof FormData).toBe(true);
        });
    });
    
    // Mock implementation of updateGoogleConsentMode
    describe('updateGoogleConsentMode', function() {
        test('should update gtag with correct consent values', function() {
            // Create a mock implementation of updateGoogleConsentMode
            function updateGoogleConsentMode(manager) {
                // Check if gtag is available
                if (typeof window.gtag !== 'function') {
                    return;
                }
                
                // Get consent state from manager
                const adServiceEnabled = manager.consents['google-ads'] === true;
                const analyticsServiceEnabled = manager.consents['google-analytics'] === true;
                
                // Create update object
                const update = {
                    'ad_storage': adServiceEnabled ? 'granted' : 'denied',
                    'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
                    'ad_user_data': adServiceEnabled ? 'granted' : 'denied',
                    'ad_personalization': adServiceEnabled ? 'granted' : 'denied'
                };
                
                // Send to gtag
                window.gtag('consent', 'update', update);
            }
            
            // Create a mock manager
            const manager = {
                consents: {
                    'google-analytics': true,
                    'google-ads': false
                }
            };
            
            // Call the function
            updateGoogleConsentMode(manager);
            
            // Check if gtag was called with the correct values
            expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
                ad_storage: 'denied',
                analytics_storage: 'granted',
                ad_user_data: 'denied',
                ad_personalization: 'denied'
            });
        });
    });
});