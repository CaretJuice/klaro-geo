(function() {
  klaroGeoLog('DEBUG: klaro-geo.js loaded (early capture attempt)');

  /**
   * Generate a consent key from a service name
   * @param {string} serviceName - The service name (e.g., "google-analytics")
   * @returns {string} - The consent key (e.g., "google_analytics_consent")
   */
  function getServiceConsentKey(serviceName) {
      return serviceName.replace(/-/g, '_') + '_consent';
  }

  // Global variables for consent mode
  window.lastConsentUpdate = null;
  window.consentUpdateTimer = null;
  window.consentUpdateDelay = 50; // Default delay
  window.controlsInjected = false;
  window.adStorageServiceName = null;
  window.analyticsStorageServiceName = null;
  window.adUserDataConsent = null; // null = not yet initialized, will be set from storage or parent service
  window.adPersonalizationConsent = null; // null = not yet initialized
  window.currentKlaroOpts = null;
  window.lastWatcherConsentTimestamp = null;

  // LocalStorage key for ad consent sub-settings
  const AD_CONSENT_STORAGE_KEY = 'klaro_geo_ad_consent_settings';

  /**
   * Save ad consent sub-settings to localStorage
   */
  function saveAdConsentSettings() {
      try {
          const settings = {
              ad_user_data: window.adUserDataConsent,
              ad_personalization: window.adPersonalizationConsent,
              timestamp: Date.now()
          };
          localStorage.setItem(AD_CONSENT_STORAGE_KEY, JSON.stringify(settings));
          klaroGeoLog('DEBUG: Saved ad consent settings to localStorage:', settings);
      } catch (e) {
          console.error('Failed to save ad consent settings:', e);
      }
  }

  /**
   * Load ad consent sub-settings from localStorage
   * @returns {Object|null} The saved settings or null if not found
   */
  function loadAdConsentSettings() {
      try {
          const data = localStorage.getItem(AD_CONSENT_STORAGE_KEY);
          if (data) {
              const settings = JSON.parse(data);
              klaroGeoLog('DEBUG: Loaded ad consent settings from localStorage:', settings);
              return settings;
          }
      } catch (e) {
          console.error('Failed to load ad consent settings:', e);
      }
      return null;
  }

  /**
   * Clear ad consent sub-settings from localStorage (e.g., when user declines all)
   */
  function clearAdConsentSettings() {
      try {
          localStorage.removeItem(AD_CONSENT_STORAGE_KEY);
          klaroGeoLog('DEBUG: Cleared ad consent settings from localStorage');
      } catch (e) {
          console.error('Failed to clear ad consent settings:', e);
      }
  }

  // Make sure window.dataLayer exists
  if (typeof window.dataLayer === 'undefined') {
      window.dataLayer = [];
  }

  // =============================================================================
  // CONSENT QUEUE
  // Queues dataLayer events until consent state is confirmed
  // =============================================================================

  // Initialize klaroGeo namespace (preserve any stub queue that was set up earlier)
  // CRITICAL: Capture the existing queue BEFORE doing anything else
  const preExistingKlaroGeo = window.klaroGeo;
  const preExistingQueue = preExistingKlaroGeo ? preExistingKlaroGeo.queue : undefined;

  klaroGeoLog('DEBUG: klaro-geo.js init - preExistingKlaroGeo:',
      preExistingKlaroGeo ? 'exists' : 'undefined',
      'preExistingQueue:', preExistingQueue ? preExistingQueue.length + ' events' : 'undefined');

  if (preExistingQueue && preExistingQueue.length > 0) {
      klaroGeoLog('DEBUG: Pre-existing queue contents:', JSON.stringify(preExistingQueue.map(e => e.event || 'no-event-name')));
  }

  window.klaroGeo = window.klaroGeo || {};
  const existingQueue = window.klaroGeo.queue || [];

  // Queue configuration
  const QUEUE_MAX_SIZE = 100;

  // Queue state
  window.klaroGeo.queue = existingQueue; // Preserve events from stub
  window.klaroGeo.consentConfirmed = false;

  klaroGeoLog('DEBUG: Queue initialized - existingQueue length:', existingQueue.length);

  // Note: The queue's job is TIMING (wait for consent state to be SET), not consent enforcement.
  // GTM's consent mode handles whether tags actually fire based on consent state.
  // We flush when the consent event fires, regardless of what consent was given.

  // Determine GTM mode from klaroConsentData (set by PHP) or klaroConfig services
  // GTM mode is enabled if:
  // 1. klaro_geo_gtm_id is set (standalone GTM), OR
  // 2. google-tag-manager service exists in klaroConfig (GTM as a Klaro service)
  if (typeof window.klaroGeo.useGTM === 'undefined') {
      // Check for standalone GTM ID first
      const hasGtmId = typeof window.klaroConsentData !== 'undefined' &&
                        window.klaroConsentData.gtmId &&
                        window.klaroConsentData.gtmId !== '';

      // Check if google-tag-manager is a configured Klaro service
      const hasGtmService = typeof window.klaroConfig !== 'undefined' &&
                            Array.isArray(window.klaroConfig.services) &&
                            window.klaroConfig.services.some(function(s) {
                                return s.name === 'google-tag-manager';
                            });

      window.klaroGeo.useGTM = hasGtmId || hasGtmService;
      klaroGeoLog('DEBUG: GTM mode detection - hasGtmId=' + hasGtmId + ', hasGtmService=' + hasGtmService + ', useGTM=' + window.klaroGeo.useGTM);
  }

  /**
   * Push an event to the consent queue or directly to dataLayer
   * @param {Object} eventData - The dataLayer event object
   */
  window.klaroGeo.push = function(eventData) {
      klaroGeoLog('DEBUG: klaroGeo.push called with:', eventData.event || 'no-event-name',
                  'consentConfirmed:', window.klaroGeo.consentConfirmed);

      if (window.klaroGeo.consentConfirmed) {
          // Consent event already fired, push directly to dataLayer
          klaroGeoLog('DEBUG: Consent Queue - pushing directly to dataLayer:', eventData.event || 'unknown event');
          window.dataLayer.push(eventData);
      } else {
          // Consent not yet confirmed, queue the event
          if (window.klaroGeo.queue.length >= QUEUE_MAX_SIZE) {
              // Drop oldest event
              const dropped = window.klaroGeo.queue.shift();
              console.warn('[klaro-geo] Consent queue limit reached (100). Dropping oldest event:', dropped.event || 'unknown');
          }
          klaroGeoLog('DEBUG: Consent Queue - queuing event:', eventData.event || 'unknown event',
                      '(queue size:', window.klaroGeo.queue.length + 1, ')');
          window.klaroGeo.queue.push(eventData);
      }
  };

  klaroGeoLog('DEBUG: klaroGeo.push function installed, replacing any stub');

  /**
   * Flush all queued events to dataLayer
   */
  function flushConsentQueue() {
      if (window.klaroGeo.queue.length === 0) {
          klaroGeoLog('DEBUG: Consent Queue - nothing to flush');
          return;
      }

      klaroGeoLog('DEBUG: Consent Queue - flushing', window.klaroGeo.queue.length, 'events to dataLayer');

      while (window.klaroGeo.queue.length > 0) {
          const event = window.klaroGeo.queue.shift();
          klaroGeoLog('DEBUG: Consent Queue - pushing event:', event.event || 'no-event-name', event);
          window.dataLayer.push(event);
      }

      klaroGeoLog('DEBUG: Consent Queue - flush complete');
  }

  /**
   * Handle consent event - flush queue
   * @param {string} eventName - The consent event name that triggered this
   */
  function onConsentEvent(eventName) {
      if (window.klaroGeo.consentConfirmed) {
          klaroGeoLog('DEBUG: Consent Queue - consent already confirmed, ignoring:', eventName);
          return;
      }

      klaroGeoLog('DEBUG: Consent Queue - consent event received:', eventName);
      window.klaroGeo.consentConfirmed = true;
      flushConsentQueue();
  }

  // Set up listener for consent events by intercepting dataLayer.push
  (function setupConsentQueueListener() {
      const originalPush = window.dataLayer.push;

      window.dataLayer.push = function() {
          // Call the original push first
          const result = originalPush.apply(window.dataLayer, arguments);

          // Check if this is a consent event we care about
          const eventData = arguments[0];
          if (eventData && typeof eventData === 'object') {
              // Check for event name - could be at top level or wrapped in 'value' object
              // GTM template sometimes wraps events in a 'value' property
              let eventName = eventData.event;
              if (!eventName && eventData.value && typeof eventData.value === 'object') {
                  eventName = eventData.value.event;
              }

              // Determine which event to listen for based on GTM mode
              const triggerEvent = window.klaroGeo.useGTM ? 'Klaro Consent Update' : 'Klaro Consent Data';

              if (eventName === triggerEvent) {
                  klaroGeoLog('DEBUG: Consent Queue - detected consent event:', eventName);
                  onConsentEvent(eventName);
              }
          }

          return result;
      };

      klaroGeoLog('DEBUG: Consent Queue - listener initialized, waiting for:',
                  window.klaroGeo.useGTM ? 'Klaro Consent Update' : 'Klaro Consent Data');
  })();

  // Process any events that were queued before this script loaded (stub pattern)
  if (existingQueue.length > 0) {
      klaroGeoLog('DEBUG: Consent Queue - found', existingQueue.length, 'pre-queued events from stub');
  }

  // Set up a watcher for late-arriving stub events (in case inline scripts run after klaro-geo.js)
  // This handles the case where wp_footer priority ordering causes the stub to run after us
  const originalQueue = window.klaroGeo.queue;
  Object.defineProperty(window.klaroGeo, 'queue', {
      get: function() { return originalQueue; },
      set: function(newQueue) {
          // If someone tries to set queue to a new array with items, merge them
          if (Array.isArray(newQueue) && newQueue.length > 0) {
              klaroGeoLog('DEBUG: Consent Queue - detected late stub queue assignment with', newQueue.length, 'events');
              newQueue.forEach(function(event) {
                  if (window.klaroGeo.consentConfirmed) {
                      klaroGeoLog('DEBUG: Consent Queue - late event pushed directly to dataLayer:', event.event || 'no-event-name');
                      window.dataLayer.push(event);
                  } else {
                      klaroGeoLog('DEBUG: Consent Queue - late event queued:', event.event || 'no-event-name');
                      originalQueue.push(event);
                  }
              });
          }
      },
      configurable: true
  });

  // =============================================================================
  // END CONSENT QUEUE
  // =============================================================================

  // Function to handle consent changes (moved from PHP)
  function handleConsentChange(manager) {
      // Check if consent logging is enabled from the dataLayer first (country-specific setting)
      let enableConsentLogging = true; // Default to true
      
      // Try to find the enableConsentLogging value from the dataLayer
      if (window.dataLayer && Array.isArray(window.dataLayer)) {
          // Look for the most recent klaroConfigLoaded event
          for (let i = window.dataLayer.length - 1; i >= 0; i--) {
              const item = window.dataLayer[i];
              if (item && 
                  item.event === 'Klaro Event' && 
                  item.klaroEventName === 'klaroConfigLoaded' &&
                  typeof item.klaroGeoEnableConsentLogging !== 'undefined') {
                  enableConsentLogging = item.klaroGeoEnableConsentLogging === true;
                  klaroGeoLog('Found enableConsentLogging in dataLayer:', enableConsentLogging);
                  break;
              }
          }
      }
      
      // Fall back to the global setting if not found in dataLayer
      if (window.klaroConsentData && 
          (window.klaroConsentData.enableConsentLogging === "0" ||
           window.klaroConsentData.enableConsentLogging === false ||
           window.klaroConsentData.enableConsentLogging === undefined)) {
          enableConsentLogging = false;
      }
      
      // If consent logging is disabled, store locally only and return
      if (!enableConsentLogging) {
          klaroGeoLog('Consent logging is disabled for this template/country. Receipt stored locally only.');
          return;
      }

      // Generate a unique receipt ID
      var receiptId = 'receipt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

      // Create the consent receipt
      var consentReceipt = {
          receipt_id: receiptId,
          timestamp: Math.floor(Date.now() / 1000), // Unix timestamp
          consent_choices: {},
          template_name: window.klaroConsentData.templateName || 'default',
          template_source: window.klaroConsentData.templateSource || 'fallback',
          country_code: window.klaroConsentData.detectedCountry || null,
          region_code: window.klaroConsentData.detectedRegion || null,
          admin_override: window.klaroConsentData.adminOverride === true,
          template_settings: window.klaroConsentData.templateSettings || {},
          klaro_config: window.klaroConfig || null
      };

      // Get consents from the manager
      var consents = manager.consents;

      // Add consent choices to the receipt
      for (var k of Object.keys(consents || {})) {
          consentReceipt.consent_choices[k] = consents[k] === true;
      }

      // Store the receipt in localStorage
      storeReceiptLocally(consentReceipt);
      
      // Try to find the enableConsentLogging value from the dataLayer
      if (window.dataLayer && Array.isArray(window.dataLayer)) {
          // Look for the most recent klaroConfigLoaded event
          for (let i = window.dataLayer.length - 1; i >= 0; i--) {
              const item = window.dataLayer[i];
              if (item && 
                  item.event === 'Klaro Event' && 
                  item.klaroEventName === 'klaroConfigLoaded' &&
                  typeof item.klaroGeoEnableConsentLogging !== 'undefined') {
                  enableConsentLogging = item.klaroGeoEnableConsentLogging === true;
                  klaroGeoLog('Found enableConsentLogging in dataLayer for server send:', enableConsentLogging);
                  break;
              }
          }
      }
      
      // Fall back to the global setting if not found in dataLayer
      if (!enableConsentLogging && typeof window.klaroConsentData !== 'undefined' &&
          typeof window.klaroConsentData.enableConsentLogging !== 'undefined') {
          enableConsentLogging = window.klaroConsentData.enableConsentLogging !== "0" &&
                                window.klaroConsentData.enableConsentLogging !== false;
      }

      // Only send to server if consent logging is enabled
      if (enableConsentLogging) {
          sendReceiptToServer(consentReceipt)
              .catch(function(error) {
                  console.error('Error from server when sending receipt:', error);
              });
      } else {
          klaroGeoLog('Server-side consent logging is disabled for this template/country. Receipt stored locally only.');
      }

      // Push to dataLayer using event factory if available
      let receiptEvent;
      if (typeof window.KlaroGeoEvents !== 'undefined') {
          receiptEvent = window.KlaroGeoEvents.createKlaroGeoEvent('generateConsentReceipt', {
              'klaroGeoConsentReceipt': consentReceipt.receipt_id,
              'klaroGeoTemplateSource': consentReceipt.template_source,
              'klaroGeoAdminOverride': consentReceipt.admin_override,
              'klaroGeoEnableConsentLogging': enableConsentLogging
          });
      } else {
          receiptEvent = {
              'event': 'Klaro Event',
              'eventSource': 'klaro-geo',
              'klaroEventName': 'generateConsentReceipt',
              'klaroGeoConsentReceipt': consentReceipt.receipt_id,
              'klaroGeoTemplateSource': consentReceipt.template_source,
              'klaroGeoAdminOverride': consentReceipt.admin_override,
              'klaroGeoEnableConsentLogging': enableConsentLogging
          };
      }
      window.dataLayer.push(receiptEvent);
}

/**
 * Store the consent receipt in localStorage
 */
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

/**
 * Get the latest consent receipt from localStorage
 * @returns {Object|null} The latest consent receipt or null if none exists
 */
function getLatestConsentReceipt() {
    try {
        // Get existing receipts
        var existingData = window.localStorage.getItem('klaro_consent_receipts');
        
        if (existingData) {
            try {
                var receipts = JSON.parse(existingData);
                
                // Ensure receipts is an array
                if (Array.isArray(receipts) && receipts.length > 0) {
                    // Return the most recent receipt (last in the array)
                    return receipts[receipts.length - 1];
                }
            } catch (parseError) {
                console.error('Failed to parse existing receipts:', parseError);
            }
        }
        
        // Return null if no receipts exist or there was an error
        return null;
    } catch (e) {
        console.error('Failed to retrieve latest consent receipt:', e);
        return null;
    }
}

/**
 * Send the consent receipt to the server
 */
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

    // Ensure admin_override is explicitly set to a boolean
    receipt.admin_override = receipt.admin_override === true;

    // Log the receipt data before sending
    klaroGeoLog('Sending receipt data to server:', receipt);

    // Stringify the receipt data
    formData.append('receipt_data', JSON.stringify(receipt));

    // Send the request
    return fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            // Handle non-OK responses
            if (typeof response.text === 'function') {
                return response.text().then(function(text) {
                    throw new Error('Server returned status ' + response.status + ': ' +
                        (text ? text.substring(0, 100) : 'No response text'));
                });
            } else {
                throw new Error('Server returned status ' + response.status);
            }
        }
        return response.json();
    })
    .then(function(data) {
        if (!data.success) {
            console.error('Failed to store consent receipt:', data);
        }
        return data;
    })
    .catch(function(error) {
        // In test environments, don't log errors
        if (typeof process === 'undefined' || process.env.NODE_ENV !== 'test') {
            console.error('Error sending consent receipt:', error);
        }
        // Don't throw the error further to prevent unhandled promise rejection
        return { success: false, error: error.message };
    });
}

