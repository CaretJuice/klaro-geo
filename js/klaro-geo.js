(function() {
  console.log('DEBUG: klaro-geo.js loaded (early capture attempt)');

  // Global variables for consent mode
  window.lastConsentUpdate = null;
  window.consentUpdateTimer = null;
  window.consentUpdateDelay = 50; // Default delay
  window.controlsInjected = false;
  window.adStorageServiceName = null;
  window.analyticsStorageServiceName = null;
  window.adUserDataConsent = false;
  window.adPersonalizationConsent = false;
  window.currentKlaroOpts = null;
  window.lastWatcherConsentTimestamp = null;

  // Make sure window.dataLayer exists
  if (typeof window.dataLayer === 'undefined') {
      window.dataLayer = [];
  }

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
                  console.log('Found enableConsentLogging in dataLayer:', enableConsentLogging);
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
          console.log('Consent logging is disabled for this template/country. Receipt stored locally only.');
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
                  console.log('Found enableConsentLogging in dataLayer for server send:', enableConsentLogging);
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
          console.log('Server-side consent logging is disabled for this template/country. Receipt stored locally only.');
      }

      // Push to dataLayer
      window.dataLayer.push({
          'event': 'Klaro Event',
          'eventSource': 'klaro-geo',
          'klaroEventName': 'generateConsentReceipt',
          'klaroGeoConsentReceipt': consentReceipt,
          'klaroGeoTemplateSource': consentReceipt.template_source,
          'klaroGeoAdminOverride': consentReceipt.admin_override,
          'klaroGeoEnableConsentLogging': enableConsentLogging
      });
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
    console.log('Sending receipt data to server:', receipt);

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
        if (process.env.NODE_ENV !== 'test') {
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
        console.log('DEBUG: Klaro manager available early, setting up watcher');
        setupWatcher(manager);
        return; // Success, don't retry
      } else {
        console.warn('DEBUG: Klaro manager is undefined, even though klaro is defined.');
      }
    } catch (error) {
      console.error('DEBUG: Error getting manager early:', error);
    }
  }

  // Fallback: Retry with setTimeout
  console.log('DEBUG: Klaro not fully ready, retrying setupKlaroDataLayerWatcher');
  setTimeout(setupKlaroDataLayerWatcher, 50); // Very short delay
}

function setupWatcher(manager) {
  try {
      // Push initial consent data
      pushConsentData(manager);

      manager.watch({
          update: function(obj, name, data) {
              if (typeof window.dataLayer !== 'undefined') {
                  // Only include acceptedServices for initialConsents and saveConsents events
                  const pushData = {
                      'event': 'Klaro Event',
                      'eventSource': 'klaro',
                      'klaroEventName': name,
                      'klaroEventData': data,
                      'klaroConfig': obj.config
                  };
                  
                  // Only add acceptedServices for initialConsents and saveConsents events
                  if (name === 'initialConsents' || name === 'saveConsents') {
                      const acceptedServices = Object.keys(manager.consents)
                          .filter(serviceName => manager.consents[serviceName] === true);
                      pushData.acceptedServices = acceptedServices;
                  }

                  console.log('DEBUG: Pushing Klaro event to dataLayer:', pushData);
                  window.dataLayer.push(pushData);

                  // Call consent handling functions
                  handleKlaroConsentEvents(manager, name, data);
              } else {
                  console.warn('DEBUG: dataLayer is not defined, cannot push Klaro event');
              }
          }
      });
      console.log('DEBUG: Generic Klaro dataLayer watcher setup complete');

  } catch (error) {
      console.error('DEBUG: Error setting up Klaro dataLayer watcher:', error);
  }
}

