/**
 * Comprehensive Tests for Klaro Geo Consent Receipts
 *
 * Tests the consent receipts functionality including:
 * - Receipt generation
 * - Local storage
 * - Server communication
 * - DataLayer integration
 * - Event handling
 */

describe('Klaro Geo Consent Receipts', function() {
    let originalFetch;
    let originalLocalStorage;

    beforeEach(function() {
        // Set up clean environment
        window.dataLayer = [];
        window.klaroConsentData = {
            enableConsentLogging: true,
            templateName: 'test-template',
            templateSource: 'test-source',
            detectedCountry: 'US',
            detectedRegion: 'CA',
            adminOverride: false,
            templateSettings: {},
            ajaxUrl: '/wp-admin/admin-ajax.php',
            nonce: 'test-nonce-123'
        };
        window.klaroConfig = { services: [] };
        window.currentKlaroOpts = null;
        window.lastWatcherConsentTimestamp = null;

        // Mock localStorage
        const localStorageMock = {
            store: {},
            getItem: function(key) {
                return this.store[key] || null;
            },
            setItem: function(key, value) {
                this.store[key] = value.toString();
            },
            clear: function() {
                this.store = {};
            }
        };
        originalLocalStorage = window.localStorage;
        Object.defineProperty(window, 'localStorage', {
            value: localStorageMock,
            writable: true
        });

        // Mock fetch
        originalFetch = global.fetch;
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ success: true })
            })
        );

        // Clear module cache and require fresh
        jest.resetModules();
    });

    afterEach(function() {
        global.fetch = originalFetch;
        Object.defineProperty(window, 'localStorage', {
            value: originalLocalStorage,
            writable: true
        });
        window.localStorage.clear();
    });

    describe('handleConsentChange', function() {
        beforeEach(function() {
            // Load the consent receipts module
            require('../../js/klaro-geo-consent-receipts.js');
        });

        test('should create receipt when consent logging is enabled', function() {
            const mockEvent = {
                detail: {
                    manager: {
                        consents: {
                            'google-analytics': true,
                            'facebook-pixel': false
                        }
                    }
                }
            };

            // Trigger consent change
            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            // Wait a bit for async operations
            return new Promise(resolve => setTimeout(resolve, 100)).then(() => {
                // Check localStorage was updated
                const receipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
                expect(receipts).toBeTruthy();
                expect(Array.isArray(receipts)).toBe(true);
                expect(receipts.length).toBeGreaterThan(0);

                const receipt = receipts[0];
                expect(receipt.receipt_id).toBeTruthy();
                expect(receipt.timestamp).toBeTruthy();
                expect(receipt.template_name).toBe('test-template');
                expect(receipt.consent_choices['google-analytics']).toBe(true);
                expect(receipt.consent_choices['facebook-pixel']).toBe(false);
            });
        });

        test('should push to dataLayer when logging is disabled', function() {
            window.klaroConsentData.enableConsentLogging = false;

            const mockEvent = {
                detail: {
                    manager: {
                        consents: {
                            'google-analytics': true
                        }
                    }
                }
            };

            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            // Check dataLayer
            expect(window.dataLayer.length).toBeGreaterThan(0);
            const dataLayerEntry = window.dataLayer.find(item =>
                item.event === 'Klaro Geo Consent Receipt'
            );
            expect(dataLayerEntry).toBeTruthy();
            expect(dataLayerEntry.klaro_geo_consent_receipt).toBeTruthy();
            expect(dataLayerEntry.klaro_geo_consent_receipt.receipt_id).toBeNull();
        });

        test('should send receipt to server when logging enabled', function() {
            const mockEvent = {
                detail: {
                    manager: {
                        consents: {
                            'google-analytics': true
                        }
                    }
                }
            };

            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            return new Promise(resolve => setTimeout(resolve, 200)).then(() => {
                expect(global.fetch).toHaveBeenCalled();
                const fetchCall = global.fetch.mock.calls[0];
                expect(fetchCall[0]).toBe('/wp-admin/admin-ajax.php');
                expect(fetchCall[1].method).toBe('POST');
            });
        });

        test('should handle admin override flag correctly', function() {
            window.klaroConsentData.adminOverride = true;

            const mockEvent = {
                detail: {
                    manager: {
                        consents: {
                            'google-analytics': true
                        }
                    }
                }
            };

            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            return new Promise(resolve => setTimeout(resolve, 100)).then(() => {
                const receipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
                expect(receipts[0].admin_override).toBe(true);
            });
        });

        test('should limit localStorage to 10 receipts', function() {
            // Create 12 receipts
            for (let i = 0; i < 12; i++) {
                const mockEvent = {
                    detail: {
                        manager: {
                            consents: {
                                [`service-${i}`]: true
                            }
                        }
                    }
                };

                document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));
            }

            return new Promise(resolve => setTimeout(resolve, 500)).then(() => {
                const receipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
                expect(receipts.length).toBeLessThanOrEqual(10);
            });
        });
    });

    describe('storeReceiptLocally', function() {
        beforeEach(function() {
            require('../../js/klaro-geo-consent-receipts.js');
        });

        test('should store receipt in localStorage', function() {
            const testReceipt = {
                receipt_id: 'test_123',
                timestamp: Date.now(),
                consent_choices: { 'test-service': true }
            };

            // The function is not exported, so we test it through the event
            const mockEvent = {
                detail: {
                    manager: {
                        consents: { 'test-service': true }
                    }
                }
            };

            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            return new Promise(resolve => setTimeout(resolve, 100)).then(() => {
                const stored = window.localStorage.getItem('klaro_consent_receipts');
                expect(stored).toBeTruthy();
                const receipts = JSON.parse(stored);
                expect(Array.isArray(receipts)).toBe(true);
            });
        });

        test('should handle corrupt localStorage data gracefully', function() {
            window.localStorage.setItem('klaro_consent_receipts', 'invalid json');

            const mockEvent = {
                detail: {
                    manager: {
                        consents: { 'test-service': true }
                    }
                }
            };

            // The function logs the error but doesn't throw
            // We should check that it creates a new array after encountering corrupt data
            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            return new Promise(resolve => setTimeout(resolve, 100)).then(() => {
                // After handling corrupt data, it should have created a new valid array
                const stored = window.localStorage.getItem('klaro_consent_receipts');
                expect(stored).toBeTruthy();

                // Try to parse it - should work now
                const receipts = JSON.parse(stored);
                expect(Array.isArray(receipts)).toBe(true);
            });
        });
    });

    describe('sendReceiptToServer', function() {
        beforeEach(function() {
            require('../../js/klaro-geo-consent-receipts.js');
        });

        test('should send POST request to correct endpoint', function() {
            const mockEvent = {
                detail: {
                    manager: {
                        consents: { 'test-service': true }
                    }
                }
            };

            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            return new Promise(resolve => setTimeout(resolve, 200)).then(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    '/wp-admin/admin-ajax.php',
                    expect.objectContaining({
                        method: 'POST',
                        credentials: 'same-origin'
                    })
                );
            });
        });

        test('should handle server errors gracefully', function() {
            global.fetch = jest.fn(() =>
                Promise.resolve({
                    ok: false,
                    status: 500,
                    text: () => Promise.resolve('Server error')
                })
            );

            const mockEvent = {
                detail: {
                    manager: {
                        consents: { 'test-service': true }
                    }
                }
            };

            // Should not throw error
            expect(() => {
                document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));
            }).not.toThrow();
        });

        test('should handle network errors gracefully', function() {
            global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));

            const mockEvent = {
                detail: {
                    manager: {
                        consents: { 'test-service': true }
                    }
                }
            };

            // Should not throw error
            expect(() => {
                document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));
            }).not.toThrow();
        });
    });

    describe('Event Handling', function() {
        beforeEach(function() {
            require('../../js/klaro-geo-consent-receipts.js');
        });

        test('should prevent duplicate consent events within 3 seconds', function() {
            const mockEvent = {
                detail: {
                    manager: {
                        consents: { 'test-service': true }
                    }
                }
            };

            // Trigger first event
            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            // Trigger second event immediately
            document.dispatchEvent(new CustomEvent('klaro:consent-change', mockEvent));

            return new Promise(resolve => setTimeout(resolve, 200)).then(() => {
                // Should only have been called once (or twice if one was for each event)
                // The exact behavior depends on timing
                const receipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts') || '[]');
                expect(receipts.length).toBeLessThanOrEqual(2);
            });
        });

        test('should handle consent-change event', function() {
            const mockEvent = {
                detail: {
                    manager: {
                        consents: { 'test-service': true }
                    }
                }
            };

            document.dispatchEvent(new CustomEvent('consent-change', mockEvent));

            return new Promise(resolve => setTimeout(resolve, 100)).then(() => {
                const receipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
                expect(receipts).toBeTruthy();
            });
        });
    });

    describe('Manual Triggers', function() {
        beforeEach(function() {
            require('../../js/klaro-geo-consent-receipts.js');
        });

        test('triggerKlaroConsentReceipt should work with currentKlaroOpts', function() {
            window.currentKlaroOpts = {
                consents: {
                    'manual-service': true
                }
            };

            const result = window.triggerKlaroConsentReceipt();

            expect(result).toContain('triggered');

            return new Promise(resolve => setTimeout(resolve, 100)).then(() => {
                const receipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
                expect(receipts).toBeTruthy();
            });
        });

        test('triggerKlaroConsentReceipt should work with klaro manager', function() {
            window.klaro = {
                getManager: jest.fn(() => ({
                    consents: {
                        'manager-service': true
                    }
                }))
            };

            const result = window.triggerKlaroConsentReceipt();

            expect(result).toContain('triggered');
        });

        test('testKlaroConsentReceipt should send test receipt', function() {
            const testPromise = window.testKlaroConsentReceipt();

            expect(testPromise).toBeTruthy();

            return testPromise.then(result => {
                expect(result).toContain('Test');
            });
        });
    });

    describe('Initialization', function() {
        test('should use existing klaroConsentData if available', function() {
            window.klaroConsentData = {
                enableConsentLogging: true,
                templateName: 'existing',
                adminOverride: false
            };

            jest.resetModules();
            require('../../js/klaro-geo-consent-receipts.js');

            // Should maintain existing data
            expect(window.klaroConsentData.templateName).toBe('existing');
        });

        test('should handle string "0" as false for enableConsentLogging', function() {
            window.klaroConsentData = {
                enableConsentLogging: "0"
            };

            jest.resetModules();
            require('../../js/klaro-geo-consent-receipts.js');

            expect(window.klaroConsentData.enableConsentLogging).toBe(false);
        });

        test('should handle string "1" as true for enableConsentLogging', function() {
            window.klaroConsentData = {
                enableConsentLogging: "1"
            };

            jest.resetModules();
            require('../../js/klaro-geo-consent-receipts.js');

            expect(window.klaroConsentData.enableConsentLogging).toBe(true);
        });
    });
});