function setupKlaroDataLayerWatcher() {
  // Try to access Klaro immediately
  if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
    try {
      const manager = window.klaro.getManager();
      if (manager) {
        klaroGeoLog('DEBUG: Klaro manager available early, setting up watcher');
        setupWatcher(manager);
        return; // Success, don't retry
      } else {
        klaroGeoWarn('DEBUG: Klaro manager is undefined, even though klaro is defined.');
      }
    } catch (error) {
      console.error('DEBUG: Error getting manager early:', error);
    }
  }

  // Fallback: Retry with setTimeout
  klaroGeoLog('DEBUG: Klaro not fully ready, retrying setupKlaroDataLayerWatcher');
  setTimeout(setupKlaroDataLayerWatcher, 50); // Very short delay
}

function setupWatcher(manager) {
  try {
      // Push initial consent data
      pushConsentData(manager);

      // Check if we should suppress intermediate consents events (default: true)
      const suppressConsentsEvents = window.klaroConsentData &&
          typeof window.klaroConsentData.suppressConsentsEvents !== 'undefined'
              ? window.klaroConsentData.suppressConsentsEvents
              : true;

      manager.watch({
          update: function(obj, name, data) {
              if (typeof window.dataLayer !== 'undefined') {
                  // Events that should always be forwarded to the dataLayer
                  const alwaysForward = ['saveConsents', 'applyConsents'];

                  // If suppressConsentsEvents is enabled, skip intermediate 'consents' events
                  // These fire once per service as Klaro updates each service individually.
                  // We only want to forward 'saveConsents' which fires once after all services are updated.
                  if (suppressConsentsEvents && !alwaysForward.includes(name)) {
                      klaroGeoLog('DEBUG: Suppressing intermediate Klaro event:', name);
                      // For initialConsents, pushConsentData already handles it, so skip here to avoid duplicates
                      if (name === 'initialConsents') {
                          klaroGeoLog('DEBUG: Skipping watcher handling of initialConsents (already handled by pushConsentData)');
                          return;
                      }
                      // Still call consent handling for internal processing, but don't push to dataLayer
                      handleKlaroConsentEvents(manager, name, data);
                      return;
                  }

                  // Use KlaroGeoEvents factory if available, otherwise fallback to inline
                  let pushData;
                  if (typeof window.KlaroGeoEvents !== 'undefined') {
                      pushData = window.KlaroGeoEvents.createKlaroForwardedEvent(name, data, obj.config);
                  } else {
                      // Fallback for when events module hasn't loaded yet
                      pushData = {
                          'event': 'Klaro Event',
                          'eventSource': 'klaro',
                          'klaroEventName': name,
                          'klaroEventData': data,
                          'klaroConfig': obj.config
                      };
                      // Add acceptedServices for saveConsents events
                      if (name === 'saveConsents') {
                          const acceptedServices = Object.keys(manager.consents)
                              .filter(serviceName => manager.consents[serviceName] === true);
                          pushData.acceptedServices = acceptedServices;
                      }
                  }

                  klaroGeoLog('DEBUG: Pushing Klaro event to dataLayer:', pushData);
                  window.dataLayer.push(pushData);

                  // Call consent handling functions
                  handleKlaroConsentEvents(manager, name, data);
              } else {
                  klaroGeoWarn('DEBUG: dataLayer is not defined, cannot push Klaro event');
              }
          }
      });
      klaroGeoLog('DEBUG: Generic Klaro dataLayer watcher setup complete');

  } catch (error) {
      console.error('DEBUG: Error setting up Klaro dataLayer watcher:', error);
  }
}