function pushConsentData(manager) {
  // Push initial consent data to dataLayer
  if (typeof window.dataLayer !== 'undefined') {
      // Create acceptedServices array
      const acceptedServices = Object.keys(manager.consents)
          .filter(serviceName => manager.consents[serviceName] === true);

      const initialData = {
          'event': 'Klaro Event',
          'eventSource': 'klaro-geo',
          'klaroEventName': 'initialConsents',
          'klaroEventData': manager.consents,
          'acceptedServices': acceptedServices,
          'klaroConfig': manager.config
      };

      console.log('DEBUG: Pushing initial Klaro event to dataLayer:', initialData);
      window.dataLayer.push(initialData);

      // Set global variables for consent mode
      if (manager.config && manager.config.consent_mode_settings) {
          window.adStorageServiceName = manager.config.consent_mode_settings.ad_storage_service;
          window.analyticsStorageServiceName = manager.config.consent_mode_settings.analytics_storage_service;
          window.adUserDataConsent = manager.config.consent_mode_settings.ad_user_data;
          window.adPersonalizationConsent = manager.config.consent_mode_settings.ad_personalization;
      }
      
      // Call consent handling functions
      handleKlaroConsentEvents(manager, 'initialConsents', manager.consents);
  } else {
      console.warn('DEBUG: dataLayer is not defined, cannot push initial Klaro event');
  }
}

// Consolidated function to handle consent events
function handleKlaroConsentEvents(manager, eventName, data) {
  console.log('DEBUG: Handling Klaro consent event:', eventName);

  // Check if consent mode is enabled
  const isConsentModeEnabled = manager.config && 
                              manager.config.consent_mode_settings && 
                              manager.config.consent_mode_settings.initialize_consent_mode === true;
  
  if (eventName === 'initialConsents') {
      // For initial consents, update the Google consent mode
      console.log('DEBUG: Initial consents detected, updating Google consent mode');
      
      if (isConsentModeEnabled) {
          // If consent mode is enabled, updateGoogleConsentMode will push klaroConsentUpdate
          updateGoogleConsentMode(manager, eventName, data);
      } else {
          // If consent mode is not enabled, push klaroConsentUpdate directly
          pushKlaroConsentUpdate(manager, eventName, null);
      }
  } 
  else if (eventName === 'saveConsents') {
      // For save consents, update the Google consent mode and store the consent receipt
      console.log('DEBUG: Save consents detected, updating Google consent mode and storing receipt');
      
      if (isConsentModeEnabled) {
          // If consent mode is enabled, updateGoogleConsentMode will push klaroConsentUpdate
          updateGoogleConsentMode(manager, eventName, data);
      } else {
          // If consent mode is not enabled, push klaroConsentUpdate directly
          pushKlaroConsentUpdate(manager, eventName, null);
      }
      
      // Always store the consent receipt
      handleConsentChange(manager);
  }
  else {
      // For other events, just log them but don't trigger updates
      console.log('DEBUG: Other Klaro event detected:', eventName, '- not triggering consent update');
  }
}

// Function to push klaroConsentUpdate event to dataLayer when consent mode is not enabled
function pushKlaroConsentUpdate(manager, eventType, consentMode) {
  if (typeof window.dataLayer !== 'undefined') {
      // Create acceptedServices array for the update event
      const acceptedServices = Object.keys(manager.consents)
          .filter(serviceName => manager.consents[serviceName] === true);
          
      const consentUpdateData = {
          'event': 'Klaro Consent Update',
          'eventSource': 'klaro-geo',
          'acceptedServices': acceptedServices,
          'triggerEvent': eventType
      };
      
      // Add consentMode if provided
      if (consentMode) {
          consentUpdateData.consentMode = consentMode;
      }
      
      console.log('DEBUG: Pushing klaroConsentUpdate to dataLayer (consent mode disabled):', consentUpdateData);
      window.dataLayer.push(consentUpdateData);
  }
}

