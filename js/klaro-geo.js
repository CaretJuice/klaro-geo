(function() {
  klaroGeoLog('DEBUG: klaro-geo.js loaded (early capture attempt)');

  /**
   * Generate a consent key from a service name.
   * For consent mode services (is_consent_mode_service=true), returns the consent_mode_key
   * directly (e.g., "analytics_storage"). For regular services, appends "_consent"
   * (e.g., "google_analytics_consent").
   * @param {string} serviceName - The service name (e.g., "google-analytics")
   * @returns {string} - The consent key
   */
  function getServiceConsentKey(serviceName) {
      // Check if this is a consent mode service with a dedicated key
      if (typeof window.klaroConfig !== 'undefined' && window.klaroConfig.services) {
          for (var i = 0; i < window.klaroConfig.services.length; i++) {
              var service = window.klaroConfig.services[i];
              if (service.name === serviceName && service.is_consent_mode_service && service.consent_mode_key) {
                  return service.consent_mode_key;
              }
          }
      }
      return serviceName.replace(/-/g, '_') + '_consent';
  }

  // Global variables for consent mode
  window.lastConsentUpdate = null;
  window.consentUpdateTimer = null;
  window.consentUpdateDelay = 50; // Default delay
  window.currentKlaroOpts = null;
  window.lastWatcherConsentTimestamp = null;

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

  // Detect consent mode type (basic or advanced)
  if (typeof window.klaroGeo.consentModeType === 'undefined') {
      window.klaroGeo.consentModeType = (typeof window.klaroConsentData !== 'undefined' &&
                                          window.klaroConsentData.consentModeType) || 'basic';
      klaroGeoLog('DEBUG: Consent mode type detection - consentModeType=' + window.klaroGeo.consentModeType);
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

      const queueSize = window.klaroGeo.queue.length;
      klaroGeoLog('DEBUG: Consent Queue - flushing', queueSize, 'events to dataLayer');

      while (window.klaroGeo.queue.length > 0) {
          const event = window.klaroGeo.queue.shift();
          klaroGeoLog('DEBUG: Consent Queue - pushing event:', event.event || 'no-event-name', event);
          window.dataLayer.push(event);
      }

      // Push queueFlushed event for timing visibility
      var queueFlushedEvent;
      if (typeof window.KlaroGeoEvents !== 'undefined') {
          queueFlushedEvent = window.KlaroGeoEvents.createKlaroGeoEvent('queueFlushed', {
              'queueSize': queueSize
          });
      } else {
          queueFlushedEvent = {
              'event': 'Klaro Event',
              'eventSource': 'klaro-geo',
              'klaroEventName': 'queueFlushed',
              'queueSize': queueSize
          };
      }
      window.dataLayer.push(queueFlushedEvent);

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

              // Always wait for 'Klaro Consent Update' (unified trigger)
              const triggerEvent = 'Klaro Consent Update';

              if (eventName === triggerEvent) {
                  klaroGeoLog('DEBUG: Consent Queue - detected consent event:', eventName);
                  onConsentEvent(eventName);
              }
          }

          return result;
      };

      klaroGeoLog('DEBUG: Consent Queue - listener initialized, waiting for: Klaro Consent Update');
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
                          pushData = window.KlaroGeoEvents.createKlaroForwardedEvent(name, data);
                      } else {
                          // Fallback for when events module hasn't loaded yet
                          pushData = {
                              'event': 'Klaro Event',
                              'eventSource': 'klaro',
                              'klaroEventName': name,
                              'klaroEventData': data
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
                      consentsCopy
                  );
              } else {
                  // Fallback - note: eventSource is now 'klaro' (not 'klaro-geo')
                  initialData = {
                      'event': 'Klaro Event',
                      'eventSource': 'klaro',
                      'klaroEventName': 'initialConsents',
                      'klaroEventData': consentsCopy,
                      'acceptedServices': acceptedServices
                  };
              }

              klaroGeoLog('DEBUG: Pushing initial Klaro event to dataLayer:', initialData);
              window.dataLayer.push(initialData);

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

          // Always store the consent receipt
          handleConsentChange(manager);
      }
      else {
          // For other events, just log them but don't trigger updates
          klaroGeoLog('DEBUG: Other Klaro event detected:', eventName, '- not triggering consent update');
      }
  }

  /**
   * Get consent mode service map.
   * Maps consent_mode_key (e.g., 'ad_storage') to service name (e.g., 'ad-storage').
   * Prefers klaroConsentData, falls back to deriving from klaroConfig.services.
   * @returns {Object} Map of consent_mode_key to service_name
   */
  function getConsentModeServiceMap() {
      if (typeof window.klaroConsentData !== 'undefined' &&
          window.klaroConsentData.consentModeServices) {
          return window.klaroConsentData.consentModeServices;
      }
      // Fallback: derive from klaroConfig.services
      var map = {};
      if (typeof window.klaroConfig !== 'undefined' && window.klaroConfig.services) {
          window.klaroConfig.services.forEach(function(service) {
              if (service.is_consent_mode_service && service.consent_mode_key) {
                  map[service.consent_mode_key] = service.name;
              }
          });
      }
      return map;
  }

  /**
   * Get parent-child service map.
   * Maps parent service name to array of child service names.
   * Prefers klaroConsentData, falls back to deriving from klaroConfig.services.
   * @returns {Object} Map of parent service name to array of child service names
   */
  function getParentChildMap() {
      if (typeof window.klaroConsentData !== 'undefined' &&
          window.klaroConsentData.parentChildMap) {
          return window.klaroConsentData.parentChildMap;
      }
      // Fallback: derive from klaroConfig.services
      var map = {};
      if (typeof window.klaroConfig !== 'undefined' && window.klaroConfig.services) {
          window.klaroConfig.services.forEach(function(service) {
              if (service.parent_service) {
                  if (!map[service.parent_service]) {
                      map[service.parent_service] = [];
                  }
                  map[service.parent_service].push(service.name);
              }
          });
      }
      return map;
  }

  // Function to handle consent updates and trigger dataLayer events
  // NOTE: Consent mode is ALWAYS enabled - consent mode services are now first-class Klaro services
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

          // Get consent mode service map
          const consentModeServiceMap = getConsentModeServiceMap();
          const parentChildMap = getParentChildMap();

          // Build consent mode update from consent mode services
          const completeUpdate = {};

          // Process consent mode services - map their consent state to standard Google keys
          Object.keys(consentModeServiceMap).forEach(function(consentModeKey) {
              const serviceName = consentModeServiceMap[consentModeKey];
              if (activeManager && activeManager.consents) {
                  const isGranted = activeManager.consents[serviceName] === true;
                  completeUpdate[consentModeKey] = isGranted ? 'granted' : 'denied';
                  klaroGeoLog('DEBUG: Consent mode key', consentModeKey, '=', completeUpdate[consentModeKey],
                      '(from service:', serviceName, ')');
              }
          });

          // Enforce parent-child dependencies
          // If parent is denied, children must also be denied
          Object.keys(parentChildMap).forEach(function(parentName) {
              const children = parentChildMap[parentName];
              if (activeManager && activeManager.consents) {
                  const parentConsented = activeManager.consents[parentName] === true;
                  if (!parentConsented) {
                      // Parent is denied - ensure all children are also denied
                      children.forEach(function(childName) {
                          // Find the consent mode key for this child service
                          Object.keys(consentModeServiceMap).forEach(function(key) {
                              if (consentModeServiceMap[key] === childName) {
                                  completeUpdate[key] = 'denied';
                                  klaroGeoLog('DEBUG: Enforcing parent-child: child', childName,
                                      '(' + key + ') denied because parent', parentName, 'is denied');
                              }
                          });
                      });
                  }
              }
          });

          // Add dynamic service consent keys for ALL services
          if (activeManager && activeManager.consents) {
              var consentKeys = Object.keys(activeManager.consents);
              consentKeys.forEach(function(serviceName) {
                  var dynamicKey = getServiceConsentKey(serviceName);
                  var isGranted = activeManager.consents[serviceName] === true;
                  completeUpdate[dynamicKey] = isGranted ? 'granted' : 'denied';
              });
          }

          // Log the complete update being sent (now includes dynamic keys)
          klaroGeoLog('DEBUG: Consent update object (with dynamic service keys):', completeUpdate);

          // Store last update
          window.lastConsentUpdate = completeUpdate;

          // Call gtag('consent', 'update') directly — no intermediary event needed
          // This queues in dataLayer for GTM to process (or works standalone without GTM)
          if (typeof window.dataLayer !== 'undefined' && activeManager && activeManager.consents) {
              // Create acceptedServices array for the update event
              const acceptedServices = Object.keys(activeManager.consents)
                  .filter(serviceName => activeManager.consents[serviceName] === true);

              // Always call gtag consent update
              klaroGeoLog('DEBUG: Calling gtag consent update');
              gtag('consent', 'update', completeUpdate);

              // Determine skip condition for Klaro Consent Update event:
              // In Basic GTM mode, for initialConsents when GTM not consented,
              // do NOT push Klaro Consent Update (would flush consent queue prematurely)
              const isGtmMode = window.klaroGeo && window.klaroGeo.useGTM;
              const gtmConsented = activeManager.consents['google-tag-manager'] === true;
              const isAdvancedMode = window.klaroGeo && window.klaroGeo.consentModeType === 'advanced';

              klaroGeoLog('DEBUG: Klaro Consent Update skip check:',
                  'eventType=' + eventType,
                  'isGtmMode=' + isGtmMode,
                  'gtmConsented=' + gtmConsented,
                  'isAdvancedMode=' + isAdvancedMode);

              if (eventType === 'initialConsents' && isGtmMode && !gtmConsented && !isAdvancedMode) {
                  klaroGeoLog('DEBUG: Skipping Klaro Consent Update push for initialConsents - GTM not yet consented in Basic mode');
              } else {
                  klaroGeoLog('DEBUG: Pushing Klaro Consent Update event to dataLayer');
                  window.dataLayer.push({
                      'event': 'Klaro Consent Update',
                      'consent_trigger': eventType,
                      'consentMode': completeUpdate,
                      'acceptedServices': acceptedServices
                  });
              }
          }

          // Reset timer
          window.consentUpdateTimer = null;

      }, window.consentUpdateDelay || 50); // Default delay
  }

  /**
   * Protect multi-purpose services from being disabled when only one of their purposes is toggled off.
   * When groupByPurpose is true, Klaro's UI shows purpose-level toggles. Disabling a purpose
   * disables ALL services in that purpose, even if a service belongs to another still-active purpose.
   * This listener re-enables such services after Klaro processes the purpose toggle.
   */
  function setupMultiPurposeProtection() {
      if (!window.klaroConfig || !window.klaroConfig.groupByPurpose) return;

      var services = window.klaroConfig.services || [];
      var multiPurposeServices = services.filter(function(s) {
          return s.purposes && s.purposes.length > 1;
      });
      if (multiPurposeServices.length === 0) return;

      document.addEventListener('change', function(e) {
          if (!e.target || !e.target.matches || !e.target.matches('input[id^="purpose-item-"]')) return;
          if (e.target.checked) return; // Only fix when disabling a purpose

          var purposeName = e.target.id.replace('purpose-item-', '');

          // After Klaro has processed (synchronously during bubble), fix multi-purpose services
          setTimeout(function() {
              var manager;
              try { manager = window.klaro.getManager(); } catch(err) { return; }
              if (!manager) return;

              multiPurposeServices.forEach(function(service) {
                  if (!service.purposes.includes(purposeName)) return;

                  var otherPurposeActive = service.purposes.some(function(p) {
                      if (p === purposeName) return false;
                      var otherInput = document.getElementById('purpose-item-' + p);
                      return otherInput && otherInput.checked;
                  });

                  if (otherPurposeActive && manager.consents[service.name] !== true) {
                      klaroGeoLog('DEBUG: Re-enabling multi-purpose service "' + service.name +
                          '" (purpose "' + purposeName + '" disabled, but other purpose still active)');
                      if (typeof manager.updateConsent === 'function') {
                          manager.updateConsent(service.name, true);
                      } else {
                          manager.consents[service.name] = true;
                      }
                  }
              });
          }, 0);
      });

      klaroGeoLog('DEBUG: Multi-purpose protection enabled for: ' +
          multiPurposeServices.map(function(s) { return s.name; }).join(', '));
  }

  // Initialize the watcher
  setupKlaroDataLayerWatcher();
  setupMultiPurposeProtection();
})();