function pushConsentData(manager) {
  // Push initial consent data to dataLayer
  if (typeof window.dataLayer !== 'undefined') {
      // Defer to next event loop tick to let Klaro fully hydrate consent from cookies
      // Without this delay, manager.consents may be empty even for returning users
      setTimeout(function() {
          // Get fresh manager to ensure we have the latest consent state
          let activeManager = manager;
          try {
              if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
                  const freshManager = window.klaro.getManager();
                  if (freshManager && freshManager.consents && Object.keys(freshManager.consents).length > 0) {
                      activeManager = freshManager;
                  }
              }
          } catch (e) {
              klaroGeoLog('DEBUG: Error getting fresh manager in pushConsentData');
          }

          // Create acceptedServices array from fresh consent state
          const acceptedServices = Object.keys(activeManager.consents || {})
              .filter(serviceName => activeManager.consents[serviceName] === true);

          // Deep copy consents to capture current state (not a reference that changes later)
          const consentsCopy = JSON.parse(JSON.stringify(activeManager.consents || {}));

          // Use KlaroGeoEvents factory if available - this ensures correct eventSource ('klaro')
          // initialConsents represents Klaro manager state, so it should use 'klaro' eventSource
          let initialData;
          if (typeof window.KlaroGeoEvents !== 'undefined') {
              initialData = window.KlaroGeoEvents.createKlaroForwardedEvent(
                  'initialConsents',
                  consentsCopy,
                  activeManager.config
              );
          } else {
              // Fallback - note: eventSource is now 'klaro' (not 'klaro-geo')
              initialData = {
                  'event': 'Klaro Event',
                  'eventSource': 'klaro',
                  'klaroEventName': 'initialConsents',
                  'klaroEventData': consentsCopy,
                  'acceptedServices': acceptedServices,
                  'klaroConfig': activeManager.config
              };
          }

          klaroGeoLog('DEBUG: Pushing initial Klaro event to dataLayer:', initialData);
          window.dataLayer.push(initialData);

          // Set global variables for consent mode
          if (activeManager.config && activeManager.config.consent_mode_settings) {
              window.adStorageServiceName = activeManager.config.consent_mode_settings.ad_storage_service;
              window.analyticsStorageServiceName = activeManager.config.consent_mode_settings.analytics_storage_service;

              // Check if ad_storage service is consented
              const adServiceConsented = window.adStorageServiceName &&
                  activeManager.consents &&
                  activeManager.consents[window.adStorageServiceName] === true;

              // Try to load saved ad consent sub-settings from localStorage
              const savedSettings = loadAdConsentSettings();

              if (savedSettings && adServiceConsented) {
                  // Use saved settings if available AND ad_storage is still consented
                  window.adUserDataConsent = savedSettings.ad_user_data === true;
                  window.adPersonalizationConsent = savedSettings.ad_personalization === true;
                  klaroGeoLog('DEBUG: Restored ad consent settings from localStorage');
              } else if (adServiceConsented) {
                  // No saved settings but ad_storage is consented - default to parent service state
                  window.adUserDataConsent = true;
                  window.adPersonalizationConsent = true;
                  klaroGeoLog('DEBUG: No saved settings, defaulting to parent service state (granted)');
              } else {
                  // ad_storage is not consented - clear any saved settings and set to false
                  window.adUserDataConsent = false;
                  window.adPersonalizationConsent = false;
                  clearAdConsentSettings();
                  klaroGeoLog('DEBUG: ad_storage not consented, setting sub-controls to denied');
              }

              klaroGeoLog('DEBUG: Initialized consent mode globals - adStorageService:', window.adStorageServiceName,
                  'adServiceConsented:', adServiceConsented,
                  'adUserDataConsent:', window.adUserDataConsent,
                  'adPersonalizationConsent:', window.adPersonalizationConsent);
          }

          // Call consent handling functions
          handleKlaroConsentEvents(activeManager, 'initialConsents', activeManager.consents);
      }, 0); // Defer to next tick
  } else {
      klaroGeoWarn('DEBUG: dataLayer is not defined, cannot push initial Klaro event');
  }
}

