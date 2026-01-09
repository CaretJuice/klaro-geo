/**
 * Klaro Geo Consent Mode Tests
 *
 * NOTE: The plugin no longer calls gtag('consent', 'default') or gtag('consent', 'update').
 * Consent mode is now handled by the GTM template using native setDefaultConsentState()
 * and updateConsentState() APIs. The plugin pushes 'Klaro Consent Update' events
 * to the dataLayer with the consentMode object.
 *
 * These tests verify:
 * 1. The consentMode object structure is correct
 * 2. Dynamic service consent keys are generated properly
 * 3. UI controls for ad personalization/user data work correctly
 */

describe('Klaro Geo Consent Mode', function() {
    // Mock dataLayer for testing
    let mockDataLayer;

    // Mock Klaro manager
    const mockKlaroManager = {
        consents: {
            'google-ads': true,
            'google-analytics': true
        },
        watch: jest.fn(),
        getConsent: function(serviceName) {
            return this.consents[serviceName] || false;
        }
    };

    // Mock DOM elements
    let mockServiceListItem;
    let mockControlsListItem;
    let mockParentCheckbox;
    let mockAdPersonalizationCheckbox;
    let mockAdUserDataCheckbox;
    let mockControlsContainer;

    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = `
            <div id="klaro">
                <ul class="cm-services">
                    <li id="service-item-container-google-ads" class="cm-service">
                        <div class="cm-service-header">
                            <div class="cm-service-title">Google Ads</div>
                            <div class="cm-service-info">
                                <input id="service-item-google-ads" type="checkbox" class="cm-list-input" checked>
                                <div class="cm-switch"></div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        `;

        // Mock global objects - using dataLayer instead of gtag for consent mode
        mockDataLayer = [];
        window.dataLayer = mockDataLayer;
        window.gtag = jest.fn(); // Keep gtag mock but it should NOT be called for consent
        window.klaro = {
            getManager: jest.fn().mockReturnValue(mockKlaroManager)
        };

        // Initialize Klaro consent data object with template settings
        // NOTE: initialize_consent_mode has been removed - consent mode is always enabled
        window.klaroConsentData = {
            templateSettings: {
                config: {
                    consent_mode_settings: {
                        ad_storage_service: 'google-ads',
                        analytics_storage_service: 'google-analytics',
                        ad_user_data: true,
                        ad_personalization: true
                    }
                }
            }
        };

        // Reset mock functions
        mockKlaroManager.watch.mockClear();

        // Get DOM elements
        mockServiceListItem = document.getElementById('service-item-container-google-ads');
        mockParentCheckbox = document.getElementById('service-item-google-ads');

        // Create the controls list item
        mockControlsListItem = document.createElement('li');
        mockControlsListItem.className = 'klaro-geo-consent-mode-controls';
        mockControlsListItem.innerHTML = `
            <div class="cm-list-title">Consent Mode Settings</div>
            <div class="klaro-geo-ad-controls">
                <div class="klaro-geo-sub-control">
                    <div class="cm-list-title">Ad Personalization</div>
                    <div class="cm-list-input-container">
                        <input id="klaro-geo-ad-personalization" type="checkbox" class="cm-list-input" checked>
                        <div class="cm-switch"></div>
                    </div>
                </div>
                <div class="klaro-geo-sub-control">
                    <div class="cm-list-title">Ad User Data</div>
                    <div class="cm-list-input-container">
                        <input id="klaro-geo-ad-user-data" type="checkbox" class="cm-list-input" checked>
                        <div class="cm-switch"></div>
                    </div>
                </div>
            </div>
        `;

        // Insert the controls list item after the service list item
        mockServiceListItem.parentNode.insertBefore(mockControlsListItem, mockServiceListItem.nextSibling);

        // Get the checkboxes and container
        mockAdPersonalizationCheckbox = document.getElementById('klaro-geo-ad-personalization');
        mockAdUserDataCheckbox = document.getElementById('klaro-geo-ad-user-data');
        mockControlsContainer = mockControlsListItem.querySelector('.klaro-geo-ad-controls');
    });

    afterEach(function() {
        // Clean up
        document.body.innerHTML = '';
        window.dataLayer = [];
        jest.clearAllMocks();

        // Reset global objects
        delete window.gtag;
        delete window.klaro;
        delete window.klaroConsentData;
        delete window.controlsInjected;
        delete window.klaroConsentCallbackSet;
        delete window.isInitializing;
        delete window.consentUpdateSetup;
        delete window.lastConsentUpdate;
        delete window.pushConsentToDataLayer;
        delete window.updateConsentState;
        delete window.updateControlsState;
        delete window.getServiceConsentKey;
        delete window.adPersonalizationConsent;
        delete window.adUserDataConsent;

        // Reset mockKlaroManager consents for next test
        mockKlaroManager.consents = {
            'google-ads': true,
            'google-analytics': true
        };
    });

    // Helper function to generate consent key from service name
    function getServiceConsentKey(serviceName) {
        return serviceName.replace(/-/g, '_') + '_consent';
    }

    // Helper function to simulate the new consent mode architecture
    // NOTE: Instead of calling gtag('consent', 'update', ...), we now push
    // 'Klaro Consent Update' events to dataLayer with a consentMode object.
    // The GTM template reads this event and calls updateConsentState() API.
    function loadConsentModeScript() {
        // Create a script element
        const script = document.createElement('script');
        script.textContent = `
            // Mock the consent mode functionality (NEW ARCHITECTURE)
            window.adPersonalizationConsent = true;
            window.adUserDataConsent = true;

            // Flag to track if we're in the initialization phase
            window.isInitializing = true;

            // Flag to track if we've already set up the consent update
            window.consentUpdateSetup = false;

            // Store the last consent update to avoid duplicates
            window.lastConsentUpdate = null;

            // Helper function to generate consent key from service name
            window.getServiceConsentKey = function(serviceName) {
                return serviceName.replace(/-/g, '_') + '_consent';
            };

            // Function to push consent update to dataLayer (NEW - no gtag calls)
            window.pushConsentToDataLayer = function(consentUpdate, triggerEvent) {
                // Skip if the update is identical to the last one
                if (window.lastConsentUpdate &&
                    JSON.stringify(window.lastConsentUpdate) === JSON.stringify(consentUpdate)) {
                    console.log('DEBUG: Skipping duplicate consent update');
                    return;
                }

                // Store this update as the last one
                window.lastConsentUpdate = consentUpdate;

                // Build the acceptedServices array
                const manager = window.klaro && window.klaro.getManager ? window.klaro.getManager() : null;
                const acceptedServices = manager ? Object.keys(manager.consents).filter(s => manager.consents[s]) : [];

                // Push the Klaro Consent Update event to dataLayer
                window.dataLayer.push({
                    'event': 'Klaro Consent Update',
                    'eventSource': 'klaro-geo',
                    'consentMode': consentUpdate,
                    'acceptedServices': acceptedServices,
                    'triggerEvent': triggerEvent || 'saveConsents'
                });
            };

            // Mock the updateConsentState function (pushes to dataLayer instead of gtag)
            window.updateConsentState = function(triggerEvent) {
                // Get the current state of services from Klaro manager
                let adServiceEnabled = false;
                let analyticsServiceEnabled = false;
                const manager = window.klaro && window.klaro.getManager ? window.klaro.getManager() : null;

                if (manager && manager.consents) {
                    // Check ad service state
                    adServiceEnabled = manager.consents['google-ads'] === true;

                    // Check analytics service state
                    analyticsServiceEnabled = manager.consents['google-analytics'] === true;
                }

                // Create a complete consent update with all signals (standard + dynamic)
                const consentUpdate = {
                    'ad_storage': adServiceEnabled ? 'granted' : 'denied',
                    'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
                    'ad_user_data': (adServiceEnabled && window.adUserDataConsent) ? 'granted' : 'denied',
                    'ad_personalization': (adServiceEnabled && window.adPersonalizationConsent) ? 'granted' : 'denied'
                };

                // Add dynamic service consent keys
                if (manager && manager.consents) {
                    Object.keys(manager.consents).forEach(function(serviceName) {
                        const dynamicKey = window.getServiceConsentKey(serviceName);
                        consentUpdate[dynamicKey] = manager.consents[serviceName] ? 'granted' : 'denied';
                    });
                }

                // Push to dataLayer (NOT gtag)
                window.pushConsentToDataLayer(consentUpdate, triggerEvent || 'saveConsents');

                // Mark that we're done with initialization
                if (window.isInitializing) {
                    console.log('DEBUG: Initialization complete');
                    window.isInitializing = false;
                    window.consentUpdateSetup = true;
                }
            };

            // Mock the updateControlsState function
            window.updateControlsState = function(adServiceName, isEnabled) {
                const serviceElement = document.getElementById('service-item-' + adServiceName);
                if (!serviceElement) return;

                const serviceListItem = serviceElement.closest('li.cm-service');
                if (!serviceListItem) return;

                const controlsListItem = serviceListItem.nextElementSibling;
                if (!controlsListItem || !controlsListItem.classList.contains('klaro-geo-consent-mode-controls')) return;

                const controlsContainer = controlsListItem.querySelector('.klaro-geo-ad-controls');
                if (!controlsContainer) return;

                if (isEnabled) {
                    controlsContainer.classList.remove('klaro-geo-controls-disabled');
                } else {
                    controlsContainer.classList.add('klaro-geo-controls-disabled');
                }

                const adPersonalizationCheckbox = controlsListItem.querySelector('#klaro-geo-ad-personalization');
                const adUserDataCheckbox = controlsListItem.querySelector('#klaro-geo-ad-user-data');

                if (adPersonalizationCheckbox) {
                    adPersonalizationCheckbox.checked = isEnabled;
                    window.adPersonalizationConsent = isEnabled;
                }

                if (adUserDataCheckbox) {
                    adUserDataCheckbox.checked = isEnabled;
                    window.adUserDataConsent = isEnabled;
                }

                window.updateConsentState('saveConsents');
            };
        `;
        document.body.appendChild(script);
    }

    test('should initialize with correct consolidated consent state in dataLayer', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Trigger the updateConsentState function
        window.updateConsentState('initialConsents');

        // Find the Klaro Consent Update event in dataLayer
        const consentEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify the event was pushed with correct consent mode object
        expect(consentEvent).toBeTruthy();
        expect(consentEvent.consentMode.ad_storage).toBe('granted');
        expect(consentEvent.consentMode.analytics_storage).toBe('granted');
        expect(consentEvent.consentMode.ad_user_data).toBe('granted');
        expect(consentEvent.consentMode.ad_personalization).toBe('granted');

        // Verify dynamic service keys are included
        expect(consentEvent.consentMode.google_ads_consent).toBe('granted');
        expect(consentEvent.consentMode.google_analytics_consent).toBe('granted');

        // Verify that gtag was NOT called for consent (handled by GTM template)
        expect(window.gtag).not.toHaveBeenCalledWith('consent', 'update', expect.anything());

        // Verify that dataLayer has exactly one consent update event
        const consentEvents = window.dataLayer.filter(e => e.event === 'Klaro Consent Update');
        expect(consentEvents.length).toBe(1);

        // Verify that initialization phase is complete
        expect(window.isInitializing).toBe(false);
        expect(window.consentUpdateSetup).toBe(true);
    });

    test('should push Klaro Consent Update event with correct structure', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Trigger the updateConsentState function
        window.updateConsentState('initialConsents');

        // Find the Klaro Consent Update event
        const consentEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify event structure
        expect(consentEvent).toBeTruthy();
        expect(consentEvent.event).toBe('Klaro Consent Update');
        expect(consentEvent.eventSource).toBe('klaro-geo');
        expect(consentEvent.consentMode).toBeDefined();
        expect(consentEvent.acceptedServices).toBeDefined();
        expect(Array.isArray(consentEvent.acceptedServices)).toBe(true);
        expect(consentEvent.triggerEvent).toBe('initialConsents');
    });

    test('should include acceptedServices array with services that have consent', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Trigger the updateConsentState function
        window.updateConsentState('saveConsents');

        // Find the Klaro Consent Update event
        const consentEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Both services have consent=true in mockKlaroManager
        expect(consentEvent.acceptedServices).toContain('google-ads');
        expect(consentEvent.acceptedServices).toContain('google-analytics');
    });

    test('should update consent state when ad personalization checkbox changes', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Complete initialization
        window.updateConsentState('initialConsents');

        // Clear dataLayer for cleaner test
        window.dataLayer = [];
        // Reset lastConsentUpdate to allow new event
        window.lastConsentUpdate = null;

        // Change the ad personalization checkbox state
        mockAdPersonalizationCheckbox.checked = false;
        window.adPersonalizationConsent = false;

        // Trigger the updateConsentState function
        window.updateConsentState('saveConsents');

        // Find the Klaro Consent Update event in dataLayer
        const consentEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify the consentMode object has the updated ad_personalization value
        expect(consentEvent).toBeTruthy();
        expect(consentEvent.consentMode.ad_storage).toBe('granted');
        expect(consentEvent.consentMode.analytics_storage).toBe('granted');
        expect(consentEvent.consentMode.ad_user_data).toBe('granted');
        expect(consentEvent.consentMode.ad_personalization).toBe('denied');

        // Verify that gtag was NOT called for consent
        expect(window.gtag).not.toHaveBeenCalledWith('consent', 'update', expect.anything());
    });

    test('should update consent state when ad user data checkbox changes', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Complete initialization
        window.updateConsentState('initialConsents');

        // Clear dataLayer for cleaner test
        window.dataLayer = [];
        // Reset lastConsentUpdate to allow new event
        window.lastConsentUpdate = null;

        // Change the ad user data checkbox state
        mockAdUserDataCheckbox.checked = false;
        window.adUserDataConsent = false;

        // Trigger the updateConsentState function
        window.updateConsentState('saveConsents');

        // Find the Klaro Consent Update event in dataLayer
        const consentEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify the consentMode object has the updated ad_user_data value
        expect(consentEvent).toBeTruthy();
        expect(consentEvent.consentMode.ad_storage).toBe('granted');
        expect(consentEvent.consentMode.analytics_storage).toBe('granted');
        expect(consentEvent.consentMode.ad_user_data).toBe('denied');
        expect(consentEvent.consentMode.ad_personalization).toBe('granted');

        // Verify that gtag was NOT called for consent
        expect(window.gtag).not.toHaveBeenCalledWith('consent', 'update', expect.anything());
    });

    test('should update controls state when parent service is disabled', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Complete initialization
        window.updateConsentState('initialConsents');

        // Clear dataLayer for cleaner test
        window.dataLayer = [];

        // We need to modify the mock Klaro manager to simulate disabling the service
        mockKlaroManager.consents['google-ads'] = false;

        // Simulate disabling the parent service
        window.updateControlsState('google-ads', false);

        // Check if the controls container has the disabled class
        expect(mockControlsContainer.classList.contains('klaro-geo-controls-disabled')).toBe(true);

        // Check if the checkboxes are unchecked
        expect(mockAdPersonalizationCheckbox.checked).toBe(false);
        expect(mockAdUserDataCheckbox.checked).toBe(false);

        // Find the Klaro Consent Update event in dataLayer
        const consentEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify the consentMode object has the updated values
        expect(consentEvent).toBeTruthy();
        expect(consentEvent.consentMode.ad_storage).toBe('denied');
        expect(consentEvent.consentMode.analytics_storage).toBe('granted');
        expect(consentEvent.consentMode.ad_user_data).toBe('denied');
        expect(consentEvent.consentMode.ad_personalization).toBe('denied');
        expect(consentEvent.consentMode.google_ads_consent).toBe('denied');

        // Verify that gtag was NOT called for consent
        expect(window.gtag).not.toHaveBeenCalledWith('consent', 'update', expect.anything());
    });

    test('should update controls state when parent service is enabled', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Complete initialization
        window.updateConsentState('initialConsents');

        // First disable the controls and service
        mockKlaroManager.consents['google-ads'] = false;
        window.updateControlsState('google-ads', false);

        // Clear dataLayer for cleaner test
        window.dataLayer = [];

        // Then re-enable the parent service
        mockKlaroManager.consents['google-ads'] = true;
        window.updateControlsState('google-ads', true);

        // Check if the controls container does not have the disabled class
        expect(mockControlsContainer.classList.contains('klaro-geo-controls-disabled')).toBe(false);

        // Check if the checkboxes are checked
        expect(mockAdPersonalizationCheckbox.checked).toBe(true);
        expect(mockAdUserDataCheckbox.checked).toBe(true);

        // Find the Klaro Consent Update event in dataLayer
        const consentEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify the consentMode object has the updated values
        expect(consentEvent).toBeTruthy();
        expect(consentEvent.consentMode.ad_storage).toBe('granted');
        expect(consentEvent.consentMode.analytics_storage).toBe('granted');
        expect(consentEvent.consentMode.ad_user_data).toBe('granted');
        expect(consentEvent.consentMode.ad_personalization).toBe('granted');

        // Verify that gtag was NOT called for consent
        expect(window.gtag).not.toHaveBeenCalledWith('consent', 'update', expect.anything());
    });

    test('should handle both checkboxes being unchecked', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Complete initialization
        window.updateConsentState('initialConsents');

        // Clear dataLayer for cleaner test
        window.dataLayer = [];
        // Reset lastConsentUpdate to allow new event
        window.lastConsentUpdate = null;

        // Change both checkbox states
        mockAdPersonalizationCheckbox.checked = false;
        mockAdUserDataCheckbox.checked = false;
        window.adPersonalizationConsent = false;
        window.adUserDataConsent = false;

        // Trigger the updateConsentState function
        window.updateConsentState('saveConsents');

        // Find the Klaro Consent Update event in dataLayer
        const consentEvent = window.dataLayer.find(e => e.event === 'Klaro Consent Update');

        // Verify the consentMode object has the updated values
        expect(consentEvent).toBeTruthy();
        expect(consentEvent.consentMode.ad_storage).toBe('granted');
        expect(consentEvent.consentMode.analytics_storage).toBe('granted');
        expect(consentEvent.consentMode.ad_user_data).toBe('denied');
        expect(consentEvent.consentMode.ad_personalization).toBe('denied');

        // Verify that gtag was NOT called for consent
        expect(window.gtag).not.toHaveBeenCalledWith('consent', 'update', expect.anything());
    });

    test('should skip duplicate consent updates', function() {
        // Load the consent mode script
        loadConsentModeScript();

        // Complete initialization
        window.updateConsentState('initialConsents');

        // Count events before second call
        const eventCountBefore = window.dataLayer.filter(e => e.event === 'Klaro Consent Update').length;

        // Call updateConsentState again with the same values
        window.updateConsentState('saveConsents');

        // Count events after second call
        const eventCountAfter = window.dataLayer.filter(e => e.event === 'Klaro Consent Update').length;

        // Verify that no new event was pushed (duplicate update was skipped)
        expect(eventCountAfter).toBe(eventCountBefore);
    });

    test('should check for Klaro manager', function() {
        // Verify that our mock Klaro manager is set up correctly
        expect(window.klaro).toBeDefined();
        expect(typeof window.klaro.getManager).toBe('function');

        // Verify that the mock manager has the expected properties
        const manager = window.klaro.getManager();
        expect(manager).toBeDefined();
        expect(manager.consents).toBeDefined();
        expect(manager.consents['google-ads']).toBe(true);
        expect(typeof manager.watch).toBe('function');
        expect(typeof manager.getConsent).toBe('function');
    });
});

