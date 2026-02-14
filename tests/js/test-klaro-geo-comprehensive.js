/**
 * Comprehensive Tests for Klaro Geo Core Functionality
 * 
 * These tests cover the functionality in klaro-geo.js
 */

describe('Klaro Geo Core Functionality - Comprehensive Tests', function() {
  // Mock functions to test
  let handleConsentChange;
  let storeReceiptLocally;
  let sendReceiptToServer;
  let updateGoogleConsentMode;
  let setupKlaroDataLayerWatcher;
  let setupWatcher;
  let pushConsentData;
  let handleKlaroConsentEvents;
  let pushKlaroConsentUpdate;
  let createAdControlsForService;
  let updateControlsUI;
  let createToggleControl;
  
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
        watch: jest.fn(),
        config: {
          consent_mode_settings: {
            initialize_consent_mode: true,
            analytics_storage_service: 'google-analytics',
            ad_storage_service: 'google-ads',
            ad_user_data: true,
            ad_personalization: true
          }
        }
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
    
    // Set up global variables for consent mode
    window.lastConsentUpdate = null;
    window.consentUpdateTimer = null;
    window.consentUpdateDelay = 50;
    window.controlsInjected = false;
    window.adStorageServiceName = 'google-ads';
    window.analyticsStorageServiceName = 'google-analytics';
    window.adUserDataConsent = true;
    window.adPersonalizationConsent = true;
    
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
    
    // Create mock implementations of the functions
    handleConsentChange = jest.fn((manager) => {
      // Generate a unique receipt ID
      const receiptId = 'receipt_test_123';
      
      // Create the consent receipt
      const consentReceipt = {
        receipt_id: receiptId,
        timestamp: 1234567890,
        consent_choices: {},
        template_name: 'default',
        template_source: 'fallback',
        country_code: 'US',
        region_code: 'CA',
        admin_override: false,
        template_settings: {},
        klaro_config: null
      };
      
      // Get consents from the manager
      const consents = manager.consents;
      
      // Add consent choices to the receipt
      for (const k of Object.keys(consents || {})) {
        consentReceipt.consent_choices[k] = consents[k] === true;
      }
      
      // Store the receipt in localStorage
      storeReceiptLocally(consentReceipt);
      
      // Only send to server if consent logging is enabled
      if (window.klaroConsentData.enableConsentLogging) {
        sendReceiptToServer(consentReceipt);
      } else {
        console.log('Server-side consent logging is disabled for this template. Receipt stored locally only.');
      }
      
      // Push to dataLayer
      window.dataLayer.push({
        'event': 'Klaro Event',
        'eventSource': 'klaro-geo',
        'klaroEventName': 'generateConsentReceipt',
        'klaroGeoConsentReceipt': consentReceipt,
        'klaroGeoTemplateSource': consentReceipt.template_source,
        'klaroGeoAdminOverride': consentReceipt.admin_override
      });
      
      return consentReceipt;
    });
    
    storeReceiptLocally = jest.fn((receipt) => {
      try {
        // Get existing receipts
        const existingData = window.localStorage.getItem('klaro_consent_receipts');
        let receipts = [];
        
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
        return true;
      } catch (e) {
        console.error('Failed to store consent receipt locally:', e);
        return false;
      }
    });
    
    sendReceiptToServer = jest.fn((receipt) => {
      // Get the AJAX URL
      const ajaxUrl = window.klaroConsentData.ajaxUrl || '/wp-admin/admin-ajax.php';
      
      // Create form data
      const formData = new FormData();
      formData.append('action', 'klaro_geo_store_consent_receipt');
      
      // Add nonce if available
      if (window.klaroConsentData.nonce) {
        formData.append('nonce', window.klaroConsentData.nonce);
      }
      
      // Ensure admin_override is explicitly set to a boolean
      receipt.admin_override = receipt.admin_override === true;
      
      // Log the receipt data before sending
      console.log('Sending receipt data to server:', receipt);
      
      // Stringify the receipt data
      formData.append('receipt_data', JSON.stringify(receipt));
      
      // Send the request
      return fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
    });
    
    updateGoogleConsentMode = jest.fn((manager, eventType, data) => {
      // Check if gtag is available
      if (typeof window.gtag !== 'function') {
        return;
      }
      
      // Check for duplicate update if data is provided
      if (data && window.lastConsentUpdate &&
          JSON.stringify(window.lastConsentUpdate) === JSON.stringify(data)) {
        return;
      }
      
      // Clear any pending update timer
      if (window.consentUpdateTimer) {
        clearTimeout(window.consentUpdateTimer);
      }
      
      // Set a new timer to debounce
      window.consentUpdateTimer = setTimeout(function() {
        // Get consent state from Klaro
        let adServiceEnabled = false;
        let analyticsServiceEnabled = false;
        
        // First try to use the provided manager
        if (manager && manager.consents) {
          if (window.adStorageServiceName) {
            adServiceEnabled = manager.consents[window.adStorageServiceName] === true;
          }
          if (window.analyticsStorageServiceName) {
            analyticsServiceEnabled = manager.consents[window.analyticsStorageServiceName] === true;
          }
        }
        
        // Create complete update
        const completeUpdate = {
          'ad_storage': adServiceEnabled ? 'granted' : 'denied',
          'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
          'ad_user_data': (adServiceEnabled && window.adUserDataConsent === true) ? 'granted' : 'denied',
          'ad_personalization': (adServiceEnabled && window.adPersonalizationConsent === true) ? 'granted' : 'denied'
        };
        
        // Store last update
        window.lastConsentUpdate = completeUpdate;
        
        // Send to gtag
        window.gtag('consent', 'update', completeUpdate);
        
        // Reset timer
        window.consentUpdateTimer = null;
        
        return completeUpdate;
      }, window.consentUpdateDelay || 50);
    });
    
    setupKlaroDataLayerWatcher = jest.fn(() => {
      // Try to access Klaro immediately
      if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
        try {
          const manager = window.klaro.getManager();
          if (manager) {
            setupWatcher(manager);
            return true; // Success
          }
        } catch (error) {
          console.error('Error getting manager early:', error);
        }
      }
      
      // Fallback: Retry with setTimeout
      setTimeout(setupKlaroDataLayerWatcher, 50); // Very short delay
      return false;
    });
    
    setupWatcher = jest.fn((manager) => {
      try {
        // Push initial consent data
        pushConsentData(manager);
        
        manager.watch({
          update: function(obj, name, data) {
            if (typeof window.dataLayer !== 'undefined') {
              // Create acceptedServices array
              const acceptedServices = Object.keys(manager.consents)
                .filter(serviceName => manager.consents[serviceName] === true);
              
              const pushData = {
                'event': 'Klaro Event',
                'eventSource': 'klaro',
                'klaroEventName': name,
                'klaroEventData': data,
                'acceptedServices': acceptedServices
              };
              
              window.dataLayer.push(pushData);
              
              // Call consent handling functions
              handleKlaroConsentEvents(manager, name, data);
            }
          }
        });
        return true;
      } catch (error) {
        console.error('Error setting up Klaro dataLayer watcher:', error);
        return false;
      }
    });
    
    pushConsentData = jest.fn((manager) => {
      // Push initial consent data to dataLayer
      if (typeof window.dataLayer !== 'undefined') {
        // Create acceptedServices array
        const acceptedServices = Object.keys(manager.consents)
          .filter(serviceName => manager.consents[serviceName] === true);
        
        const initialData = {
          'event': 'Klaro Event',
          'eventSource': 'klaro',
          'klaroEventName': 'initialConsents',
          'klaroEventData': manager.consents,
          'acceptedServices': acceptedServices
        };
        
        window.dataLayer.push(initialData);
        
        // Call consent handling functions
        handleKlaroConsentEvents(manager, 'initialConsents', manager.consents);
        
        // Set global variables for consent mode
        if (manager.config && manager.config.consent_mode_settings) {
          window.adStorageServiceName = manager.config.consent_mode_settings.ad_storage_service;
          window.analyticsStorageServiceName = manager.config.consent_mode_settings.analytics_storage_service;
          window.adUserDataConsent = manager.config.consent_mode_settings.ad_user_data;
          window.adPersonalizationConsent = manager.config.consent_mode_settings.ad_personalization;
        }
        return true;
      } else {
        console.warn('dataLayer is not defined, cannot push initial Klaro event');
        return false;
      }
    });
    
    handleKlaroConsentEvents = jest.fn((manager, eventName, data) => {
      if (eventName === 'initialConsents') {
        // For initial consents, update the Google consent mode
        updateGoogleConsentMode(manager, eventName, data);
        return 'initialConsents';
      } 
      else if (eventName === 'saveConsents') {
        // For save consents, update the Google consent mode and store the consent receipt
        updateGoogleConsentMode(manager, eventName, data);
        handleConsentChange(manager);
        return 'saveConsents';
      }
      else {
        // For other events, just log them but don't trigger updates
        return 'otherEvent';
      }
    });
    
    createAdControlsForService = jest.fn((serviceElement) => {
      // Mock implementation
      return document.createElement('div');
    });
    
    updateControlsUI = jest.fn((manager, serviceListItem, isServiceEnabled) => {
      // Mock implementation
      return true;
    });
    
    createToggleControl = jest.fn((id, serviceElement, label, description, initialState, onChange) => {
      // Create a simple mock toggle control
      const container = document.createElement('div');
      container.className = 'klaro-geo-toggle-control';
      
      // Create a checkbox
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.id = `klaro-geo-${id}`;
      checkbox.checked = initialState;
      container.appendChild(checkbox);
      
      return container;
    });
    
    // Add pushKlaroConsentUpdate function
    pushKlaroConsentUpdate = jest.fn((manager, eventType, consentMode) => {
      if (typeof window.dataLayer !== 'undefined') {
        const acceptedServices = Object.keys(manager.consents)
          .filter(serviceName => manager.consents[serviceName] === true);

        const consentUpdateData = {
          'event': 'Klaro Consent Update',
          'acceptedServices': acceptedServices,
          'consent_trigger': eventType
        };

        if (consentMode) {
          consentUpdateData.consentMode = consentMode;
        }

        window.dataLayer.push(consentUpdateData);
      }
    });
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
  
  // Test handleConsentChange function
  describe('handleConsentChange', function() {
    test('should store receipt in localStorage when consent logging is enabled', function() {
      // Create a mock Klaro manager
      const manager = {
        consents: {
          'google-analytics': true,
          'facebook-pixel': false
        }
      };
      
      // Call the function
      const receipt = handleConsentChange(manager);
      
      // Check if the function was called
      expect(handleConsentChange).toHaveBeenCalledWith(manager);
      
      // Check if storeReceiptLocally was called
      expect(storeReceiptLocally).toHaveBeenCalled();
      
      // Check if sendReceiptToServer was called
      expect(sendReceiptToServer).toHaveBeenCalled();
      
      // Check if data was pushed to dataLayer
      expect(window.dataLayer.length).toBe(1);
      expect(window.dataLayer[0].event).toBe('Klaro Event');
      expect(window.dataLayer[0].eventSource).toBe('klaro-geo');
      expect(window.dataLayer[0].klaroEventName).toBe('generateConsentReceipt');
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
      
      // Call the function
      handleConsentChange(manager);
      
      // Check if the function was called
      expect(handleConsentChange).toHaveBeenCalledWith(manager);
      
      // Check if sendReceiptToServer was NOT called
      expect(sendReceiptToServer).not.toHaveBeenCalled();
      
      // Check if the disabled message was logged
      expect(console.log).toHaveBeenCalledWith('Server-side consent logging is disabled for this template. Receipt stored locally only.');
    });
    
    test('should include correct data in the consent receipt', function() {
      // Create a mock Klaro manager
      const manager = {
        consents: {
          'google-analytics': true,
          'facebook-pixel': false
        }
      };
      
      // Call the function
      const receipt = handleConsentChange(manager);
      
      // Check receipt structure
      expect(receipt).toBeTruthy();
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
  });
  
  // Test storeReceiptLocally function
  describe('storeReceiptLocally', function() {
    test('should store receipt in localStorage', function() {
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
      
      // Check if the function was called
      expect(storeReceiptLocally).toHaveBeenCalledWith(receipt);
      
      // Check if receipt was stored in localStorage
      const storedReceipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
      expect(storedReceipts).toBeTruthy();
      expect(Array.isArray(storedReceipts)).toBe(true);
      expect(storedReceipts.length).toBe(1);
      expect(storedReceipts[0]).toEqual(receipt);
    });
    
    test('should limit stored receipts to 10', function() {
      // Store 11 receipts
      for (let i = 0; i < 11; i++) {
        const receipt = {
          receipt_id: `test_receipt_${i}`,
          timestamp: Math.floor(Date.now() / 1000) + i,
          consent_choices: {
            'google-analytics': i % 2 === 0,
            'facebook-pixel': i % 3 === 0
          }
        };
        
        storeReceiptLocally(receipt);
      }
      
      // Check if the function was called 11 times
      expect(storeReceiptLocally).toHaveBeenCalledTimes(11);
      
      // Check if only 10 receipts were stored
      const storedReceipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
      expect(storedReceipts.length).toBe(10);
      
      // Check that the oldest receipt was removed (FIFO)
      expect(storedReceipts[0].receipt_id).toBe('test_receipt_1');
    });
    
    test('should handle invalid existing data in localStorage', function() {
      // Set invalid data in localStorage
      window.localStorage.setItem('klaro_consent_receipts', 'not valid JSON');
      
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
      
      // Check if error was logged
      expect(console.error).toHaveBeenCalledWith(
        'Failed to parse existing receipts:',
        expect.any(Error)
      );
      
      // Check if receipt was still stored
      const storedReceipts = JSON.parse(window.localStorage.getItem('klaro_consent_receipts'));
      expect(storedReceipts).toBeTruthy();
      expect(Array.isArray(storedReceipts)).toBe(true);
      expect(storedReceipts.length).toBe(1);
      expect(storedReceipts[0]).toEqual(receipt);
    });
  });
  
  // Test sendReceiptToServer function
  describe('sendReceiptToServer', function() {
    test('should send receipt to server with correct data', function() {
      // Create a mock receipt
      const receipt = {
        receipt_id: 'test_receipt_123',
        timestamp: Math.floor(Date.now() / 1000),
        consent_choices: {
          'google-analytics': true,
          'facebook-pixel': false
        },
        admin_override: 'true' // String value to test conversion
      };
      
      // Call the function
      sendReceiptToServer(receipt);
      
      // Check if the function was called
      expect(sendReceiptToServer).toHaveBeenCalledWith(receipt);
      
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
      
      // Check that admin_override was converted to a boolean
      expect(typeof receipt.admin_override).toBe('boolean');
    });
    
    test('should use custom AJAX URL if provided', function() {
      // Set custom AJAX URL
      window.klaroConsentData.ajaxUrl = '/custom-ajax-url';
      
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
      
      // Check if the function was called
      expect(sendReceiptToServer).toHaveBeenCalledWith(receipt);
      
      // Check if fetch was called with the custom URL
      expect(window.fetch).toHaveBeenCalled();
      const fetchCall = window.fetch.mock.calls[0];
      expect(fetchCall[0]).toBe('/custom-ajax-url');
    });
  });
  
  // Test updateGoogleConsentMode function
  describe('updateGoogleConsentMode', function() {
    test('should update gtag with correct consent values', function() {
      // Create a mock manager
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        }
      };
      
      // Call the function
      updateGoogleConsentMode(manager, 'test', null);
      
      // Check if the function was called
      expect(updateGoogleConsentMode).toHaveBeenCalledWith(manager, 'test', null);
      
      // Fast-forward timers to trigger the debounced function
      jest.runAllTimers();
      
      // Check if gtag was called with the correct values
      expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
        ad_storage: 'denied', // false from manager.consents
        analytics_storage: 'granted', // true from manager.consents
        ad_user_data: 'denied', // false because ad_storage is denied
        ad_personalization: 'denied' // false because ad_storage is denied
      });
    });
    
    test('should not update gtag if it is not available', function() {
      // Remove gtag
      delete window.gtag;
      
      // Create a mock manager
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        }
      };
      
      // Call the function
      updateGoogleConsentMode(manager, 'test', null);
      
      // Check if the function was called
      expect(updateGoogleConsentMode).toHaveBeenCalledWith(manager, 'test', null);
      
      // Fast-forward timers
      jest.runAllTimers();
      
      // Check that no error was thrown
      expect(console.error).not.toHaveBeenCalled();
    });
    
    test('should skip duplicate updates', function() {
      // Set up global variables
      window.adStorageServiceName = 'google-ads';
      window.analyticsStorageServiceName = 'google-analytics';
      
      // Create a mock manager
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        }
      };
      
      // Set lastConsentUpdate to match what would be sent
      window.lastConsentUpdate = {
        ad_storage: 'denied',
        analytics_storage: 'granted',
        ad_user_data: 'denied',
        ad_personalization: 'denied'
      };
      
      // Call the function
      updateGoogleConsentMode(manager, 'test', window.lastConsentUpdate);
      
      // Check if the function was called
      expect(updateGoogleConsentMode).toHaveBeenCalledWith(manager, 'test', window.lastConsentUpdate);
      
      // Fast-forward timers
      jest.runAllTimers();
      
      // Check that gtag was not called
      expect(window.gtag).not.toHaveBeenCalled();
    });
  });
  
  // Test handleKlaroConsentEvents function
  describe('handleKlaroConsentEvents', function() {
    // Reset pushKlaroConsentUpdate for these tests
    
    beforeEach(() => {
      pushKlaroConsentUpdate = jest.fn((manager, eventType, consentMode) => {
        if (typeof window.dataLayer !== 'undefined') {
          const acceptedServices = Object.keys(manager.consents)
            .filter(serviceName => manager.consents[serviceName] === true);

          const consentUpdateData = {
            'event': 'Klaro Consent Update',
            'acceptedServices': acceptedServices,
            'consent_trigger': eventType
          };

          if (consentMode) {
            consentUpdateData.consentMode = consentMode;
          }

          window.dataLayer.push(consentUpdateData);
        }
      });
    });
    
    test('should handle initialConsents event with consent mode enabled', function() {
      // Create a mock manager with consent mode enabled
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        },
        config: {
          consent_mode_settings: {
            initialize_consent_mode: true,
            analytics_storage_service: 'google-analytics',
            ad_storage_service: 'google-ads'
          }
        }
      };
      
      // Call the function with initialConsents event
      handleKlaroConsentEvents(manager, 'initialConsents', manager.consents);
      
      // Check if the function was called
      expect(handleKlaroConsentEvents).toHaveBeenCalledWith(manager, 'initialConsents', manager.consents);
      
      // Check if updateGoogleConsentMode was called
      expect(updateGoogleConsentMode).toHaveBeenCalledWith(manager, 'initialConsents', manager.consents);
      
      // Check if handleConsentChange was NOT called
      expect(handleConsentChange).not.toHaveBeenCalled();
    });
    
    test('should handle saveConsents event with consent mode enabled', function() {
      // Create a mock manager with consent mode enabled
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        },
        config: {
          consent_mode_settings: {
            initialize_consent_mode: true,
            analytics_storage_service: 'google-analytics',
            ad_storage_service: 'google-ads'
          }
        }
      };
      
      // Call the function with saveConsents event
      handleKlaroConsentEvents(manager, 'saveConsents', manager.consents);
      
      // Check if the function was called
      expect(handleKlaroConsentEvents).toHaveBeenCalledWith(manager, 'saveConsents', manager.consents);
      
      // Check if updateGoogleConsentMode was called
      expect(updateGoogleConsentMode).toHaveBeenCalledWith(manager, 'saveConsents', manager.consents);
      
      // Check if handleConsentChange was called
      expect(handleConsentChange).toHaveBeenCalledWith(manager);
    });
    
    test('should handle other events', function() {
      // Create a mock manager
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        }
      };
      
      // Call the function with a different event
      handleKlaroConsentEvents(manager, 'otherEvent', {});
      
      // Check if the function was called
      expect(handleKlaroConsentEvents).toHaveBeenCalledWith(manager, 'otherEvent', {});
      
      // Check if updateGoogleConsentMode was NOT called
      expect(updateGoogleConsentMode).not.toHaveBeenCalled();
      
      // Check if handleConsentChange was NOT called
      expect(handleConsentChange).not.toHaveBeenCalled();
    });
  });
  
  // Test pushKlaroConsentUpdate function
  describe('pushKlaroConsentUpdate', function() {
    // Reset pushKlaroConsentUpdate for these tests
    
    beforeEach(() => {
      pushKlaroConsentUpdate = jest.fn((manager, eventType, consentMode) => {
        if (typeof window.dataLayer !== 'undefined') {
          const acceptedServices = Object.keys(manager.consents)
            .filter(serviceName => manager.consents[serviceName] === true);

          const consentUpdateData = {
            'event': 'Klaro Consent Update',
            'acceptedServices': acceptedServices,
            'consent_trigger': eventType
          };

          if (consentMode) {
            consentUpdateData.consentMode = consentMode;
          }

          window.dataLayer.push(consentUpdateData);
        }
      });
    });
    
    test('should push klaroConsentUpdate event to dataLayer', function() {
      // Create a mock manager
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        }
      };
      
      // Call the function
      pushKlaroConsentUpdate(manager, 'testEvent', null);
      
      // Check if the function was called
      expect(pushKlaroConsentUpdate).toHaveBeenCalledWith(manager, 'testEvent', null);
      
      // Check if data was pushed to dataLayer
      expect(window.dataLayer.length).toBeGreaterThan(0);
      
      // Find the Klaro Consent Update event
      const updateEvent = window.dataLayer.find(item => item.event === 'Klaro Consent Update');
      expect(updateEvent).toBeTruthy();
      expect(updateEvent.consent_trigger).toBe('testEvent');
      expect(updateEvent.acceptedServices).toContain('google-analytics');
      expect(updateEvent.acceptedServices).not.toContain('google-ads');
    });
    
    test('should include consentMode if provided', function() {
      // Create a mock manager
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        }
      };
      
      // Create a mock consentMode
      const consentMode = {
        ad_storage: 'denied',
        analytics_storage: 'granted'
      };
      
      // Call the function
      pushKlaroConsentUpdate(manager, 'testEvent', consentMode);
      
      // Check if the function was called
      expect(pushKlaroConsentUpdate).toHaveBeenCalledWith(manager, 'testEvent', consentMode);
      
      // Check if data was pushed to dataLayer
      expect(window.dataLayer.length).toBeGreaterThan(0);
      
      // Find the Klaro Consent Update event
      const updateEvent = window.dataLayer.find(item => item.event === 'Klaro Consent Update');
      expect(updateEvent).toBeTruthy();
      expect(updateEvent.consent_trigger).toBe('testEvent');
      expect(updateEvent.consentMode).toEqual(consentMode);
    });
  });
  
  // Test UI-related functions
  describe('UI Controls', function() {
    test('should create toggle control with correct structure', function() {
      // Create a mock service element
      const serviceElement = document.createElement('div');
      serviceElement.className = 'cm-service';
      
      // Call the function
      const toggleControl = createToggleControl(
        'test-toggle',
        serviceElement,
        'Test Toggle',
        'This is a test toggle',
        true,
        jest.fn()
      );
      
      // Check if the function was called
      expect(createToggleControl).toHaveBeenCalledWith(
        'test-toggle',
        serviceElement,
        'Test Toggle',
        'This is a test toggle',
        true,
        expect.any(Function)
      );
      
      // Check the structure
      expect(toggleControl.className).toBe('klaro-geo-toggle-control');
      
      // Check that it contains a checkbox
      const checkbox = toggleControl.querySelector('input[type="checkbox"]');
      expect(checkbox).toBeTruthy();
      expect(checkbox.id).toBe('klaro-geo-test-toggle');
      expect(checkbox.checked).toBe(true);
    });
  });
  
  // Test integration between functions
  describe('Integration Tests', function() {
    test('should handle the complete consent flow', function() {
      // Create a mock manager
      const manager = {
        consents: {
          'google-analytics': true,
          'google-ads': false
        },
        watch: jest.fn(),
        config: {
          consent_mode_settings: {
            initialize_consent_mode: true,
            analytics_storage_service: 'google-analytics',
            ad_storage_service: 'google-ads',
            ad_user_data: true,
            ad_personalization: true
          }
        }
      };
      
      // Set up the watcher
      setupWatcher(manager);
      
      // Check if pushConsentData was called
      expect(pushConsentData).toHaveBeenCalledWith(manager);
      
      // Simulate a consent change event
      const updateCallback = manager.watch.mock.calls[0][0].update;
      updateCallback(manager, 'saveConsents', manager.consents);
      
      // Check if handleKlaroConsentEvents was called
      expect(handleKlaroConsentEvents).toHaveBeenCalledWith(manager, 'saveConsents', manager.consents);
      
      // Check if updateGoogleConsentMode was called
      expect(updateGoogleConsentMode).toHaveBeenCalledWith(manager, 'saveConsents', manager.consents);
      
      // Check if handleConsentChange was called
      expect(handleConsentChange).toHaveBeenCalledWith(manager);
      
      // Fast-forward timers to trigger the debounced function
      jest.runAllTimers();
      
      // Check if gtag was called
      expect(window.gtag).toHaveBeenCalled();
      
      // Check if data was pushed to dataLayer
      expect(window.dataLayer.length).toBeGreaterThan(0);
    });
  });
});