// Consolidated function to handle consent events
// NOTE: Consent mode is ALWAYS enabled - no toggle check needed
function handleKlaroConsentEvents(manager, eventName, data) {
  klaroGeoLog('DEBUG: Handling Klaro consent event:', eventName);

  if (eventName === 'initialConsents') {
      // For initial consents, update the Google consent mode
      klaroGeoLog('DEBUG: Initial consents detected, updating Google consent mode');
      updateGoogleConsentMode(manager, eventName, data);
  }
  else if (eventName === 'saveConsents') {
      // For save consents, update the Google consent mode and store the consent receipt
      klaroGeoLog('DEBUG: Save consents detected, updating Google consent mode and storing receipt');
      updateGoogleConsentMode(manager, eventName, data);

      // Save ad consent sub-settings to localStorage
      saveAdConsentSettings();

      // Always store the consent receipt
      handleConsentChange(manager);
  }
  else {
      // For other events, just log them but don't trigger updates
      klaroGeoLog('DEBUG: Other Klaro event detected:', eventName, '- not triggering consent update');
  }
}

// Function to handle consent updates and trigger dataLayer events
// NOTE: Consent mode is ALWAYS enabled - dynamic service keys are generated for all services
function updateGoogleConsentMode(manager, eventType, data) {
  klaroGeoLog('DEBUG: Google consent mode update triggered by:', eventType,
    'with data:', data ? (typeof data === 'object' ? 'consent object' : data) : 'no data');
  klaroGeoLog('DEBUG: updateGoogleConsentMode ENTRY - eventType=' + eventType + ', timestamp=' + Date.now());

  // Check if gtag is available
  if (typeof window.gtag !== 'function') {
      klaroGeoLog('DEBUG: gtag not available, skipping consent update');
      return;
  }

  // Check for duplicate update if data is provided
  if (data && window.lastConsentUpdate &&
      JSON.stringify(window.lastConsentUpdate) === JSON.stringify(data)) {
      klaroGeoLog('DEBUG: Skipping duplicate consent update');
      return;
  }

  // Clear any pending update timer
  if (window.consentUpdateTimer) {
      klaroGeoLog('DEBUG: Clearing pending consent update timer');
      clearTimeout(window.consentUpdateTimer);
  }

  // Set a new timer to debounce
  klaroGeoLog('DEBUG: Setting debounced consent update timer');
  window.consentUpdateTimer = setTimeout(function() {

      // Get consent state from Klaro
      let adServiceEnabled = false;
      let analyticsServiceEnabled = false;
      let activeManager = manager; // Track the manager we'll use for consent state

      // ALWAYS get a fresh manager to ensure we have the latest consent state
      // This is critical because Klaro may not have fully hydrated consent from cookies
      // when the initial call was made (the passed manager may have stale/empty consents)
      try {
          if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
              const freshManager = window.klaro.getManager();
              if (freshManager && freshManager.consents && Object.keys(freshManager.consents).length > 0) {
                  klaroGeoLog('DEBUG: Using fresh Klaro manager for consent state');
                  activeManager = freshManager;
              }
          }
      } catch (e) {
          klaroGeoLog('DEBUG: Error getting fresh manager, using provided manager');
      }

      // Now read consent state from activeManager
      if (activeManager && activeManager.consents) {
          klaroGeoLog('DEBUG: Reading consent state from manager');
          if (window.adStorageServiceName) {
              adServiceEnabled = activeManager.consents[window.adStorageServiceName] === true;
          }
          if (window.analyticsStorageServiceName) {
              analyticsServiceEnabled = activeManager.consents[window.analyticsStorageServiceName] === true;
          }
      } else {
          // Fallback: try to use data if provided
          klaroGeoLog('DEBUG: No manager consents available, using fallback');
          if (data) {
              adServiceEnabled = data.ad_storage === 'granted';
              analyticsServiceEnabled = data.analytics_storage === 'granted';
          }
      }

      // Log the current state of the sub-controls
      klaroGeoLog('DEBUG: Current sub-control states - adUserDataConsent:', window.adUserDataConsent, 'adPersonalizationConsent:', window.adPersonalizationConsent);

      // Create complete update with standard Google Consent Mode keys
      const completeUpdate = {
          'ad_storage': adServiceEnabled ? 'granted' : 'denied',
          'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
          'ad_user_data': (adServiceEnabled && window.adUserDataConsent === true) ? 'granted' : 'denied',
          'ad_personalization': (adServiceEnabled && window.adPersonalizationConsent === true) ? 'granted' : 'denied'
      };

      // Reserved keys that dynamic service keys should not overwrite
      const reservedKeys = ['ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization'];

      // Add dynamic service consent keys for ALL services
      // Use activeManager which may have been updated if the original manager was empty
      console.log('[klaro-geo] activeManager exists:', !!activeManager, 'has consents:', !!(activeManager && activeManager.consents));
      if (activeManager && activeManager.consents) {
          const consentKeys = Object.keys(activeManager.consents);
          console.log('[klaro-geo] Processing', consentKeys.length, 'services for dynamic consent keys:', consentKeys);
          consentKeys.forEach(function(serviceName) {
              const dynamicKey = getServiceConsentKey(serviceName);
              // Only add if not conflicting with reserved keys
              if (!reservedKeys.includes(dynamicKey.replace('_consent', ''))) {
                  const isGranted = activeManager.consents[serviceName] === true;
                  completeUpdate[dynamicKey] = isGranted ? 'granted' : 'denied';
                  console.log('[klaro-geo] Added dynamic key:', dynamicKey, '=', completeUpdate[dynamicKey]);
              } else {
                  console.log('[klaro-geo] Skipped reserved key collision:', dynamicKey);
              }
          });
      } else {
          console.log('[klaro-geo] No activeManager.consents available - dynamic keys will not be added');
          console.log('[klaro-geo] activeManager:', activeManager);
      }

      // Log the complete update being sent (now includes dynamic keys)
      klaroGeoLog('DEBUG: Consent update object (with dynamic service keys):', completeUpdate);

      // Store last update
      window.lastConsentUpdate = completeUpdate;

      // Update UI controls
      if (activeManager && activeManager.services) {
          const adService = activeManager.services.find(service => service.name === window.adStorageServiceName);
          if (adService) {
              const serviceElement = document.getElementById('service-item-' + window.adStorageServiceName);
              updateControlsUI(activeManager, serviceElement, adServiceEnabled);
          }
      }

      // Push "Klaro Consent Data" event to dataLayer
      // The Klaro Geo GTM template listens for this event and:
      // 1. Calls updateConsentState() with the consent data
      // 2. Pushes "Klaro Consent Update" event AFTER consent is set
      // Use "Klaro Consent Update" as the trigger for GA4/other tags (consent is guaranteed to be set)
      if (typeof window.dataLayer !== 'undefined' && activeManager && activeManager.consents) {
          // Create acceptedServices array for the update event
          const acceptedServices = Object.keys(activeManager.consents)
              .filter(serviceName => activeManager.consents[serviceName] === true);

          // In GTM mode, for initialConsents, only push if GTM service is consented
          // This prevents stale events from being queued before GTM loads
          // (GTM now requires consent, so initialConsents before consent would create stale events)
          const isGtmMode = window.klaroGeo && window.klaroGeo.useGTM;
          const gtmConsented = activeManager.consents['google-tag-manager'] === true;

          // Debug logging for skip condition
          klaroGeoLog('DEBUG: Klaro Consent Data skip check:',
              'eventType=' + eventType,
              'isGtmMode=' + isGtmMode,
              'gtmConsented=' + gtmConsented,
              'window.klaroGeo.useGTM=' + (window.klaroGeo ? window.klaroGeo.useGTM : 'undefined'),
              'consents=' + JSON.stringify(activeManager.consents || {}));

          if (eventType === 'initialConsents' && isGtmMode && !gtmConsented) {
              klaroGeoLog('DEBUG: Skipping Klaro Consent Data push for initialConsents - GTM not yet consented');
              // Don't push stale consent data before GTM loads
              // saveConsents will push the correct data after user consents
          } else {
              klaroGeoLog('DEBUG: Pushing Klaro Consent Data event to dataLayer');
              // Use KlaroGeoEvents factory if available for fresh consent state
              let consentDataEvent;
              if (typeof window.KlaroGeoEvents !== 'undefined') {
                  consentDataEvent = window.KlaroGeoEvents.createConsentDataEvent(eventType);
              } else {
                  consentDataEvent = {
                      'event': 'Klaro Consent Data',
                      'eventSource': 'klaro-geo',
                      'consentMode': completeUpdate,
                      'acceptedServices': acceptedServices,
                      'triggerEvent': eventType
                  };
              }
              window.dataLayer.push(consentDataEvent);
          }
      }

      // Reset timer
      window.consentUpdateTimer = null;

  }, window.consentUpdateDelay || 50); // Default delay
}


  // Function to update just the UI controls without triggering a consent update
  // This function is used to update the UI controls based on the service state
  function updateControlsUI(manager, serviceListItem, isServiceEnabled) {
    // Get the current state of the checkboxes
    let adPersonalizationCheckbox = null;
    let adUserDataCheckbox = null;
    let controlsContainer = null;

    // Try to find controls anywhere in the document
    controlsContainer = document.querySelector('.klaro-geo-ad-controls');

    if (controlsContainer) {
        klaroGeoLog('DEBUG: Found controls container in document');

        adPersonalizationCheckbox = controlsContainer.querySelector('#klaro-geo-ad-personalization');
        adUserDataCheckbox = controlsContainer.querySelector('#klaro-geo-ad-user-data');

        if (adPersonalizationCheckbox && adUserDataCheckbox) {
            klaroGeoLog('DEBUG: Found control checkboxes');
        }
    }

    // If still not found, try to find the checkboxes directly
    if (!adPersonalizationCheckbox) {
        adPersonalizationCheckbox = document.querySelector('#klaro-geo-ad-personalization');
        if (adPersonalizationCheckbox) {
            klaroGeoLog('DEBUG: Found ad personalization checkbox directly');
        }
    }

    if (!adUserDataCheckbox) {
        adUserDataCheckbox = document.querySelector('#klaro-geo-ad-user-data');
        if (adUserDataCheckbox) {
            klaroGeoLog('DEBUG: Found ad user data checkbox directly');
        }
    }

    if (controlsContainer) {
        if (isServiceEnabled) {
            controlsContainer.classList.remove('klaro-geo-controls-disabled');
            klaroGeoLog('DEBUG: Removed klaro-geo-controls-disabled class');
        } else {
            controlsContainer.classList.add('klaro-geo-controls-disabled');
            klaroGeoLog('DEBUG: Added klaro-geo-controls-disabled class');
        }
    }

    if (adPersonalizationCheckbox) {
        const oldState = adPersonalizationCheckbox.checked;
        
        // Always sync the checkbox state with the parent service
        adPersonalizationCheckbox.checked = isServiceEnabled;
        window.adPersonalizationConsent = isServiceEnabled;
        
        // Always update the disabled state
        adPersonalizationCheckbox.disabled = !isServiceEnabled;
        
        klaroGeoLog('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked, 
            'disabled:', adPersonalizationCheckbox.disabled, 'global consent:', window.adPersonalizationConsent);
    }

    if (adUserDataCheckbox) {
        const oldState = adUserDataCheckbox.checked;
        
        // Always sync the checkbox state with the parent service
        adUserDataCheckbox.checked = isServiceEnabled;
        window.adUserDataConsent = isServiceEnabled;
        
        // Always update the disabled state
        adUserDataCheckbox.disabled = !isServiceEnabled;
        
        klaroGeoLog('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked, 
            'disabled:', adUserDataCheckbox.disabled, 'global consent:', window.adUserDataConsent);
    }
}