// Function to handle consent updates and trigger dataLayer events
function updateGoogleConsentMode(manager, eventType, data) {
  console.log('DEBUG: Google consent mode update triggered by:', eventType, 
    'with data:', data ? (typeof data === 'object' ? 'consent object' : data) : 'no data');

  // Check if gtag is available
  if (typeof window.gtag !== 'function') {
      console.log('DEBUG: gtag not available, skipping consent update');
      return;
  }

  // Check for duplicate update if data is provided
  if (data && window.lastConsentUpdate &&
      JSON.stringify(window.lastConsentUpdate) === JSON.stringify(data)) {
      console.log('DEBUG: Skipping duplicate consent update');
      return;
  }

  // Clear any pending update timer
  if (window.consentUpdateTimer) {
      console.log('DEBUG: Clearing pending consent update timer');
      clearTimeout(window.consentUpdateTimer);
  }

  // Set a new timer to debounce
  console.log('DEBUG: Setting debounced consent update timer');
  window.consentUpdateTimer = setTimeout(function() {

      // Get consent state from Klaro
      let adServiceEnabled = false;
      let analyticsServiceEnabled = false;
      
      // First try to use the provided manager
      if (manager && manager.consents) {
          console.log('DEBUG: Using provided manager for consent state');
          if (window.adStorageServiceName) {
              adServiceEnabled = manager.consents[window.adStorageServiceName] === true;
          }
          if (window.analyticsStorageServiceName) {
              analyticsServiceEnabled = manager.consents[window.analyticsStorageServiceName] === true;
          }
      } else {
          // If no manager provided or it doesn't have consents, try to get one
          try {
              if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
                  console.log('DEBUG: Getting fresh Klaro manager for consent state');
                  const klaroManager = window.klaro.getManager();
                  if (klaroManager && klaroManager.consents) {
                      if (window.adStorageServiceName) {
                          adServiceEnabled = klaroManager.consents[window.adStorageServiceName] === true;
                      }
                      if (window.analyticsStorageServiceName) {
                          analyticsServiceEnabled = klaroManager.consents[window.analyticsStorageServiceName] === true;
                      }
                  }
              }
          } catch (e) {
              console.error('DEBUG: Error getting consent state from Klaro manager:', e);
              // Try to use data as fallback if provided
              if (data) {
                  adServiceEnabled = data.ad_storage === 'granted'; // Fallback
                  analyticsServiceEnabled = data.analytics_storage === 'granted'; // Fallback
              }
          }
      }

      // Log the current state of the sub-controls
      console.log('DEBUG: Current sub-control states - adUserDataConsent:', window.adUserDataConsent, 'adPersonalizationConsent:', window.adPersonalizationConsent);
      
      // Create complete update
      const completeUpdate = {
          'ad_storage': adServiceEnabled ? 'granted' : 'denied',
          'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
          'ad_user_data': (adServiceEnabled && window.adUserDataConsent === true) ? 'granted' : 'denied',
          'ad_personalization': (adServiceEnabled && window.adPersonalizationConsent === true) ? 'granted' : 'denied'
      };
      
      // Log the complete update being sent
      console.log('DEBUG: Sending consent update to gtag:', completeUpdate);

      // Store last update
      window.lastConsentUpdate = completeUpdate;

      // Send to gtag
      window.gtag('consent', 'update', completeUpdate);
      console.log('DEBUG: Consent state updated with:', completeUpdate);

      // Update UI controls
      if (manager.services) {
          const adService = manager.services.find(service => service.name === window.adStorageServiceName);
          if (adService) {
              const serviceElement = document.getElementById('service-item-' + window.adStorageServiceName);
              updateControlsUI(manager, serviceElement, adServiceEnabled);
          }
      }
      
      // Push klaroConsentUpdate event to dataLayer after consent mode is updated
      if (typeof window.dataLayer !== 'undefined') {
          // Create acceptedServices array for the update event
          const acceptedServices = Object.keys(manager.consents)
              .filter(serviceName => manager.consents[serviceName] === true);
              
          const consentUpdateData = {
              'event': 'Klaro Consent Update',
              'eventSource': 'klaro-geo',
              'consentMode': completeUpdate,
              'acceptedServices': acceptedServices,
              'triggerEvent': eventType
          };
          
          console.log('DEBUG: Pushing klaroConsentUpdate to dataLayer:', consentUpdateData);
          window.dataLayer.push(consentUpdateData);
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
        console.log('DEBUG: Found controls container in document');

        adPersonalizationCheckbox = controlsContainer.querySelector('#klaro-geo-ad-personalization');
        adUserDataCheckbox = controlsContainer.querySelector('#klaro-geo-ad-user-data');

        if (adPersonalizationCheckbox && adUserDataCheckbox) {
            console.log('DEBUG: Found control checkboxes');
        }
    }

    // If still not found, try to find the checkboxes directly
    if (!adPersonalizationCheckbox) {
        adPersonalizationCheckbox = document.querySelector('#klaro-geo-ad-personalization');
        if (adPersonalizationCheckbox) {
            console.log('DEBUG: Found ad personalization checkbox directly');
        }
    }

    if (!adUserDataCheckbox) {
        adUserDataCheckbox = document.querySelector('#klaro-geo-ad-user-data');
        if (adUserDataCheckbox) {
            console.log('DEBUG: Found ad user data checkbox directly');
        }
    }

    if (controlsContainer) {
        if (isServiceEnabled) {
            controlsContainer.classList.remove('klaro-geo-controls-disabled');
            console.log('DEBUG: Removed klaro-geo-controls-disabled class');
        } else {
            controlsContainer.classList.add('klaro-geo-controls-disabled');
            console.log('DEBUG: Added klaro-geo-controls-disabled class');
        }
    }

    if (adPersonalizationCheckbox) {
        const oldState = adPersonalizationCheckbox.checked;
        
        // Always sync the checkbox state with the parent service
        adPersonalizationCheckbox.checked = isServiceEnabled;
        window.adPersonalizationConsent = isServiceEnabled;
        
        // Always update the disabled state
        adPersonalizationCheckbox.disabled = !isServiceEnabled;
        
        console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked, 
            'disabled:', adPersonalizationCheckbox.disabled, 'global consent:', window.adPersonalizationConsent);
    }

    if (adUserDataCheckbox) {
        const oldState = adUserDataCheckbox.checked;
        
        // Always sync the checkbox state with the parent service
        adUserDataCheckbox.checked = isServiceEnabled;
        window.adUserDataConsent = isServiceEnabled;
        
        // Always update the disabled state
        adUserDataCheckbox.disabled = !isServiceEnabled;
        
        console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked, 
            'disabled:', adUserDataCheckbox.disabled, 'global consent:', window.adUserDataConsent);
    }
}