/**
 * Dynamic Consent Keys Tests
 * Tests for the new dynamic service consent keys feature
 */
describe('Dynamic Consent Keys', function() {
    // Helper function to generate consent key from service name
    function getServiceConsentKey(serviceName) {
        return serviceName.replace(/-/g, '_') + '_consent';
    }

    describe('getServiceConsentKey helper', function() {
        test('should convert service name with hyphens to consent key', function() {
            expect(getServiceConsentKey('google-analytics')).toBe('google_analytics_consent');
            expect(getServiceConsentKey('google-ads')).toBe('google_ads_consent');
            expect(getServiceConsentKey('contact-form-7')).toBe('contact_form_7_consent');
        });

        test('should handle service name without hyphens', function() {
            expect(getServiceConsentKey('piwik')).toBe('piwik_consent');
            expect(getServiceConsentKey('facebook')).toBe('facebook_consent');
        });

        test('should handle multiple hyphens', function() {
            expect(getServiceConsentKey('my-custom-service-name')).toBe('my_custom_service_name_consent');
        });
    });

    describe('dynamic keys generation', function() {
        // Mock gtag and manager for dynamic key tests
        const mockGtag = jest.fn();
        const mockManager = {
            consents: {
                'google-analytics': true,
                'piwik': true,
                'facebook': false,
                'google-ads': false
            }
        };

        beforeEach(function() {
            window.gtag = mockGtag;
            mockGtag.mockClear();
        });

        afterEach(function() {
            delete window.gtag;
        });

        test('should generate dynamic keys for all services', function() {
            // Simulate generating consent update with dynamic keys
            const reservedKeys = ['ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization'];
            const completeUpdate = {
                'ad_storage': 'denied',
                'analytics_storage': 'granted',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied'
            };

            // Add dynamic keys for all services
            Object.keys(mockManager.consents).forEach(function(serviceName) {
                const dynamicKey = getServiceConsentKey(serviceName);
                if (!reservedKeys.includes(dynamicKey.replace('_consent', ''))) {
                    completeUpdate[dynamicKey] = mockManager.consents[serviceName] ? 'granted' : 'denied';
                }
            });

            // Verify the update includes all expected keys
            expect(completeUpdate).toEqual({
                'ad_storage': 'denied',
                'analytics_storage': 'granted',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'google_analytics_consent': 'granted',
                'piwik_consent': 'granted',
                'facebook_consent': 'denied',
                'google_ads_consent': 'denied'
            });
        });

        test('should set denied services to denied (not omit them)', function() {
            const consents = {
                'google-analytics': true,
                'facebook': false
            };

            const consentModeUpdate = {};
            Object.keys(consents).forEach(function(serviceName) {
                const key = getServiceConsentKey(serviceName);
                consentModeUpdate[key] = consents[serviceName] ? 'granted' : 'denied';
            });

            // Both should be present, with correct values
            expect(consentModeUpdate['google_analytics_consent']).toBe('granted');
            expect(consentModeUpdate['facebook_consent']).toBe('denied');
        });

        test('should not conflict with reserved Google keys', function() {
            // Reserved keys should not be overwritten by dynamic keys
            const reservedKeys = ['ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization'];

            // Even if a service is named to produce a key like "ad_storage_consent",
            // it should still be added since it has the "_consent" suffix
            const serviceName = 'ad-storage';
            const dynamicKey = getServiceConsentKey(serviceName);

            // The dynamic key would be "ad_storage_consent" - not the same as "ad_storage"
            expect(dynamicKey).toBe('ad_storage_consent');
            expect(reservedKeys.includes(dynamicKey)).toBe(false);
        });
    });
});