// Function to inject controls for a service element (used by the createServiceListItems override)
// Function to create ad controls for a service element
function createAdControlsForService(serviceElement) {
    klaroGeoLog('DEBUG: Creating ad controls for service element');

    if (!serviceElement) {
        klaroGeoLog('DEBUG: Service element is null, cannot create controls');
        return;
    }

    // Find the parent li.cm-service element
    const serviceListItem = serviceElement.closest('li.cm-service');
    if (!serviceListItem) {
        klaroGeoLog('DEBUG: Could not find parent li.cm-service element');
        return;
    }

    // Check if we already have controls for this service
    const existingControls = document.querySelector('.klaro-geo-ad-controls');
    if (existingControls) {
        klaroGeoLog('DEBUG: Controls already exist somewhere in the document');
        return;
    }

    // Get the current state of the service
    const serviceCheckbox = serviceListItem.querySelector('input[type="checkbox"]');
    const isServiceEnabled = serviceCheckbox ? serviceCheckbox.checked : false;

    // Store references to the checkboxes for easier access
    let adPersonalizationCheckbox = null;
    let adUserDataCheckbox = null;

    // Create the controls container
    const controlsContainer = document.createElement('div');
    controlsContainer.className = 'klaro-geo-ad-controls';

    // Add or remove the disabled class based on the service state
    if (!isServiceEnabled) {
        controlsContainer.classList.add('klaro-geo-controls-disabled');
    }

    // Create the heading
    const heading = document.createElement('div');
    heading.className = 'klaro-geo-heading';
    heading.textContent = 'Google Consent Mode Settings';
    controlsContainer.appendChild(heading);

    // Create the description
    const description = document.createElement('div');
    description.className = 'klaro-geo-description';
    description.textContent = 'These settings control how your data is used by Google for ads.';
    controlsContainer.appendChild(description);

    // Create the ad personalization control
    const adPersonalizationControl = createToggleControl(
      'ad-personalization', 
      serviceElement,
      'Ad Personalization',
      'Allow Google to show you personalized ads',
      isServiceEnabled, // Always match the parent service state
      function(isChecked, event) {
          // Only process if the parent service is enabled
          if (!serviceCheckbox || serviceCheckbox.checked) {
              window.adPersonalizationConsent = isChecked;
              
              // Prevent event propagation to avoid Klaro's event handling
              event.stopPropagation();
              event.preventDefault();
              
              // Log the change but don't trigger a consent update
              // The update will be triggered when the user saves the changes
              klaroGeoLog('DEBUG: Ad personalization consent updated to:', isChecked, 
                  '- waiting for save to trigger consent update');
          } else {
              // If parent is disabled, prevent toggling and reset to off state
              klaroGeoLog('DEBUG: Preventing toggle of ad personalization when parent is disabled');
              event.stopPropagation();
              event.preventDefault();
              return false;
          }
      }
    );
    
    // Add the control to the container
    controlsContainer.appendChild(adPersonalizationControl);


      // Create the ad user data control
      const adUserDataControl = createToggleControl(
        'ad-user-data', 
        serviceElement,
        'Ad User Data',
        'Allow Google to use your data for ad measurement',
        isServiceEnabled, // Always match the parent service state
        function(isChecked, event) {
            // Only process if the parent service is enabled
            if (!serviceCheckbox || serviceCheckbox.checked) {
                window.adUserDataConsent = isChecked;
                
                // Prevent event propagation to avoid Klaro's event handling
                event.stopPropagation();
                event.preventDefault();
                
                // Log the change but don't trigger a consent update
                // The update will be triggered when the user saves the changes
                klaroGeoLog('DEBUG: Ad user data consent updated to:', isChecked, 
                    '- waiting for save to trigger consent update');
            } else {
                // If parent is disabled, prevent toggling and reset to off state
                klaroGeoLog('DEBUG: Preventing toggle of ad user data when parent is disabled');
                event.stopPropagation();
                event.preventDefault();
                return false;
            }
        }
    );
    
    // Add the control to the container
    controlsContainer.appendChild(adUserDataControl);

    // Store references to the checkboxes for easier access
    adPersonalizationCheckbox = adPersonalizationControl.querySelector('input[type="checkbox"]');
    adUserDataCheckbox = adUserDataControl.querySelector('input[type="checkbox"]');
    
    // Set the state for both checkboxes
    if (adPersonalizationCheckbox) {
        // Set the checkbox state and disabled state
        adPersonalizationCheckbox.checked = isServiceEnabled;
        adPersonalizationCheckbox.disabled = !isServiceEnabled;
        
        // Update the global variable
        window.adPersonalizationConsent = isServiceEnabled;
        
        klaroGeoLog('DEBUG: Set ad personalization checkbox state:', isServiceEnabled, 
            'disabled:', !isServiceEnabled, 'global consent:', window.adPersonalizationConsent);
    }
    
    if (adUserDataCheckbox) {
        // Set the checkbox state and disabled state
        adUserDataCheckbox.checked = isServiceEnabled;
        adUserDataCheckbox.disabled = !isServiceEnabled;
        
        // Update the global variable
        window.adUserDataConsent = isServiceEnabled;
        
        klaroGeoLog('DEBUG: Set ad user data checkbox state:', isServiceEnabled, 
            'disabled:', !isServiceEnabled, 'global consent:', window.adUserDataConsent);
    }

    // Add event listener to the parent checkbox to control child checkboxes
    serviceCheckbox.addEventListener('change', function() {
        const isParentChecked = this.checked;
        klaroGeoLog('DEBUG: Parent checkbox changed, new state:', isParentChecked);

        // Update child checkboxes and their disabled state
        if (adPersonalizationCheckbox) {
            // Always sync the checkbox state with the parent
            adPersonalizationCheckbox.checked = isParentChecked;
            window.adPersonalizationConsent = isParentChecked;
            
            // Always update disabled state
            adPersonalizationCheckbox.disabled = !isParentChecked;
            klaroGeoLog('DEBUG: Parent change - updated ad personalization checkbox to:', adPersonalizationCheckbox.checked, 
                'and consent to:', window.adPersonalizationConsent);
        }
        
        if (adUserDataCheckbox) {
            // Always sync the checkbox state with the parent
            adUserDataCheckbox.checked = isParentChecked;
            window.adUserDataConsent = isParentChecked;
            
            // Always update disabled state
            adUserDataCheckbox.disabled = !isParentChecked;
            klaroGeoLog('DEBUG: Parent change - updated ad user data checkbox to:', adUserDataCheckbox.checked, 
                'and consent to:', window.adUserDataConsent);
        }

        // Log the change but don't trigger a consent update
        // The update will be triggered when the user saves the changes
        klaroGeoLog('DEBUG: Parent checkbox changed, waiting for save to trigger consent update');
    });
    
    // Find the purpose toggle that might control this service
    // First, find the parent purpose element
    const purposeElement = serviceListItem.closest('.cm-purpose');
    if (purposeElement) {
        klaroGeoLog('DEBUG: Found parent purpose element for service');
        
        // Function to update sub-controls based on service state
        const updateSubControlsFromPurposeToggle = function() {
            // Use setTimeout to let Klaro process the toggle first
            setTimeout(function() {
                // Check the current state of the service checkbox after Klaro has processed the purpose toggle
                const isServiceChecked = serviceCheckbox.checked;
                klaroGeoLog('DEBUG: Purpose toggle clicked, service checkbox state after Klaro processing:', isServiceChecked);
                
                // Update child checkboxes and their disabled state
                if (adPersonalizationCheckbox) {
                    // Always sync the checkbox state with the parent service
                    adPersonalizationCheckbox.checked = isServiceChecked;
                    window.adPersonalizationConsent = isServiceChecked;
                    
                    // Always update disabled state
                    adPersonalizationCheckbox.disabled = !isServiceChecked;
                    klaroGeoLog('DEBUG: Purpose toggle - updated ad personalization checkbox to:', adPersonalizationCheckbox.checked, 
                        'and consent to:', window.adPersonalizationConsent);
                }
                
                if (adUserDataCheckbox) {
                    // Always sync the checkbox state with the parent service
                    adUserDataCheckbox.checked = isServiceChecked;
                    window.adUserDataConsent = isServiceChecked;
                    
                    // Always update disabled state
                    adUserDataCheckbox.disabled = !isServiceChecked;
                    klaroGeoLog('DEBUG: Purpose toggle - updated ad user data checkbox to:', adUserDataCheckbox.checked, 
                        'and consent to:', window.adUserDataConsent);
                }
                
                // Log the change but don't trigger a consent update
                // The update will be triggered when the user saves the changes
                klaroGeoLog('DEBUG: Purpose toggle changed service state, waiting for save to trigger consent update');
            }, 50); // Small delay to let Klaro process the toggle first
        };
        
        // Find the purpose toggle checkbox
        const purposeToggle = purposeElement.querySelector('input[type="checkbox"]');
        if (purposeToggle) {
            klaroGeoLog('DEBUG: Found purpose toggle checkbox');
            
            // Add a click event listener to the purpose toggle checkbox
            purposeToggle.addEventListener('click', updateSubControlsFromPurposeToggle);
            klaroGeoLog('DEBUG: Added click event listener to purpose toggle checkbox');
            
            // Also add listeners to the label and slider which users might click instead
            const purposeLabel = purposeElement.querySelector('label.cm-list-label');
            if (purposeLabel) {
                purposeLabel.addEventListener('click', updateSubControlsFromPurposeToggle);
                klaroGeoLog('DEBUG: Added click event listener to purpose toggle label');
            }
            
            const purposeSlider = purposeElement.querySelector('.cm-switch .slider');
            if (purposeSlider) {
                purposeSlider.addEventListener('click', updateSubControlsFromPurposeToggle);
                klaroGeoLog('DEBUG: Added click event listener to purpose toggle slider');
            }
        }
        
        // Also set up a MutationObserver to watch for changes to the service slider's active class
        // This will catch changes that happen when the purpose toggle is clicked
        const serviceSlider = serviceListItem.querySelector('.cm-switch .slider');
        if (serviceSlider) {
            const sliderObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        // Check if the active class was added or removed
                        const isActive = serviceSlider.classList.contains('active');
                        klaroGeoLog('DEBUG: Service slider class changed, active:', isActive);
                        
                        // Update the sub-controls based on the slider's active state
                        if (adPersonalizationCheckbox) {
                            adPersonalizationCheckbox.checked = isActive;
                            window.adPersonalizationConsent = isActive;
                            adPersonalizationCheckbox.disabled = !isActive;
                            klaroGeoLog('DEBUG: Slider class change - updated ad personalization checkbox to:', isActive);
                        }
                        
                        if (adUserDataCheckbox) {
                            adUserDataCheckbox.checked = isActive;
                            window.adUserDataConsent = isActive;
                            adUserDataCheckbox.disabled = !isActive;
                            klaroGeoLog('DEBUG: Slider class change - updated ad user data checkbox to:', isActive);
                        }
                    }
                });
            });
            
            // Start observing the slider for class changes
            sliderObserver.observe(serviceSlider, { attributes: true });
            klaroGeoLog('DEBUG: Set up observer for service slider class changes');
        }
    }

    serviceListItem.parentNode.insertBefore(controlsContainer, serviceListItem.nextSibling);
}