// Function to inject controls for a service element (used by the createServiceListItems override)
// Function to create ad controls for a service element
function createAdControlsForService(serviceElement) {
    console.log('DEBUG: Creating ad controls for service element');

    if (!serviceElement) {
        console.log('DEBUG: Service element is null, cannot create controls');
        return;
    }

    // Find the parent li.cm-service element
    const serviceListItem = serviceElement.closest('li.cm-service');
    if (!serviceListItem) {
        console.log('DEBUG: Could not find parent li.cm-service element');
        return;
    }

    // Check if we already have controls for this service
    const existingControls = document.querySelector('.klaro-geo-ad-controls');
    if (existingControls) {
        console.log('DEBUG: Controls already exist somewhere in the document');
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
              console.log('DEBUG: Ad personalization consent updated to:', isChecked, 
                  '- waiting for save to trigger consent update');
          } else {
              // If parent is disabled, prevent toggling and reset to off state
              console.log('DEBUG: Preventing toggle of ad personalization when parent is disabled');
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
                console.log('DEBUG: Ad user data consent updated to:', isChecked, 
                    '- waiting for save to trigger consent update');
            } else {
                // If parent is disabled, prevent toggling and reset to off state
                console.log('DEBUG: Preventing toggle of ad user data when parent is disabled');
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
        
        console.log('DEBUG: Set ad personalization checkbox state:', isServiceEnabled, 
            'disabled:', !isServiceEnabled, 'global consent:', window.adPersonalizationConsent);
    }
    
    if (adUserDataCheckbox) {
        // Set the checkbox state and disabled state
        adUserDataCheckbox.checked = isServiceEnabled;
        adUserDataCheckbox.disabled = !isServiceEnabled;
        
        // Update the global variable
        window.adUserDataConsent = isServiceEnabled;
        
        console.log('DEBUG: Set ad user data checkbox state:', isServiceEnabled, 
            'disabled:', !isServiceEnabled, 'global consent:', window.adUserDataConsent);
    }

    // Add event listener to the parent checkbox to control child checkboxes
    serviceCheckbox.addEventListener('change', function() {
        const isParentChecked = this.checked;
        console.log('DEBUG: Parent checkbox changed, new state:', isParentChecked);

        // Update child checkboxes and their disabled state
        if (adPersonalizationCheckbox) {
            // Always sync the checkbox state with the parent
            adPersonalizationCheckbox.checked = isParentChecked;
            window.adPersonalizationConsent = isParentChecked;
            
            // Always update disabled state
            adPersonalizationCheckbox.disabled = !isParentChecked;
            console.log('DEBUG: Parent change - updated ad personalization checkbox to:', adPersonalizationCheckbox.checked, 
                'and consent to:', window.adPersonalizationConsent);
        }
        
        if (adUserDataCheckbox) {
            // Always sync the checkbox state with the parent
            adUserDataCheckbox.checked = isParentChecked;
            window.adUserDataConsent = isParentChecked;
            
            // Always update disabled state
            adUserDataCheckbox.disabled = !isParentChecked;
            console.log('DEBUG: Parent change - updated ad user data checkbox to:', adUserDataCheckbox.checked, 
                'and consent to:', window.adUserDataConsent);
        }

        // Log the change but don't trigger a consent update
        // The update will be triggered when the user saves the changes
        console.log('DEBUG: Parent checkbox changed, waiting for save to trigger consent update');
    });

    serviceListItem.parentNode.insertBefore(controlsContainer, serviceListItem.nextSibling);
}

