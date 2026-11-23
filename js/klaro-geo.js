(function() {

  // Event creation and consent-mode mapping live in klaro-geo-events.js, which
  // is enqueued as a dependency of this script.
  const KlaroGeoEvents = window.KlaroGeoEvents;

  // Global variables for consent mode
  window.lastConsentUpdate = null;
  window.consentUpdateTimer = null;
  window.consentUpdateDelay = 50; // Default delay

  // window.dataLayer, window.gtag, window.klaroGeo and window.klaroConsentData
  // are defined by the inline script PHP prints in front of the bootstrap
  // (klaro_geo_render_frontend_payload()), so they exist when this file runs.

  // =============================================================================
  // CONSENT QUEUE
  // Queues dataLayer events until consent state is confirmed
  // =============================================================================

  // Preserve any stub queue that was set up earlier
  const existingQueue = window.klaroGeo.queue || [];

  // Queue configuration
  const QUEUE_MAX_SIZE = 100;

  // Queue state
  window.klaroGeo.queue = existingQueue; // Preserve events from stub
  window.klaroGeo.consentConfirmed = false;

  // Note: The queue's job is TIMING (wait for consent state to be SET), not consent enforcement.
  // GTM's consent mode handles whether tags actually fire based on consent state.
  // We flush when the consent event fires, regardless of what consent was given.

  /**
   * Work out GTM mode, unless the page set window.klaroGeo.useGTM itself.
   * GTM mode is on if:
   * 1. klaro_geo_gtm_id is set (standalone GTM), OR
   * 2. google-tag-manager is a configured Klaro service
   *
   * Called once Klaro has a manager, because the services are in
   * window.klaroConfig, and in client geo mode that does not exist until the
   * geo lookup has returned (after this file has run).
   */
  function detectGtmMode() {
      if (typeof window.klaroGeo.useGTM !== 'undefined') {
          return;
      }

      const hasGtmId = !!window.klaroConsentData.gtmId;
      const hasGtmService = window.klaroConfig.services.some(function(s) {
          return s.name === 'google-tag-manager';
      });

      window.klaroGeo.useGTM = hasGtmId || hasGtmService;
      klaroGeoLog('GTM mode detection - hasGtmId=' + hasGtmId + ', hasGtmService=' + hasGtmService + ', useGTM=' + window.klaroGeo.useGTM);
  }

  // Detect consent mode type (basic or advanced)
  if (typeof window.klaroGeo.consentModeType === 'undefined') {
      window.klaroGeo.consentModeType = window.klaroConsentData.consentModeType || 'basic';
      klaroGeoLog('Consent mode type detection - consentModeType=' + window.klaroGeo.consentModeType);
  }

  /**
   * Push an event to the consent queue or directly to dataLayer
   * @param {Object} eventData - The dataLayer event object
   */
  window.klaroGeo.push = function(eventData) {

      if (window.klaroGeo.consentConfirmed) {
          // Consent event already fired, push directly to dataLayer
          klaroGeoLog('Consent Queue - pushing directly to dataLayer:', eventData.event || 'unknown event');
          window.dataLayer.push(eventData);
      } else {
          // Consent not yet confirmed, queue the event
          if (window.klaroGeo.queue.length >= QUEUE_MAX_SIZE) {
              // Drop oldest event
              const dropped = window.klaroGeo.queue.shift();
              klaroGeoError('Consent queue limit reached (100). Dropping oldest event:', dropped.event || 'unknown');
          }
          klaroGeoLog('Consent Queue - queuing event:', eventData.event || 'unknown event',
                      '(queue size:', window.klaroGeo.queue.length + 1, ')');
          window.klaroGeo.queue.push(eventData);
      }
  };

  /**
   * Flush all queued events to dataLayer
   */
  function flushConsentQueue() {
      if (window.klaroGeo.queue.length === 0) {
          return;
      }

      const queueSize = window.klaroGeo.queue.length;
      klaroGeoLog('Consent Queue - flushing', queueSize, 'events to dataLayer');

      while (window.klaroGeo.queue.length > 0) {
          const event = window.klaroGeo.queue.shift();
          window.dataLayer.push(event);
      }

      // Push queueFlushed event for timing visibility
      window.dataLayer.push(KlaroGeoEvents.createKlaroGeoEvent('queueFlushed', {
          'queueSize': queueSize
      }));

  }

  /**
   * Handle consent event - flush queue
   * @param {string} eventName - The consent event name that triggered this
   */
  function onConsentEvent(eventName) {
      if (window.klaroGeo.consentConfirmed) {
          klaroGeoLog('Consent Queue - consent already confirmed, ignoring:', eventName);
          return;
      }

      klaroGeoLog('Consent Queue - consent event received:', eventName);
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
                  onConsentEvent(eventName);
              }
          }

          return result;
      };

  })();

  // Process any events that were queued before this script loaded (stub pattern)
  if (existingQueue.length > 0) {
      klaroGeoLog('Consent Queue - found', existingQueue.length, 'pre-queued events from stub');
  }

  // Set up a watcher for late-arriving stub events (in case inline scripts run after klaro-geo.js)
  // This handles the case where wp_footer priority ordering causes the stub to run after us
  const originalQueue = window.klaroGeo.queue;
  Object.defineProperty(window.klaroGeo, 'queue', {
      get: function() { return originalQueue; },
      set: function(newQueue) {
          // If someone tries to set queue to a new array with items, merge them
          if (Array.isArray(newQueue) && newQueue.length > 0) {
              klaroGeoLog('Consent Queue - detected late stub queue assignment with', newQueue.length, 'events');
              newQueue.forEach(function(event) {
                  if (window.klaroGeo.consentConfirmed) {
                      window.dataLayer.push(event);
                  } else {
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

  // The visitor saved a choice in the banner: record it.
  function handleConsentChange(manager) {
      generateConsentReceipt(manager, 'choice');
  }

  // Set in localStorage once the GPC receipt below has been stored. It holds no
  // identifier, only the fact that this browser's opt-out is already on record.
  const GPC_RECEIPT_FLAG = 'klaro_geo_gpc_receipt_recorded';

  /**
   * Record a GPC opt-out for a visitor who has not used the banner.
   *
   * GPC switches services off without the visitor doing anything, so without
   * this the opt-out most GPC visitors get would leave no record. One receipt
   * is written per browser, not per page view: nothing is written once a
   * choice has been saved (that receipt records the signal too) or once this
   * receipt has been stored.
   */
  function recordGpcReceipt(manager) {
      // window.klaroGeo.gpc is set by the bootstrap when it applies the template,
      // which is before Klaro has a manager.
      if (window.klaroGeo.gpc.detected !== true || manager.confirmed) {
          return;
      }

      try {
          if (window.localStorage.getItem(GPC_RECEIPT_FLAG)) {
              return;
          }
      } catch (e) {
          // Without storage every page view would write a receipt.
          return;
      }

      const sent = generateConsentReceipt(manager, 'gpc');
      if (sent) {
          sent.then(function(data) {
              if (data && data.success) {
                  try {
                      window.localStorage.setItem(GPC_RECEIPT_FLAG, '1');
                  } catch (e) {
                      klaroGeoLog('Could not remember that the GPC receipt was stored');
                  }
              }
          });
      }
  }

  /**
   * Build a consent receipt from Klaro's current state and send it.
   *
   * @param {Object} manager     Klaro's consent manager.
   * @param {string} receiptType 'choice' (the visitor saved a choice) or 'gpc'
   *                             (GPC was applied and the visitor has not chosen).
   * @returns {Promise|null} The server request, or null when logging is off.
   */
  function generateConsentReceipt(manager, receiptType) {
      // window.klaroConsentData.enableConsentLogging is the effective setting:
      // receipts enabled globally AND consent logging enabled for this template.
      const enableConsentLogging = window.klaroConsentData.enableConsentLogging === true;

      if (!enableConsentLogging) {
          klaroGeoLog('Consent logging is disabled for this template/country. No receipt generated.');
          return null;
      }

      // Generate a unique receipt ID
      let receiptId = 'receipt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

      // Global Privacy Control, as it stood when the choice was saved: whether
      // the browser sent the signal, and which services the plugin switched
      // off by default because of it (set by the bootstrap).
      const gpc = window.klaroGeo.gpc;

      // Create the consent receipt
      let consentReceipt = {
          receipt_id: receiptId,
          receipt_type: receiptType,
          timestamp: Math.floor(Date.now() / 1000), // Unix timestamp
          consent_choices: {},
          template_name: window.klaroConsentData.templateName || 'default',
          template_source: window.klaroConsentData.templateSource || 'fallback',
          country_code: window.klaroConsentData.detectedCountry || null,
          region_code: window.klaroConsentData.detectedRegion || null,
          admin_override: window.klaroConsentData.adminOverride === true,
          template_settings: window.klaroConsentData.templateSettings || {},
          klaro_config: window.klaroConfig || null,
          gpc_signal: navigator.globalPrivacyControl === true,
          gpc_affected_services: gpc.affectedServices.slice()
      };

      // Get consents from the manager
      let consents = manager.consents;

      // Add consent choices to the receipt
      for (let k of Object.keys(consents)) {
          consentReceipt.consent_choices[k] = consents[k] === true;
      }

      // Keep the visitor's own copy of a choice they made. A GPC receipt is
      // not kept in the browser: the visitor has asked not to be tracked, and
      // a stored receipt ID would be a persistent identifier.
      if (receiptType === 'choice') {
          storeReceiptLocally(consentReceipt);
      }

      const sent = sendReceiptToServer(consentReceipt)
          .catch(function(error) {
              klaroGeoError('Error from server when sending receipt:', error);
          });

      window.dataLayer.push(KlaroGeoEvents.createKlaroGeoEvent('generateConsentReceipt', {
          'klaroGeoConsentReceipt': consentReceipt.receipt_id,
          'klaroGeoConsentReceiptType': receiptType,
          'klaroGeoTemplateSource': consentReceipt.template_source,
          'klaroGeoAdminOverride': consentReceipt.admin_override,
          'klaroGeoEnableConsentLogging': enableConsentLogging
      }));

      return sent;
  }

  /**
   * Store the consent receipt in localStorage
   */
  function storeReceiptLocally(receipt) {
      try {
          // Get existing receipts
          let existingData = window.localStorage.getItem('klaro_consent_receipts');
          let receipts = [];

          if (existingData) {
              try {
                  receipts = JSON.parse(existingData);
              } catch (parseError) {
                  klaroGeoError('Failed to parse existing receipts:', parseError);
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
          klaroGeoError('Failed to store consent receipt locally:', e);
      }
  }

  /**
   * Send the consent receipt to the server
   */
  function sendReceiptToServer(receipt) {
      // ajaxUrl is present whenever receipts are enabled, and a receipt is only
      // generated when they are.
      let ajaxUrl = window.klaroConsentData.ajaxUrl;

      // Create form data
      let formData = new FormData();
      formData.append('action', 'klaro_geo_store_consent_receipt');

      // Add nonce if available
      if (window.klaroConsentData.nonce) {
          formData.append('nonce', window.klaroConsentData.nonce);
      }

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
              return response.text().then(function(text) {
                  throw new Error('Server returned status ' + response.status + ': ' +
                      (text ? text.substring(0, 100) : 'No response text'));
              });
          }
          return response.json();
      })
      .then(function(data) {
          if (!data.success) {
              klaroGeoError('Failed to store consent receipt:', data);
          }
          return data;
      })
      .catch(function(error) {
          klaroGeoError('Error sending consent receipt:', error);
          // Don't throw the error further to prevent unhandled promise rejection
          return { success: false, error: error.message };
      });
  }

  function setupKlaroDataLayerWatcher() {
      // getManager() throws until window.klaroConfig exists. In client geo
      // mode that is after the geo lookup, so keep trying until it does.
      let manager = null;
      try {
          manager = window.klaro.getManager();
      } catch (error) {
          manager = null;
      }

      if (manager) {
          setupWatcher(manager);
      } else {
          setTimeout(setupKlaroDataLayerWatcher, 50);
      }
  }

  // Runs once, when Klaro has a manager and so window.klaroConfig exists.
  function setupWatcher(manager) {
      detectGtmMode();
      setupMultiPurposeProtection(manager);

      // Push initial consent data
      pushConsentData(manager);

      const suppressConsentsEvents = window.klaroConsentData.suppressConsentsEvents;

      manager.watch({
          update: function(obj, name, data) {
              // Events that should always be forwarded to the dataLayer
              const alwaysForward = ['saveConsents', 'applyConsents'];

              // If suppressConsentsEvents is enabled, skip intermediate 'consents' events
              // These fire once per service as Klaro updates each service individually.
              // We only want to forward 'saveConsents' which fires once after all services are updated.
              if (suppressConsentsEvents && !alwaysForward.includes(name)) {
                  // For initialConsents, pushConsentData already handles it, so skip here to avoid duplicates
                  if (name === 'initialConsents') {
                      return;
                  }
                  // Still call consent handling for internal processing, but don't push to dataLayer
                  handleKlaroConsentEvents(manager, name, data);
                  return;
              }

              window.dataLayer.push(KlaroGeoEvents.createKlaroForwardedEvent(name, data));

              // Call consent handling functions
              handleKlaroConsentEvents(manager, name, data);
          }
      });
  }

  function pushConsentData(manager) {
      // Defer to next event loop tick to let Klaro fully hydrate consent from cookies
      // Without this delay, manager.consents may be empty even for returning users
      setTimeout(function() {
          // Deep copy consents to capture current state (not a reference that changes later)
          const consentsCopy = JSON.parse(JSON.stringify(manager.consents));

          // initialConsents represents Klaro manager state, so it uses the 'klaro' eventSource
          window.dataLayer.push(KlaroGeoEvents.createKlaroForwardedEvent('initialConsents', consentsCopy));

          // Call consent handling functions
          handleKlaroConsentEvents(manager, 'initialConsents', manager.consents);

          recordGpcReceipt(manager);
      }, 0);
  }

  // Consolidated function to handle consent events
  // NOTE: Consent mode is ALWAYS enabled - no toggle check needed
  function handleKlaroConsentEvents(manager, eventName, data) {

      if (eventName === 'initialConsents') {
          // For initial consents, update the Google consent mode
          updateGoogleConsentMode(manager, eventName, data);
      }
      else if (eventName === 'saveConsents') {
          // For save consents, update the Google consent mode and store the consent receipt
          updateGoogleConsentMode(manager, eventName, data);

          // Always store the consent receipt
          handleConsentChange(manager);
      }
  }

  function updateGoogleConsentMode(manager, eventType, data) {
      klaroGeoLog('Google consent mode update triggered by:', eventType,
          'with data:', data ? (typeof data === 'object' ? 'consent object' : data) : 'no data');

      // Check for duplicate update if data is provided
      if (data && window.lastConsentUpdate &&
          JSON.stringify(window.lastConsentUpdate) === JSON.stringify(data)) {
          klaroGeoLog('Skipping duplicate consent update');
          return;
      }

      // Clear any pending update timer
      if (window.consentUpdateTimer) {
          clearTimeout(window.consentUpdateTimer);
      }

      // Set a new timer to debounce
      window.consentUpdateTimer = setTimeout(function() {

          // klaro.getManager() returns one manager object per config, so the
          // manager passed in is always the current one; its consents are read
          // here, when the timer fires, not when the update was requested.
          // Standard Google keys from consent mode services (with parent-child
          // dependencies enforced) plus a dynamic key for every service
          const completeUpdate = KlaroGeoEvents.buildConsentMode(manager.consents);

          // Log the complete update being sent (now includes dynamic keys)
          klaroGeoLog('Consent update object (with dynamic service keys):', completeUpdate);

          // On initialConsents, if all values match the defaults set in <head>,
          // the gtag('consent', 'update') call is redundant and is skipped.
          // The 'Klaro Consent Update' event is still pushed: it is the signal that
          // consent state is known, it flushes the consent queue, and it is the
          // trigger tags fire on. A fresh visitor's state always matches the
          // defaults, so skipping the event here meant fresh visitors never got it
          // (issue #4).
          let matchesDefaults = false;
          if (eventType === 'initialConsents') {
              let defaults = window.klaroConsentData.templateSettings.config.consent_mode_settings.consent_defaults;
              matchesDefaults = Object.keys(completeUpdate).every(function(key) {
                  return completeUpdate[key] === (defaults[key] || 'denied');
              });
          }

          // Store last update
          window.lastConsentUpdate = completeUpdate;

          // Call gtag('consent', 'update') directly — no intermediary event needed
          // This queues in dataLayer for GTM to process (or works standalone without GTM)
          const acceptedServices = Object.keys(manager.consents)
              .filter(serviceName => manager.consents[serviceName] === true);

          if (matchesDefaults) {
              klaroGeoLog('Skipping redundant gtag consent update on initialConsents - all values match defaults');
          } else {
              window.gtag('consent', 'update', completeUpdate);
          }

          // Determine skip condition for Klaro Consent Update event:
          // In Basic GTM mode, for initialConsents when GTM not consented,
          // do NOT push Klaro Consent Update (would flush consent queue prematurely)
          const isGtmMode = window.klaroGeo.useGTM;
          const gtmConsented = manager.consents['google-tag-manager'] === true;
          const isAdvancedMode = window.klaroGeo.consentModeType === 'advanced';

          if (eventType === 'initialConsents' && isGtmMode && !gtmConsented && !isAdvancedMode) {
              klaroGeoLog('Skipping Klaro Consent Update push for initialConsents - GTM not yet consented in Basic mode');
          } else {
              window.dataLayer.push({
                  'event': 'Klaro Consent Update',
                  'consent_trigger': eventType,
                  'consentMode': completeUpdate,
                  'acceptedServices': acceptedServices,
                  'gpcActive': window.klaroGeo.gpc.detected
              });
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
  function setupMultiPurposeProtection(manager) {
      if (!window.klaroConfig.groupByPurpose) return;

      let multiPurposeServices = window.klaroConfig.services.filter(function(s) {
          return s.purposes && s.purposes.length > 1;
      });
      if (multiPurposeServices.length === 0) return;

      document.addEventListener('change', function(e) {
          if (!e.target.matches('input[id^="purpose-item-"]')) return;
          if (e.target.checked) return; // Only fix when disabling a purpose

          let purposeName = e.target.id.replace('purpose-item-', '');

          // After Klaro has processed (synchronously during bubble), fix multi-purpose services
          setTimeout(function() {
              multiPurposeServices.forEach(function(service) {
                  if (!service.purposes.includes(purposeName)) return;

                  let otherPurposeActive = service.purposes.some(function(p) {
                      if (p === purposeName) return false;
                      let otherInput = document.getElementById('purpose-item-' + p);
                      return otherInput && otherInput.checked;
                  });

                  if (otherPurposeActive && manager.consents[service.name] !== true) {
                      klaroGeoLog('Re-enabling multi-purpose service "' + service.name +
                          '" (purpose "' + purposeName + '" disabled, but other purpose still active)');
                      manager.updateConsent(service.name, true);
                  }
              });
          }, 0);
      });

      klaroGeoLog('Multi-purpose protection enabled for: ' +
          multiPurposeServices.map(function(s) { return s.name; }).join(', '));
  }

  // Initialize the watcher
  setupKlaroDataLayerWatcher();
})();