// Function to create a toggle control
function createToggleControl(id, serviceElement, label, description, initialState, onChange) {
    klaroGeoLog(`DEBUG: Creating toggle control for ${id} with initial state ${initialState}`);
    klaroGeoLog('DEBUG: serviceElement:', serviceElement);

    // Create the container
    const container = document.createElement('div');
    container.className = 'klaro-geo-toggle-control';

    // Create a flex container for the label and toggle
    const flexContainer = document.createElement('div');
    flexContainer.className = 'klaro-geo-flex-container';
    container.appendChild(flexContainer);

    // Create the label
    const labelElement = document.createElement('label');
    labelElement.htmlFor = `klaro-geo-${id}`;
    labelElement.textContent = label;
    flexContainer.appendChild(labelElement);

    // Create the toggle switch container
    const toggleContainer = document.createElement('div');
    toggleContainer.className = 'klaro-geo-toggle-switch';
    flexContainer.appendChild(toggleContainer);

    // Create the checkbox
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = `klaro-geo-${id}`;
    checkbox.checked = initialState;
    toggleContainer.appendChild(checkbox);

    // Add the slider (knob is added via CSS ::after)
    const slider = document.createElement('span');
    slider.className = 'slider';
    toggleContainer.appendChild(slider);

    // Create the description
    const descriptionElement = document.createElement('p');
    descriptionElement.textContent = description;
    container.appendChild(descriptionElement);

    // Add event listener to the checkbox
    checkbox.addEventListener('change', function(event) {
        // Stop propagation to prevent Klaro from capturing the event
        event.stopPropagation();

        klaroGeoLog(`DEBUG: ${id} checkbox changed to:`, this.checked);

        if (onChange) {
            onChange(this.checked, event);
        }
    });

    // Add click handler to the slider to toggle the checkbox
    slider.addEventListener('click', function(event) {
        // Stop propagation to prevent Klaro from capturing the event
        event.stopPropagation();

        // Only toggle if the checkbox is not disabled
        if (!checkbox.disabled) {
            // Toggle the checkbox
            checkbox.checked = !checkbox.checked;

            // Trigger the change event
            const changeEvent = new Event('change');
            checkbox.dispatchEvent(changeEvent);
        } else {
            klaroGeoLog('DEBUG: Ignoring click on disabled slider');
        }
    });

    // Add click handler to the label to toggle the checkbox
    labelElement.addEventListener('click', function(event) {
        // Stop propagation to prevent Klaro from capturing the event
        event.stopPropagation();

        // Only toggle if the checkbox is not disabled
        if (!checkbox.disabled) {
            // Toggle the checkbox
            checkbox.checked = !checkbox.checked;

            // Trigger the change event
            const changeEvent = new Event('change');
            checkbox.dispatchEvent(changeEvent);
        } else {
            klaroGeoLog('DEBUG: Ignoring click on disabled label');
        }
    });

    // Add click handler to the container to prevent event propagation
    container.addEventListener('click', function(event) {
        event.stopPropagation();
        
        // If the checkbox is disabled, prevent any interaction
        if (checkbox.disabled) {
            event.preventDefault();
            klaroGeoLog('DEBUG: Preventing interaction with disabled control container');
        }
    });

    return container;
}

   // Function to inject controls for a service element (used by the createServiceListItems override)
    function injectAdControlsForService(serviceElement) {
        klaroGeoLog('DEBUG: injectAdControlsForService called for element:', serviceElement);
        
        // Check if controls are already injected
        if (controlsInjected) {
            // Double-check if controls actually exist in the DOM
            const existingControls = document.querySelector('.klaro-geo-consent-mode-controls');
            if (existingControls) {
                klaroGeoLog('DEBUG: Controls already injected and found in DOM, skipping injection');
                return;
            } else {
                klaroGeoLog('DEBUG: Controls marked as injected but not found in DOM, will try to inject again');
                controlsInjected = false;
            }
        }
        
        // Use the global ad storage service name
        if (!adStorageServiceName || adStorageServiceName === 'no_service') {
            klaroGeoLog('DEBUG: No ad storage service configured, skipping injection');
            return;
        }
        
        // Make sure we have a valid service element
        if (!serviceElement || !serviceElement.classList.contains('cm-service')) {
            klaroGeoLog('DEBUG: Invalid service element, skipping injection');
            return;
        }
        
        // Find the service ID from the element
        const serviceId = serviceElement.querySelector('input[type="checkbox"]')?.id;
        if (!serviceId) {
            klaroGeoLog('DEBUG: Could not find service ID, trying to inject directly');
            
            // Try to inject directly if we can't find the ID
            createAdControlsForService(serviceElement);
            return;
        }
        
        // Extract the service name from the ID (usually in format 'service-item-NAME')
        const serviceIdParts = serviceId.split('-');
        const serviceName = serviceIdParts.length > 2 ? serviceIdParts.slice(2).join('-') : null;
        
        if (!serviceName) {
            klaroGeoLog('DEBUG: Could not extract service name from ID, trying to inject directly');
            
            // Try to inject directly if we can't extract the service name
            createAdControlsForService(serviceElement);
            return;
        }
        
        // Create the controls directly
        createAdControlsForService(serviceElement);
    }

    function setupModalObserver() {
        klaroGeoLog('DEBUG: setupModalObserver called');
        
        // Find the Klaro container
        const klaroContainer = document.getElementById('klaro');
        if (!klaroContainer) {
            klaroGeoLog('DEBUG: Klaro container not found, will try again later');
            setTimeout(setupModalObserver, 500);
            return;
        }
        
        klaroGeoLog('DEBUG: Found Klaro container, setting up observer');
        
        // Set up the observer
        let modalVisible = false;
        const observer = new MutationObserver(function(mutations) {
            // Check if the modal is now visible
            const modal = document.querySelector('#klaro .cookie-modal');
            if (modal && !modalVisible) {
                modalVisible = true;
                klaroGeoLog('DEBUG: Modal open detected by MutationObserver');
                
                // Also call our handler directly
                handleModalOpen();
                klaroGeoLog('DEBUG: Calling handleModalOpen in MutationObserver');
            } else if (!modal && modalVisible) {
                modalVisible = false;
                klaroGeoLog('DEBUG: Modal close detected by MutationObserver');
            }
        });
        
        // Start observing
        observer.observe(klaroContainer, {
            childList: true,
            subtree: true,
            attributes: true,
            characterData: true
        });
        
        klaroGeoLog('DEBUG: Modal observer setup complete');
    }

    function handleModalOpen() {
        klaroGeoLog('DEBUG: handleModalOpen called');
        
        // Set the editing flag to true when the modal opens
        isEditingInModal = true;
        needsConsentUpdate = false;
        klaroGeoLog('DEBUG: Set isEditingInModal to true');
        
        // Check if the modal is actually visible
        const klaroModal = document.querySelector('.klaro .cookie-modal');
        klaroGeoLog('DEBUG: Klaro modal found in DOM:', klaroModal ? 'yes' : 'no');
        
        if (!klaroModal) {
            klaroGeoLog('DEBUG: Modal not found in DOM, will try again later');
            setTimeout(handleModalOpen, 300);
            klaroGeoLog('DEBUG: handleModalOpen called recursively from documnet.querySelector');
            return;
        }
        
        // Find the save button in the modal
        const saveButton = klaroModal.querySelector('.cm-btn-success') || 
                        klaroModal.querySelector('.cm-btn-accept-all') || 
                        klaroModal.querySelector('button[data-role="accept"]');
        
        if (saveButton) {
            klaroGeoLog('DEBUG: Found save button in modal:', saveButton);
            
            // Add click event listener to the save button
            saveButton.addEventListener('click', function() {
                klaroGeoLog('DEBUG: Save button clicked');
                
                // Set the editing flag to false
                isEditingInModal = false;
                
                // If we need to update consent, do it now
                if (needsConsentUpdate) {
                    klaroGeoLog('DEBUG: Sending deferred consent update after save');
                    setTimeout(function() {
                        updateConsentState(true); // Force update
                    }, 100); // Short delay to allow Klaro to process the changes
                }
            });
        } else {
            klaroGeoLog('DEBUG: Save button not found in modal');
        }
        
        // Find the close button in the modal
        const closeButton = klaroModal.querySelector('.cm-btn-close') || 
                        klaroModal.querySelector('.cm-btn-decline') || 
                        klaroModal.querySelector('button[data-role="close"]') ||
                        klaroModal.querySelector('button[data-role="decline"]');
        
        if (closeButton) {
            klaroGeoLog('DEBUG: Found close button in modal:', closeButton);
            
            // Add click event listener to the close button
            closeButton.addEventListener('click', function() {
                klaroGeoLog('DEBUG: Close button clicked');
                
                // Set the editing flag to false
                isEditingInModal = false;
                
                // We don't send any updates when the modal is closed without saving
                klaroGeoLog('DEBUG: Modal closed without saving, discarding pending consent updates');
                needsConsentUpdate = false;
            });
        } else {
            klaroGeoLog('DEBUG: Close button not found in modal');
        }
        
        // Log all services in the modal for debugging
        const services = document.querySelectorAll('li.cm-service');
        klaroGeoLog('DEBUG: Found', services.length, 'services in the modal');
        
        if (services.length === 0) {
            klaroGeoLog('DEBUG: No services found in modal, will try again later');
            setTimeout(handleModalOpen, 300);
            klaroGeoLog('DEBUG: handleModalOpen called recursively after services check');
            return;
        }
        
        services.forEach((service, index) => {
            const title = service.querySelector('.cm-list-title');
            const serviceId = service.querySelector('input[type="checkbox"]')?.id;
            klaroGeoLog('DEBUG: Service', index, 'title:', title ? title.textContent : 'No title', 'ID:', serviceId || 'No ID');
        });
        
        // Check if controls are already injected
        if (controlsInjected) {
            // Double-check if controls actually exist in the DOM
            const existingControls = document.querySelector('.klaro-geo-consent-mode-controls');
            if (existingControls) {
                klaroGeoLog('DEBUG: Controls already injected and found in DOM, skipping modal open handler');
                return;
            } else {
                klaroGeoLog('DEBUG: Controls marked as injected but not found in DOM, will try to inject again');
                controlsInjected = false;
            }
        }

        // Check if we have the consent mode configuration
        // NOTE: Consent mode is ALWAYS enabled - no toggle check needed
        if (
            typeof window.klaroConsentData === 'undefined' ||
            !window.klaroConsentData.templateSettings ||
            !window.klaroConsentData.templateSettings.config ||
            !window.klaroConsentData.templateSettings.config.consent_mode_settings
        ) {
            klaroGeoLog('DEBUG: Consent Mode settings not found in template (modal open)');
            return;
        }

        klaroGeoLog('DEBUG: Consent Mode is enabled (always enabled) (modal open)');

        // Use the global ad storage service name
        klaroGeoLog('DEBUG: Ad storage event service name (modal open):', adStorageServiceName);

        if (!adStorageServiceName || adStorageServiceName === 'no_service') {
            klaroGeoLog('DEBUG: No ad storage event configured (modal open)');
            return;
        }
        
        // Find the service element directly
        let targetService = null;
        
        // First try to find by ID
        const serviceById = document.getElementById('service-item-' + adStorageServiceName);
        if (serviceById) {
            klaroGeoLog('DEBUG: Found service by ID:', adStorageServiceName);
            targetService = serviceById.closest('li.cm-service');
        }
        
        // If not found by ID, try to find by title
        if (!targetService) {
            klaroGeoLog('DEBUG: Service not found by ID, trying to find by title');
            
            // Try to find by title based on the service name
            // First, check if adStorageServiceName is valid
            if (!adStorageServiceName) {
                klaroGeoLog('DEBUG: adStorageServiceName is not defined, cannot search by title');
                return;
            }
            
            // Convert service name to a more readable format (e.g., 'google-ads' -> 'Google Ads')
            const readableServiceName = adStorageServiceName
                .split('-')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
                
            klaroGeoLog('DEBUG: Looking for service with title:', readableServiceName);
            
            const allServices = document.querySelectorAll('li.cm-service');
            for (let i = 0; i < allServices.length; i++) {
                const title = allServices[i].querySelector('.cm-service-title') || allServices[i].querySelector('.cm-list-title');
                if (title && title.textContent === readableServiceName) {
                    klaroGeoLog('DEBUG: Found service by exact title match:', readableServiceName);
                    targetService = allServices[i];
                    break;
                }
            }
            
            // Try to find by partial title match
            if (!targetService) {
                const allServices = document.querySelectorAll('li.cm-service');
                for (let i = 0; i < allServices.length; i++) {
                    const title = allServices[i].querySelector('.cm-service-title') || allServices[i].querySelector('.cm-list-title');
                    if (title && title.textContent.toLowerCase().includes(adStorageServiceName.toLowerCase())) {
                        klaroGeoLog('DEBUG: Found service by partial title match:', title.textContent);
                        targetService = allServices[i];
                        break;
                    }
                }
            }
        }
        
        if (!targetService) {
            klaroGeoLog('DEBUG: Could not find target service in modal, will try again later');
            setTimeout(handleModalOpen, 500);
            klaroGeoLog('DEBUG: handleModalOpen called recursively with missing targetSerivce');
            return;
        }
        
        klaroGeoLog('DEBUG: Found target service:', targetService);
        
        // Directly inject controls for the found service
        if (targetService) injectAdControlsForService(targetService);
    }

// Initialize the watcher
setupKlaroDataLayerWatcher();
setupModalObserver();
})();