// Function to create a toggle control
function createToggleControl(id, serviceElement, label, description, initialState, onChange) {
    console.log(`DEBUG: Creating toggle control for ${id} with initial state ${initialState}`);
    console.log('DEBUG: serviceElement:', serviceElement);

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

        console.log(`DEBUG: ${id} checkbox changed to:`, this.checked);

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
            console.log('DEBUG: Ignoring click on disabled slider');
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
            console.log('DEBUG: Ignoring click on disabled label');
        }
    });

    // Add click handler to the container to prevent event propagation
    container.addEventListener('click', function(event) {
        event.stopPropagation();
        
        // If the checkbox is disabled, prevent any interaction
        if (checkbox.disabled) {
            event.preventDefault();
            console.log('DEBUG: Preventing interaction with disabled control container');
        }
    });

    return container;
}

   // Function to inject controls for a service element (used by the createServiceListItems override)
    function injectAdControlsForService(serviceElement) {
        console.log('DEBUG: injectAdControlsForService called for element:', serviceElement);
        
        // Check if controls are already injected
        if (controlsInjected) {
            // Double-check if controls actually exist in the DOM
            const existingControls = document.querySelector('.klaro-geo-consent-mode-controls');
            if (existingControls) {
                console.log('DEBUG: Controls already injected and found in DOM, skipping injection');
                return;
            } else {
                console.log('DEBUG: Controls marked as injected but not found in DOM, will try to inject again');
                controlsInjected = false;
            }
        }
        
        // Use the global ad storage service name
        if (!adStorageServiceName || adStorageServiceName === 'no_service') {
            console.log('DEBUG: No ad storage service configured, skipping injection');
            return;
        }
        
        // Make sure we have a valid service element
        if (!serviceElement || !serviceElement.classList.contains('cm-service')) {
            console.log('DEBUG: Invalid service element, skipping injection');
            return;
        }
        
        // Find the service ID from the element
        const serviceId = serviceElement.querySelector('input[type="checkbox"]')?.id;
        if (!serviceId) {
            console.log('DEBUG: Could not find service ID, trying to inject directly');
            
            // Try to inject directly if we can't find the ID
            createAdControlsForService(serviceElement);
            return;
        }
        
        // Extract the service name from the ID (usually in format 'service-item-NAME')
        const serviceIdParts = serviceId.split('-');
        const serviceName = serviceIdParts.length > 2 ? serviceIdParts.slice(2).join('-') : null;
        
        if (!serviceName) {
            console.log('DEBUG: Could not extract service name from ID, trying to inject directly');
            
            // Try to inject directly if we can't extract the service name
            createAdControlsForService(serviceElement);
            return;
        }
        
        // Create the controls directly
        createAdControlsForService(serviceElement);
    }

    function setupModalObserver() {
        console.log('DEBUG: setupModalObserver called');
        
        // Find the Klaro container
        const klaroContainer = document.getElementById('klaro');
        if (!klaroContainer) {
            console.log('DEBUG: Klaro container not found, will try again later');
            setTimeout(setupModalObserver, 500);
            return;
        }
        
        console.log('DEBUG: Found Klaro container, setting up observer');
        
        // Set up the observer
        let modalVisible = false;
        const observer = new MutationObserver(function(mutations) {
            // Check if the modal is now visible
            const modal = document.querySelector('#klaro .cookie-modal');
            if (modal && !modalVisible) {
                modalVisible = true;
                console.log('DEBUG: Modal open detected by MutationObserver');
                
                // Also call our handler directly
                handleModalOpen();
                console.log('DEBUG: Calling handleModalOpen in MutationObserver');
            } else if (!modal && modalVisible) {
                modalVisible = false;
                console.log('DEBUG: Modal close detected by MutationObserver');
            }
        });
        
        // Start observing
        observer.observe(klaroContainer, {
            childList: true,
            subtree: true,
            attributes: true,
            characterData: true
        });
        
        console.log('DEBUG: Modal observer setup complete');
    }

    function handleModalOpen() {
        console.log('DEBUG: handleModalOpen called');
        
        // Set the editing flag to true when the modal opens
        isEditingInModal = true;
        needsConsentUpdate = false;
        console.log('DEBUG: Set isEditingInModal to true');
        
        // Check if the modal is actually visible
        const klaroModal = document.querySelector('.klaro .cookie-modal');
        console.log('DEBUG: Klaro modal found in DOM:', klaroModal ? 'yes' : 'no');
        
        if (!klaroModal) {
            console.log('DEBUG: Modal not found in DOM, will try again later');
            setTimeout(handleModalOpen, 300);
            console.log('DEBUG: handleModalOpen called recursively from documnet.querySelector');
            return;
        }
        
        // Find the save button in the modal
        const saveButton = klaroModal.querySelector('.cm-btn-success') || 
                        klaroModal.querySelector('.cm-btn-accept-all') || 
                        klaroModal.querySelector('button[data-role="accept"]');
        
        if (saveButton) {
            console.log('DEBUG: Found save button in modal:', saveButton);
            
            // Add click event listener to the save button
            saveButton.addEventListener('click', function() {
                console.log('DEBUG: Save button clicked');
                
                // Set the editing flag to false
                isEditingInModal = false;
                
                // If we need to update consent, do it now
                if (needsConsentUpdate) {
                    console.log('DEBUG: Sending deferred consent update after save');
                    setTimeout(function() {
                        updateConsentState(true); // Force update
                    }, 100); // Short delay to allow Klaro to process the changes
                }
            });
        } else {
            console.log('DEBUG: Save button not found in modal');
        }
        
        // Find the close button in the modal
        const closeButton = klaroModal.querySelector('.cm-btn-close') || 
                        klaroModal.querySelector('.cm-btn-decline') || 
                        klaroModal.querySelector('button[data-role="close"]') ||
                        klaroModal.querySelector('button[data-role="decline"]');
        
        if (closeButton) {
            console.log('DEBUG: Found close button in modal:', closeButton);
            
            // Add click event listener to the close button
            closeButton.addEventListener('click', function() {
                console.log('DEBUG: Close button clicked');
                
                // Set the editing flag to false
                isEditingInModal = false;
                
                // We don't send any updates when the modal is closed without saving
                console.log('DEBUG: Modal closed without saving, discarding pending consent updates');
                needsConsentUpdate = false;
            });
        } else {
            console.log('DEBUG: Close button not found in modal');
        }
        
        // Log all services in the modal for debugging
        const services = document.querySelectorAll('li.cm-service');
        console.log('DEBUG: Found', services.length, 'services in the modal');
        
        if (services.length === 0) {
            console.log('DEBUG: No services found in modal, will try again later');
            setTimeout(handleModalOpen, 300);
            console.log('DEBUG: handleModalOpen called recursively after services check');
            return;
        }
        
        services.forEach((service, index) => {
            const title = service.querySelector('.cm-list-title');
            const serviceId = service.querySelector('input[type="checkbox"]')?.id;
            console.log('DEBUG: Service', index, 'title:', title ? title.textContent : 'No title', 'ID:', serviceId || 'No ID');
        });
        
        // Check if controls are already injected
        if (controlsInjected) {
            // Double-check if controls actually exist in the DOM
            const existingControls = document.querySelector('.klaro-geo-consent-mode-controls');
            if (existingControls) {
                console.log('DEBUG: Controls already injected and found in DOM, skipping modal open handler');
                return;
            } else {
                console.log('DEBUG: Controls marked as injected but not found in DOM, will try to inject again');
                controlsInjected = false;
            }
        }

        // Check if we have the consent mode configuration
        if (
            typeof window.klaroConsentData === 'undefined' ||
            !window.klaroConsentData.templateSettings ||
            !window.klaroConsentData.templateSettings.config ||
            !window.klaroConsentData.templateSettings.config.consent_mode_settings
        ) {
            console.log('DEBUG: Consent Mode settings not found in template (modal open)');
            return;
        }

        // Check if consent mode is enabled
        if (!window.klaroConsentData.templateSettings.config.consent_mode_settings.initialize_consent_mode) {
            console.log('DEBUG: Consent Mode not enabled in template (initialize_consent_mode is false) (modal open)');
            console.log('DEBUG: Consent Mode settings (modal open):', window.klaroConsentData.templateSettings.config.consent_mode_settings);
            return;
        }

        console.log('DEBUG: Consent Mode is enabled in template (modal open)');

        // Use the global ad storage service name
        console.log('DEBUG: Ad storage event service name (modal open):', adStorageServiceName);

        if (!adStorageServiceName || adStorageServiceName === 'no_service') {
            console.log('DEBUG: No ad storage event configured (modal open)');
            return;
        }
        
        // Find the service element directly
        let targetService = null;
        
        // First try to find by ID
        const serviceById = document.getElementById('service-item-' + adStorageServiceName);
        if (serviceById) {
            console.log('DEBUG: Found service by ID:', adStorageServiceName);
            targetService = serviceById.closest('li.cm-service');
        }
        
        // If not found by ID, try to find by title
        if (!targetService) {
            console.log('DEBUG: Service not found by ID, trying to find by title');
            
            // Try to find by title based on the service name
            // First, check if adStorageServiceName is valid
            if (!adStorageServiceName) {
                console.log('DEBUG: adStorageServiceName is not defined, cannot search by title');
                return;
            }
            
            // Convert service name to a more readable format (e.g., 'google-ads' -> 'Google Ads')
            const readableServiceName = adStorageServiceName
                .split('-')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
                
            console.log('DEBUG: Looking for service with title:', readableServiceName);
            
            const allServices = document.querySelectorAll('li.cm-service');
            for (let i = 0; i < allServices.length; i++) {
                const title = allServices[i].querySelector('.cm-service-title') || allServices[i].querySelector('.cm-list-title');
                if (title && title.textContent === readableServiceName) {
                    console.log('DEBUG: Found service by exact title match:', readableServiceName);
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
                        console.log('DEBUG: Found service by partial title match:', title.textContent);
                        targetService = allServices[i];
                        break;
                    }
                }
            }
        }
        
        if (!targetService) {
            console.log('DEBUG: Could not find target service in modal, will try again later');
            setTimeout(handleModalOpen, 500);
            console.log('DEBUG: handleModalOpen called recursively with missing targetSerivce');
            return;
        }
        
        console.log('DEBUG: Found target service:', targetService);
        
        // Directly inject controls for the found service
        if (targetService) injectAdControlsForService(targetService);
    }

// Initialize the watcher
setupKlaroDataLayerWatcher();
setupModalObserver();
})();
