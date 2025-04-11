/**
 * Klaro Geo Consent Mode Extension
 *
 * This script adds additional controls for Google Consent Mode v2 parameters
 * (ad_personalization and ad_user_data) when Google Consent Mode is enabled.
 */
(function() {
    // Log when this script is loaded
    console.log('DEBUG: Klaro Geo Consent Mode Extension loaded');
    
    // Store references to consent state
    let adPersonalizationConsent = false;
    let adUserDataConsent = false;
    
    // Global flag to track if we've already injected controls
    let controlsInjected = false;
    
    // Global flag to track if we've set up the watcher
    let watcherSetup = false;
    
    // Global flag to track if gtag is available
    let gtagAvailable = false;
    
    // Global flag to track if we've already set up the consent update
    let consentUpdateSetup = false;
    
    // Counter for retry attempts
    let retryAttempts = 0;
    const MAX_RETRY_ATTEMPTS = 10;
    
    // Flag to track if we're in the initialization phase
    // This helps prevent individual consent updates during initialization
    let isInitializing = true;
    
    // Flag to track if we're in the modal editing mode
    // This helps prevent consent updates while the user is editing settings
    let isEditingInModal = false;
    
    // Flag to track if we need to update consent when the modal is saved
    let needsConsentUpdate = false;
    
    // Store the ad storage service name globally
    let adStorageServiceName = '';
    
    // Store the last consent update to avoid duplicates
    let lastConsentUpdate = null;
    let pendingConsentUpdate = false;
    
    // Debounce timer for consent updates
    let consentUpdateTimer = null;
    let consentUpdateDelay = 50; // ms
    
    // Function to safely update consent with gtag
    function handleConsentUpdate(consentUpdate) {
        // Skip if gtag is not available
        if (typeof window.gtag !== 'function') {
            console.log('DEBUG: gtag not available, skipping consent update');
            return;
        }
        
        // Skip if the update is identical to the last one
        if (lastConsentUpdate && 
            JSON.stringify(lastConsentUpdate) === JSON.stringify(consentUpdate)) {
            console.log('DEBUG: Skipping duplicate consent update');
            return;
        }
        
        // Clear any pending update timer
        if (consentUpdateTimer) {
            console.log('DEBUG: Clearing pending consent update timer');
            clearTimeout(consentUpdateTimer);
        }
        
        // Set a new timer to debounce multiple rapid updates
        console.log('DEBUG: Setting debounced consent update timer');
        consentUpdateTimer = setTimeout(function() {
            // Get the current state of all services from Klaro manager
            let adServiceEnabled = false;
            let analyticsServiceEnabled = false;
            
            // Try to get the current state from the Klaro manager
            try {
                if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
                    const manager = window.klaro.getManager();
                    if (manager && manager.consents) {
                        // Check ad service state
                        if (adStorageServiceName) {
                            adServiceEnabled = manager.consents[adStorageServiceName] === true;
                        }
                        
                        // Check analytics service state
                        const analyticsServiceName = window.klaroConsentData?.templateSettings?.config?.consent_mode_settings?.analytics_storage_service;
                        if (analyticsServiceName && analyticsServiceName !== 'no_service') {
                            analyticsServiceEnabled = manager.consents[analyticsServiceName] === true;
                        }
                    }
                }
            } catch (e) {
                console.error('DEBUG: Error getting consent state from Klaro manager:', e);
                // Fall back to the provided update
                adServiceEnabled = consentUpdate.ad_storage === 'granted';
                analyticsServiceEnabled = consentUpdate.analytics_storage === 'granted';
            }
            
            // Create a complete update with the current state
            const completeUpdate = {
                'ad_storage': adServiceEnabled ? 'granted' : 'denied',
                'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
                'ad_user_data': (adServiceEnabled && adUserDataConsent) ? 'granted' : 'denied',
                'ad_personalization': (adServiceEnabled && adPersonalizationConsent) ? 'granted' : 'denied'
            };
            
            // Store this update as the last one
            lastConsentUpdate = completeUpdate;
            
            // Send the update to gtag
            window.gtag('consent', 'update', completeUpdate);
            console.log('DEBUG: Consent state updated with:', completeUpdate);
            
            // Reset the timer
            consentUpdateTimer = null;
        }, consentUpdateDelay);
    }
/*    
    // Override the global handleConsentUpdate function if it exists
    // This ensures all consent updates go through our safeUpdateConsent function
    if (typeof window.handleConsentUpdate === 'function') {
        console.log('DEBUG: Overriding global handleConsentUpdate function');
        
        // Store the original function
        const originalHandleConsentUpdate = window.handleConsentUpdate;
        
        // Replace it with our version that uses safeUpdateConsent
        window.handleConsentUpdate = function(type, granted) {
            console.log('DEBUG: handleConsentUpdate called for', type, granted);
            
            // If we're initializing (not a user action), collect the updates but don't send them yet
            if (isInitializing) {
                // Create a complete update with all signals
                const completeUpdate = {
                    'ad_storage': type === 'ad_storage' ? (granted ? 'granted' : 'denied') : (lastConsentUpdate?.ad_storage || 'denied'),
                    'analytics_storage': type === 'analytics_storage' ? (granted ? 'granted' : 'denied') : (lastConsentUpdate?.analytics_storage || 'denied'),
                    'ad_user_data': type === 'ad_user_data' ? (granted ? 'granted' : 'denied') : (lastConsentUpdate?.ad_user_data || 'denied'),
                    'ad_personalization': type === 'ad_personalization' ? (granted ? 'granted' : 'denied') : (lastConsentUpdate?.ad_personalization || 'denied')
                };
                
                // Store this update as the last one
                lastConsentUpdate = completeUpdate;
                
                // Don't send the update yet - it will be sent by updateConsentState
                console.log('DEBUG: Collected consent update for', type, 'but not sending yet');
                return;
            }
            
            // For user actions, update immediately
            // Create an update object with just this signal
            const singleUpdate = {
                [type]: granted ? 'granted' : 'denied'
            };
            
            // Use our safe update function to ensure all signals are included
            safeUpdateConsent(singleUpdate);
        };
    }
*/
    // Create a custom event for when the modal opens
    const modalOpenEvent = document.createEvent('Event');
    modalOpenEvent.initEvent('klaroModalOpen', true, true);
    
    // Set up a MutationObserver to detect when the Klaro modal is opened
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

    // Polling function to check for Klaro readiness
    const waitForKlaro = function() {
        console.log('DEBUG: Checking for Klaro...');

        // Try different ways to access Klaro (adjust as needed)
        const klaroAccess = window.Klaro || window.klaro; // Try both variations

        if (klaroAccess && typeof klaroAccess === 'object') {
            console.log('DEBUG: Klaro is ready, attempting to initialize');
            
            // Instead of trying to hook into a non-existent function, let's initialize our extension
            try {
                // Initialize the consent mode extension
                initConsentModeExtension();

                // Set up the modal observer
                setupModalObserver();
                
                console.log('DEBUG: Successfully initialized Klaro extension');
                return; // Stop polling if successful
            } catch (error) {
                console.error('DEBUG: Error initializing Klaro extension:', error);
                console.log('DEBUG: Retrying to initialize Klaro');
                setTimeout(waitForKlaro, 100); // Retry after a delay
            }
        } else {
            console.log('DEBUG: Klaro not fully ready, retrying');
            setTimeout(waitForKlaro, 100);
        }
    };

    // Log the current state of gtag
    console.log('DEBUG: On script load - gtag defined:', typeof window.gtag !== 'undefined');
    if (typeof window.gtag !== 'undefined') {
        console.log('DEBUG: gtag is a function:', typeof window.gtag === 'function');
    }
    
    // Start the polling for Klaro
    waitForKlaro();
    
    // Function to initialize the consent mode extension
    function initConsentModeExtension() {
        console.log('DEBUG: initConsentModeExtension called');
      
        if (window.klaroGeo && window.klaroGeo.manager) {
          console.log('DEBUG: Klaro manager already available in klaroGeo namespace');
          initializeExtension(window.klaroGeo.manager);
          return;
        }
      
        document.addEventListener('klaro-manager-ready', function(event) {
          console.log('DEBUG: Received klaro-manager-ready event');
          if (event.detail && event.detail.manager) {
            console.log('DEBUG: Got manager from event');
            initializeExtension(event.detail.manager);
          }
        });
      
        console.log('DEBUG: Global Klaro manager handler not available, using polling');
      
        retryAttempts = 0;
      
        const checkKlaroLoaded = function() {
          retryAttempts++;
          console.log('DEBUG: Checking if Klaro is loaded... (attempt ' + retryAttempts + ' of ' + MAX_RETRY_ATTEMPTS + ')');
      
          if (retryAttempts > MAX_RETRY_ATTEMPTS) {
            console.log('DEBUG: Maximum retry attempts exceeded. Proceeding with initialization anyway.');
            try {
              const manager = window.klaro?.getManager?.(); // Safer chaining
              if (manager) {
                initializeExtension(manager);
              } else {
                console.log('DEBUG: Proceeding without Klaro manager');
                initializeExtension(null);
              }
            } catch (e) {
              console.error('DEBUG: Error during fallback initialization:', e);
              initializeExtension(null);
            }
            return;
          }
      
          if (window.klaro && typeof window.klaro.getManager === 'function') {
            try {
              const manager = window.klaro.getManager();
              if (manager && manager.consents) {
                console.log('DEBUG: Klaro manager and consents are available');
                initializeExtension(manager);
                return;
              } else {
                console.log('DEBUG: Manager or consents not ready yet');
              }
            } catch (e) {
              console.error('DEBUG: Error getting Klaro manager:', e);
            }
          } else {
            console.log('DEBUG: window.klaro or getManager not defined yet');
          }
          setTimeout(checkKlaroLoaded, 100);
        };
      
        checkKlaroLoaded();
      }
    
    // Function to initialize the extension once Klaro is loaded
    function initializeExtension(manager) {
        console.log('DEBUG: Initializing extension with Klaro manager:', manager ? 'available' : 'not available');
        
        // Load saved sub-control states
        const statesLoaded = loadSubControlStates();
        console.log('DEBUG: Sub-control states loaded:', statesLoaded);
        
        // Set up a watcher for consent changes if not already done and manager is available
        if (!watcherSetup && manager) {
            setupConsentWatcher(manager);
        } else if (!manager) {
            console.log('DEBUG: Skipping consent watcher setup because manager is not available');
        }
        
        // Apply the current consent state after a delay to ensure everything is ready
        // Use forceUpdate=true to ensure it fires even if there's a pending update
        setTimeout(function() {
            console.log('DEBUG: Applying initial consent state');
            updateConsentState(true); // Force update on initialization
        }, 500);
        
        // Debug the consent data
        console.log('DEBUG: Klaro Consent Data:', window.klaroConsentData);

        // Check if we have the consent mode configuration
        if (
            typeof window.klaroConsentData === 'undefined' ||
            !window.klaroConsentData.templateSettings ||
            !window.klaroConsentData.templateSettings.config ||
            !window.klaroConsentData.templateSettings.config.consent_mode_settings
        ) {
            console.log('DEBUG: Consent Mode settings not found in template');
            return;
        }

        // Check if consent mode is enabled
        if (!window.klaroConsentData.templateSettings.config.consent_mode_settings.initialize_consent_mode) {
            console.log('DEBUG: Consent Mode not enabled in template (initialize_consent_mode is false)');
            console.log('DEBUG: Consent Mode settings:', window.klaroConsentData.templateSettings.config.consent_mode_settings);
            return;
        }

        console.log('DEBUG: Consent Mode is enabled in template');

        // Get the ad storage service name and store it globally
        adStorageServiceName = 
            window.klaroConsentData.templateSettings.config.consent_mode_settings.ad_storage_service;
        
        console.log('DEBUG: Ad storage service name:', adStorageServiceName);

        if (!adStorageServiceName || adStorageServiceName === 'no_service') {
            console.log('DEBUG: No ad storage service configured');
            return;
        }
        
        // Check if the ad service is enabled in Klaro
        let adServiceEnabled = false;
        let analyticsServiceEnabled = false;
        
        if (manager && manager.consents) {
            // Check ad service state
            adServiceEnabled = manager.consents[adStorageServiceName] === true;
            console.log('DEBUG: Ad service enabled in Klaro:', adServiceEnabled);
            
            // Check analytics service state if configured
            const analyticsServiceName = window.klaroConsentData?.templateSettings?.config?.consent_mode_settings?.analytics_storage_service;
            if (analyticsServiceName && analyticsServiceName !== 'no_service') {
                analyticsServiceEnabled = manager.consents[analyticsServiceName] === true;
                console.log('DEBUG: Analytics service enabled in Klaro:', analyticsServiceEnabled);
            }
            
            // Note: We don't directly update the consent state here anymore.
            // Instead, we rely on the updateConsentState() call that happens after a delay
            // in the initializeExtension function.
        }

        console.log('DEBUG: Initialized for service ' + adStorageServiceName);
        // We don't try to inject controls here anymore - we'll wait for the show() method to be called
    }
    
    // Function to set up the consent watcher
    function setupConsentWatcher(manager) {
        console.log('DEBUG: Setting up consent watcher');
        
        // Check if manager is valid
        if (!manager) {
            console.log('DEBUG: Cannot set up consent watcher - manager is null');
            return;
        }
        
        // Check if watch method exists
        if (typeof manager.watch !== 'function') {
            console.log('DEBUG: Cannot set up consent watcher - manager.watch is not a function');
            return;
        }
        
        try {
            // Set up a watcher for consent changes
            manager.watch({
                update: function(obj, name, data) {
                    try {
                        // Only care about consent updates
                        if (name === 'consents') {
                            console.log('DEBUG: Consent state changed via manager.watch():', data);
                            
                            // Create a single consolidated update for all services
                            // This prevents multiple updates when multiple services change
                            
                            // Get the current state of all services
                            let adServiceEnabled = false;
                            let analyticsServiceEnabled = false;
                            
                            // Check ad service state
                            if (adStorageServiceName && typeof data[adStorageServiceName] !== 'undefined') {
                                adServiceEnabled = data[adStorageServiceName] === true;
                                console.log('DEBUG: Ad service enabled in consent change:', adServiceEnabled);
                            }
                            
                            // Check analytics service state
                            const analyticsServiceName = window.klaroConsentData?.templateSettings?.config?.consent_mode_settings?.analytics_storage_service;
                            if (analyticsServiceName && analyticsServiceName !== 'no_service' && typeof data[analyticsServiceName] !== 'undefined') {
                                analyticsServiceEnabled = data[analyticsServiceName] === true;
                                console.log('DEBUG: Analytics service enabled in consent change:', analyticsServiceEnabled);
                            }
                            
                            // Update the UI controls for the ad service if needed
                            if (adStorageServiceName && typeof data[adStorageServiceName] !== 'undefined') {
                                // Find the service element
                                const serviceElement = document.getElementById('service-item-' + adStorageServiceName);
                                if (serviceElement) {
                                    const serviceListItem = serviceElement.closest('li.cm-service');
                                    if (serviceListItem) {
                                        // Update the UI controls without triggering a consent update
                                        // We'll do a single consolidated update after all UI changes
                                        updateControlsUI(serviceListItem, adServiceEnabled);
                                    }
                                }
                            }
                            
                            // If we're in the modal editing mode, defer the update
                            if (isEditingInModal) {
                                console.log('DEBUG: In modal editing mode, deferring consent update from watcher until save');
                                needsConsentUpdate = true;
                                return;
                            }
                            
                            // Now send a single consolidated consent update
                            const consentUpdate = {
                                'ad_storage': adServiceEnabled ? 'granted' : 'denied',
                                'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
                                'ad_user_data': (adServiceEnabled && adUserDataConsent) ? 'granted' : 'denied',
                                'ad_personalization': (adServiceEnabled && adPersonalizationConsent) ? 'granted' : 'denied'
                            };
                            
                            // Send the update
                            handleConsentUpdate(consentUpdate);
                        }
                    } catch (e) {
                        console.error('DEBUG: Error in consent watcher update callback:', e);
                    }
                }
            });
            
            // Mark that we've set up the watcher
            watcherSetup = true;
            console.log('DEBUG: Consent watcher set up successfully');
        } catch (e) {
            console.error('DEBUG: Error setting up consent watcher:', e);
        }
    }
    
    // Function to update just the UI controls without triggering a consent update
    function updateControlsUI(serviceListItem, isServiceEnabled) {
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
            adPersonalizationCheckbox.checked = isServiceEnabled;
            adPersonalizationConsent = isServiceEnabled;
            console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked);
        }
        
        if (adUserDataCheckbox) {
            const oldState = adUserDataCheckbox.checked;
            adUserDataCheckbox.checked = isServiceEnabled;
            adUserDataConsent = isServiceEnabled;
            console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked);
        }
    }
    
    // Function to update controls based on consent state
    function updateControlsFromConsent(serviceListItem, isServiceEnabled) {
        // Update the UI controls
        updateControlsUI(serviceListItem, isServiceEnabled);
        
        updateConsentState(true);
    }
    
    // Function to handle when the modal is opened
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
            
            // Try to find by title (for Google Ads specifically)
            if (adStorageServiceName === 'google-ads') {
                const allServices = document.querySelectorAll('li.cm-service');
                for (let i = 0; i < allServices.length; i++) {
                    const title = allServices[i].querySelector('.cm-service-title') || allServices[i].querySelector('.cm-list-title');
                    if (title && title.textContent === 'Google Ads') {
                        console.log('DEBUG: Found Google Ads service by title');
                        targetService = allServices[i];
                        break;
                    }
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
        injectAdControlsForService(targetService);
        
        // Also try the standard injection as a fallback
        if (!controlsInjected) {
            console.log('DEBUG: Attempting standard injection as fallback');
            injectAdControls(adStorageServiceName);
        }
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
        
        // Check if this is the service we want to inject controls for
        const title = serviceElement.querySelector('.cm-list-title')?.textContent || '';
        
        if (serviceName !== adStorageServiceName && 
            !title.toLowerCase().includes('google ads') && 
            !title.toLowerCase().includes(adStorageServiceName.toLowerCase())) {
            console.log('DEBUG: Service', serviceName, 'does not match configured service', adStorageServiceName, ', skipping injection');
            return;
        }
        
        console.log('DEBUG: Found matching service for injection:', serviceName, 'with title:', title);
        
        // Create the controls directly
        createAdControlsForService(serviceElement);
    }
    
    // Function to create a toggle control
    function createToggleControl(id, label, description, initialState, onChange) {
        console.log(`DEBUG: Creating toggle control for ${id} with initial state ${initialState}`);
        
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
        
        // Create the slider (knob is added via CSS ::after)
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
                onChange(this.checked);
            }
        });
        
        // Add click handler to the slider to toggle the checkbox
        slider.addEventListener('click', function(event) {
            // Stop propagation to prevent Klaro from capturing the event
            event.stopPropagation();
            
            // Toggle the checkbox
            checkbox.checked = !checkbox.checked;
            
            // Trigger the change event
            const changeEvent = new Event('change');
            checkbox.dispatchEvent(changeEvent);
        });
        
        // Add click handler to the label to toggle the checkbox
        labelElement.addEventListener('click', function(event) {
            // Stop propagation to prevent Klaro from capturing the event
            event.stopPropagation();
            
            // Toggle the checkbox
            checkbox.checked = !checkbox.checked;
            
            // Trigger the change event
            const changeEvent = new Event('change');
            checkbox.dispatchEvent(changeEvent);
        });
        
        // Add click handler to the container to prevent event propagation
        container.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        
        return container;
    }

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
            controlsInjected = true;
            return;
        }
        
        // Get the current state of the service
        const serviceCheckbox = serviceListItem.querySelector('input[type="checkbox"]');
        const isServiceEnabled = serviceCheckbox ? serviceCheckbox.checked : false;
        
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
        heading.textContent = 'Google Consent Mode v2 Settings';
        controlsContainer.appendChild(heading);
        
        // Create the description
        const description = document.createElement('div');
        description.className = 'klaro-geo-description';
        description.textContent = 'These settings control how your data is used by Google for ads.';
        controlsContainer.appendChild(description);

        // Create the ad personalization control
        const adPersonalizationControl = createToggleControl(
            'ad-personalization',
            'Ad Personalization',
            'Allow Google to show you personalized ads',
            isServiceEnabled && adPersonalizationConsent,
            function(isChecked) {
                adPersonalizationConsent = isChecked;
                
                // If this is being turned on, make sure the parent is also on
                if (isChecked && serviceCheckbox && !serviceCheckbox.checked) {
                    // This shouldn't happen due to the disabled state, but just in case
                    console.log('DEBUG: Child control enabled while parent is disabled, enabling parent');
                    serviceCheckbox.checked = true;
                    
                    // Trigger change event on the parent
                    const changeEvent = new Event('change');
                    serviceCheckbox.dispatchEvent(changeEvent);
                }
                
                //saveSubControlStates();
                //console.log('DEBUG: saveSubControlStates called in createAdControlsForService 1');
                updateConsentState(true); // Force update on user action
            }
        );
        controlsContainer.appendChild(adPersonalizationControl);
        
        // Create the ad user data control
        const adUserDataControl = createToggleControl(
            'ad-user-data',
            'Ad User Data',
            'Allow Google to use your data for ad measurement',
            isServiceEnabled && adUserDataConsent,
            function(isChecked) {
                adUserDataConsent = isChecked;
                
                // If this is being turned on, make sure the parent is also on
                if (isChecked && serviceCheckbox && !serviceCheckbox.checked) {
                    // This shouldn't happen due to the disabled state, but just in case
                    console.log('DEBUG: Child control enabled while parent is disabled, enabling parent');
                    serviceCheckbox.checked = true;
                    
                    // Trigger change event on the parent
                    const changeEvent = new Event('change');
                    serviceCheckbox.dispatchEvent(changeEvent);
                }
                
                //saveSubControlStates();
                //console.log('DEBUG: saveSubControlStates called in createAdControlsForService 2');
                updateConsentState(true); // Force update on user action
            }
        );
        controlsContainer.appendChild(adUserDataControl);
        
        // Find the service description element to inject our controls after
        const serviceDescription = serviceListItem.querySelector('#service-item-google-ads-description') || 
                                  serviceListItem.querySelector('[id$="-description"]');
        
        if (serviceDescription) {
            // Insert the controls after the service description
            serviceDescription.appendChild(controlsContainer);
            console.log('DEBUG: Controls injected into service description');
        } else {
            // Fallback: append to the service list item
            serviceListItem.appendChild(controlsContainer);
            console.log('DEBUG: Controls injected into service list item (fallback)');
        }
        
        // Set the global flag
        controlsInjected = true;
        
        console.log('DEBUG: Ad controls successfully injected');
        
        // Add event listener to the parent service checkbox
        if (serviceCheckbox) {
            console.log('DEBUG: Adding event listener to parent service checkbox');
            
            serviceCheckbox.addEventListener('change', function() {
                console.log('DEBUG: Parent service checkbox changed to:', this.checked);
                
                // Get references to the child checkboxes
                const adPersonalizationCheckbox = controlsContainer.querySelector('#klaro-geo-ad-personalization');
                const adUserDataCheckbox = controlsContainer.querySelector('#klaro-geo-ad-user-data');
                
                // Update the container disabled state
                if (this.checked) {
                    controlsContainer.classList.remove('klaro-geo-controls-disabled');
                    
                    // When parent is checked, restore previous state of children
                    // or keep them unchecked if they were unchecked before
                    if (adPersonalizationCheckbox) {
                        // Don't trigger change event yet
                        adPersonalizationCheckbox.checked = adPersonalizationConsent;
                    }
                    
                    if (adUserDataCheckbox) {
                        // Don't trigger change event yet
                        adUserDataCheckbox.checked = adUserDataConsent;
                    }
                    
                    // No need to manually update visual appearance as CSS handles it
                } else {
                    controlsContainer.classList.add('klaro-geo-controls-disabled');
                    
                    // If the parent is unchecked, also uncheck the child controls
                    if (adPersonalizationCheckbox) {
                        adPersonalizationCheckbox.checked = false;
                        
                        // No need to manually update visual appearance as CSS handles it
                    }
                    
                    if (adUserDataCheckbox) {
                        adUserDataCheckbox.checked = false;
                        // No need to manually update visual appearance as CSS handles it
                    }
                    
                    // Update the global state
                    adPersonalizationConsent = false;
                    adUserDataConsent = false;
                    
                    // Save the sub-control states
                    //saveSubControlStates();
                    //console.log('DEBUG: saveSubControlStates called in createAdControlsForService 3');
                }
                
                // Update the consent state
                updateConsentState(true); // Force update on user action
            });
        }
    }

       // Create the container for the additional controls
       const controlsContainer = document.createElement('div');
       controlsContainer.className = 'klaro-geo-ad-controls';

       // Get the current consent state from Klaro's manager
       let isServiceEnabled = false;

       // Try to get the consent state from Klaro's manager
       if (window.klaro && typeof window.klaro.getManager === 'function') {
           try {
               // Get the manager without using gtag
               let manager = null;
               try {
                   // Use a safer approach to get the manager
                   const getManagerFn = window.klaro.getManager;
                   manager = getManagerFn.call(window.klaro);
                   console.log('DEBUG: Got manager using safer approach in injectAdControls');
               } catch (e) {
                   console.error('DEBUG: Error getting manager with safer approach in injectAdControls:', e);
                   // Fall back to direct call
                   try {
                       manager = window.klaro.getManager();
                       console.log('DEBUG: Got manager using direct call in injectAdControls');
                   } catch (e2) {
                       console.error('DEBUG: Error getting manager with direct call in injectAdControls:', e2);
                   }
               }

               if (manager && manager.consents) {
                   isServiceEnabled = manager.consents[adStorageServiceName] === true;
                   console.log('DEBUG: Got consent state from Klaro manager for', adStorageServiceName, ':', isServiceEnabled);

                   // Set up the watcher if not already done
                   if (!watcherSetup) {
                       setupConsentWatcher(manager);
                   }
               } else {
                   console.log('DEBUG: Klaro manager or consents not available during injection, using default state');
                   // Continue with default state
               }
           } catch (e) {
               console.error('DEBUG: Error getting Klaro manager during injection:', e);
               // Continue with default state
           }
       } else {
           console.log('DEBUG: Klaro or getManager not available during injection, using default state');
           // Continue with default state
       }

       // Initialize the sub-controls to match the main service state
       adPersonalizationConsent = isServiceEnabled;
       adUserDataConsent = isServiceEnabled;

       // Add or remove the disabled class based on the service state
       if (isServiceEnabled) {
           controlsContainer.classList.remove('klaro-geo-controls-disabled');
       } else {
           controlsContainer.classList.add('klaro-geo-controls-disabled');
       }

   // Function to create a sub-control
   function createSubControl(id, title, description, initialState, onChange) {
    console.log(`DEBUG: Creating sub-control for ${id} with initial state:`, initialState);

    const container = document.createElement('div');
    container.className = 'cm-service klaro-geo-sub-control';
    console.log(`DEBUG: Created container for ${id} with class:`, container.className);

    // Create the control HTML
    container.innerHTML = `
        <div class="cm-service-header">
            <input id="klaro-geo-${id}" type="checkbox" class="cm-list-input" <span class="math-inline">\{
initialState ? 'checked' \: ''
\}\>
<label for\="klaro\-geo\-</span>{id}" class="cm-list-label">
                <span class="cm-list-title"><span class="math-inline">\{title\}</span\>
<span class\="cm\-switch"\><div class\="slider round"\></div\></span\>
</label\>
<div class\="cm\-service\-description"\>
<p class\="purposes"\></span>{description}</p>
            </div>
        </div>
    `;
    console.log(`DEBUG: Set HTML for ${id} control`);

    // Get the checkbox and slider elements
    const checkbox = container.querySelector(`input#klaro-geo-${id}`);
    const slider = container.querySelector('.slider');

    console.log(`DEBUG: Found checkbox for ${id}:`, checkbox ? true : false);
    console.log(`DEBUG: Found slider for ${id}:`, slider ? true : false);

    // Add event listener to the checkbox
    if (checkbox) {
        // Set initial state
        checkbox.checked = initialState;
        console.log(`DEBUG: Set initial checkbox state for ${id} to:`, checkbox.checked);

        // Let's not add any custom click handlers - let Klaro handle the clicks
        console.log(`DEBUG: Not adding custom click handlers - letting Klaro handle clicks`);

        // Add change event listener to the checkbox
        console.log(`DEBUG: Adding change listener to checkbox for ${id}`);
        checkbox.addEventListener('change', function(e) {
            console.log(`DEBUG: Checkbox for ${id} change event triggered`);
            console.log(`DEBUG: Event:`, e);
            console.log(`DEBUG: Checkbox ${id} changed to:`, this.checked);

            // Ensure the global consent state is updated
            if (id === 'ad-personalization') {
                adPersonalizationConsent = this.checked;
                console.log(`DEBUG: Updated adPersonalizationConsent to:`, adPersonalizationConsent);
            } else if (id === 'ad-user-data') {
                adUserDataConsent = this.checked;
                console.log(`DEBUG: Updated adUserDataConsent to:`, adUserDataConsent);
            }

            // Call the onChange callback
            if (onChange) {
                console.log(`DEBUG: Calling onChange callback for ${id}`);
                onChange(this.checked);
            }
        });
    } else {
        console.log(`DEBUG: Could not find checkbox for ${id}`);
    }

    console.log(`DEBUG: Returning container for ${id}`);
    return container;
}

    // Function to save sub-control states using localStorage
    function saveSubControlStates() {
        console.log('DEBUG: Saving sub-control states');
        
        try {
            // Create a custom storage key for our sub-controls
            const storageKey = 'klaro-geo-sub-controls';
            
            // Create an object with our sub-control states
            const subControlStates = {
                adPersonalizationConsent: adPersonalizationConsent,
                adUserDataConsent: adUserDataConsent
            };
            
            // Save to localStorage
            try {
                localStorage.setItem(storageKey, JSON.stringify(subControlStates));
                console.log('DEBUG: Saved sub-control states to localStorage:', subControlStates);
                return true;
            } catch (e) {
                console.error('DEBUG: Error saving to localStorage:', e);
            }
        } catch (e) {
            console.error('DEBUG: Error saving sub-control states:', e);
        }
        
        return false;
    }
    
    // Function to load sub-control states
    function loadSubControlStates() {
        console.log('DEBUG: Loading sub-control states');
        
        try {
            // Try to load from localStorage
            const storageKey = 'klaro-geo-sub-controls';
            
            try {
                const storedValue = localStorage.getItem(storageKey);
                if (storedValue) {
                    const subControlStates = JSON.parse(storedValue);
                    console.log('DEBUG: Loaded sub-control states from localStorage:', subControlStates);
                    
                    // Apply the loaded states
                    if (typeof subControlStates.adPersonalizationConsent === 'boolean') {
                        adPersonalizationConsent = subControlStates.adPersonalizationConsent;
                    }
                    
                    if (typeof subControlStates.adUserDataConsent === 'boolean') {
                        adUserDataConsent = subControlStates.adUserDataConsent;
                    }
                    
                    console.log('DEBUG: Applied loaded sub-control states - adPersonalizationConsent:', 
                        adPersonalizationConsent, 'adUserDataConsent:', adUserDataConsent);
                    return true;
                }
            } catch (e) {
                console.error('DEBUG: Error loading from localStorage:', e);
            }
        } catch (e) {
            console.error('DEBUG: Error loading sub-control states:', e);
        }
        
        console.log('DEBUG: No saved sub-control states found, using defaults');
        return false;
    }

    // Function to update the consent state
    function updateConsentState(forceUpdate = false) {
        // If we already have a pending update, don't schedule another one unless forced
        if (pendingConsentUpdate && !forceUpdate) {
            console.log('DEBUG: Consent update already pending, skipping');
            return;
        }
        
        // Save the sub-control states
        saveSubControlStates();
        console.log('DEBUG: saveSubControlStates called in updateConsentState');
        
        // If we're in the modal editing mode and not forcing an update, defer the update
        if (isEditingInModal && !forceUpdate && !isInitializing) {
            console.log('DEBUG: In modal editing mode, deferring consent update until save');
            needsConsentUpdate = true;
            return;
        }
        
        // Mark that we have a pending update
        pendingConsentUpdate = true;
        
        // Get the current state of the ad service from Klaro manager
        let adServiceEnabled = false;
        let analyticsServiceEnabled = false;
        
        try {
            if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
                const manager = window.klaro.getManager();
                
                if (manager && manager.consents) {
                    // Check ad service state
                    adServiceEnabled = manager.consents[adStorageServiceName] === true;
                    
                    // Check analytics service state if configured
                    const analyticsServiceName = window.klaroConsentData?.templateSettings?.config?.consent_mode_settings?.analytics_storage_service;
                    if (analyticsServiceName && analyticsServiceName !== 'no_service') {
                        analyticsServiceEnabled = manager.consents[analyticsServiceName] === true;
                    }
                }
            }
        } catch (e) {
            console.error('DEBUG: Error getting consent state from Klaro manager:', e);
        }
        
        // Create a complete consent update with all signals
        const consentUpdate = {
            'ad_storage': adServiceEnabled ? 'granted' : 'denied',
            'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
            'ad_user_data': (adServiceEnabled && adUserDataConsent) ? 'granted' : 'denied',
            'ad_personalization': (adServiceEnabled && adPersonalizationConsent) ? 'granted' : 'denied'
        };
        
        // Send the update through our debounced function
        handleConsentUpdate(consentUpdate);
        
        // Mark that we're done with initialization
        if (isInitializing) {
            console.log('DEBUG: Initialization complete, allowing individual consent updates for user actions');
            isInitializing = false;
            consentUpdateSetup = true;
        }
        
        // Reset the needs update flag
        needsConsentUpdate = false;
        
        // Clear the pending flag after a short delay
        setTimeout(function() {
            pendingConsentUpdate = false;
        }, consentUpdateDelay + 10); // Slightly longer than the debounce delay
    }

    // Log that the script has loaded
    console.log('DEBUG: Klaro Geo Consent Mode Extension script loaded');
})();