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
    // Counter for retry attempts
    let retryAttempts = 0;
    const MAX_RETRY_ATTEMPTS = 10;


    // Function to safely apply initialization code
    function safelyApplyInitializationCode() {
        console.log('DEBUG: Safely applying initialization code function is now deprecated');
        console.log('DEBUG: Initialization code is now directly included in the onInit callback of the Google Tag Manager service');
        
        // This function is now deprecated as the initialization code is directly included in the onInit callback
        // We're keeping it for backward compatibility, but it doesn't do anything anymore
        return;
    }

    
    // Save the original klaro.show() method
    let originalKlaroShow = null;

    // Function to hook into klaro.show()
    function hookKlaroShow() {
        console.log('DEBUG: Hooking into klaro.show()');
        
        // Check if klaro.show() is available
        if (window.klaro && typeof window.klaro.show === 'function') {
            originalKlaroShow = window.klaro.show; // Save the original
            
            // Override klaro.show()
            window.klaro.show = function(config, modal) {
                console.log('DEBUG: Overridden klaro.show() called');
                
                // Call the original klaro.show() with the provided arguments
                originalKlaroShow.apply(this, arguments);
                
                // Use setTimeout to ensure DOM is ready
                setTimeout(() => {
                    console.log('DEBUG: Running logic after klaro.show()');
                    
                    // Check if Klaro manager is available
                    if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
                        const manager = window.klaro.getManager();
                        if (manager) {
                            // If controls aren't injected yet, try to inject them
                            if (!controlsInjected) {
                                console.log('DEBUG: Controls not yet injected, calling handleModalOpen()');
                                handleModalOpen(manager);
                            } else {
                                console.log('DEBUG: Controls already injected, skipping handleModalOpen()');
                            }
                        }
                    }
                }, 0); // A 0ms delay is usually sufficient
            };
            
            console.log('DEBUG: klaro.show() hooked successfully');
            return true;
        } else {
            console.log('DEBUG: klaro.show() not available to hook');
            return false;
        }
    }

    // Function to initialize the consent mode extension
    function initConsentModeExtension() {
        console.log('DEBUG: initConsentModeExtension called');

        // Try to safely apply initialization code
        safelyApplyInitializationCode();
        
        // Check if the manager is already available in the klaroGeo namespace
        if (window.klaroGeo && window.klaroGeo.manager) {
            console.log('DEBUG: Klaro manager already available in klaroGeo namespace');
            initializeExtension(window.klaroGeo.manager);
            
            // Try to hook into klaro.show()
            hookKlaroShow();
            return;
        }
        
        // Listen for the klaro-manager-ready event
        document.addEventListener('klaro-manager-ready', function(event) {
            console.log('DEBUG: Received klaro-manager-ready event');
            if (event.detail && event.detail.manager) {
                console.log('DEBUG: Got manager from event');
                initializeExtension(event.detail.manager);
                
                // Try to hook into klaro.show()
                hookKlaroShow();
            }
        });
        
        // Fall back to our own polling mechanism if the global handler isn't available
        console.log('DEBUG: Global Klaro manager handler not available, using polling');

        // Reset retry counter
        retryAttempts = 0;

        // Set up a polling mechanism to check when Klaro is loaded
        const checkKlaroLoaded = function() {
            // Increment retry counter
            retryAttempts++;
            
            console.log('DEBUG: Checking if Klaro is loaded... (attempt ' + retryAttempts + ' of ' + MAX_RETRY_ATTEMPTS + ')');
            
            // Check if we've exceeded the maximum number of retry attempts
            if (retryAttempts > MAX_RETRY_ATTEMPTS) {
                console.log('DEBUG: Maximum retry attempts exceeded. Proceeding with initialization anyway.');
                
                // Try to initialize with whatever we have
                try {
                    if (window.klaro && typeof window.klaro.getManager === 'function') {
                        const manager = window.klaro.getManager();
                        if (manager) {
                            initializeExtension(manager);
                            
                            // Try to hook into klaro.show()
                            hookKlaroShow();
                        } else {
                            console.log('DEBUG: Proceeding without Klaro manager');
                            initializeExtension(null);
                        }
                    } else {
                        console.log('DEBUG: Proceeding without Klaro manager');
                        initializeExtension(null);
                    }
                } catch (e) {
                    console.error('DEBUG: Error during fallback initialization:', e);
                    // Initialize without a manager as a last resort
                    initializeExtension(null);
                }
                return;
            }
            
            // Check if window.klaro and getManager are available
            if (typeof window.klaro !== 'undefined') {
                console.log('DEBUG: window.klaro is defined');
                
                // Try to hook into klaro.show() if it's available
                if (typeof window.klaro.show === 'function' && !originalKlaroShow) {
                    console.log('DEBUG: window.klaro.show is a function, hooking into it');
                    hookKlaroShow();
                }
                
                if (typeof window.klaro.getManager === 'function') {
                    console.log('DEBUG: window.klaro.getManager is a function');
                    
                    try {
                        // Get the manager using the proper API
                        let manager = null;
                        try {
                            // Use the documented API approach
                            manager = window.klaro.getManager();
                            console.log('DEBUG: Got manager using Klaro API');
                        } catch (e) {
                            console.error('DEBUG: Error getting manager:', e);
                        }
                        
                        if (manager) {
                            console.log('DEBUG: Klaro manager is available');
                            console.log('DEBUG: Manager properties:', Object.keys(manager));
                            
                            // Check if manager has consents property
                            if (manager.consents) {
                                console.log('DEBUG: Manager consents available:', Object.keys(manager.consents).length, 'services');
                                
                                // Klaro is loaded, initialize the extension
                                initializeExtension(manager);
                                return;
                            } else {
                                console.log('DEBUG: Manager.consents is not available yet');
                            }
                        } else {
                            console.log('DEBUG: Klaro manager is not available yet (null or undefined)');
                        }
                    } catch (e) {
                        console.error('DEBUG: Error getting Klaro manager:', e);
                        console.log('DEBUG: Error stack:', e.stack);
                    }
                } else {
                    console.log('DEBUG: window.klaro.getManager is not a function');
                }
            } else {
                console.log('DEBUG: window.klaro is not defined yet');
            }
            
            // If we get here, Klaro is not fully loaded yet, try again in 100ms
            setTimeout(checkKlaroLoaded, 100);
        };
        
        // Start checking if Klaro is loaded
        checkKlaroLoaded();
    }
    
    // Function to initialize the extension once Klaro is loaded
    function initializeExtension(manager) {
        console.log('DEBUG: Initializing extension with Klaro manager:', manager ? 'available' : 'not available');
        
        // Set up a watcher for consent changes if not already done and manager is available
        if (!watcherSetup && manager) {
            setupConsentWatcher(manager);
        } else if (!manager) {
            console.log('DEBUG: Skipping consent watcher setup because manager is not available');
        }
        
        // Check if controls are already injected
        if (controlsInjected) {
            console.log('DEBUG: Controls already injected, skipping initialization');
            return;
        }

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

        // Get the ad storage service name
        const adStorageService = 
            window.klaroConsentData.templateSettings.config.consent_mode_settings.ad_storage_service;
        
        console.log('DEBUG: Ad storage service name:', adStorageService);

        if (!adStorageService || adStorageService === 'no_service') {
            console.log('DEBUG: No ad storage service configured');
            return;
        }

        console.log('DEBUG: Initializing for service ' + adStorageService);

        // Try to inject immediately in case the modal is already open
        if (!controlsInjected) {
            console.log('DEBUG: Attempting immediate initial injection');
            injectAdControls(adStorageService);
        }
        
        // Also try after a delay in case the DOM wasn't ready
        setTimeout(function() {
            // Check again if controls are already injected
            if (controlsInjected) {
                console.log('DEBUG: Controls already injected, skipping scheduled initial injection');
                return;
            }

            console.log('DEBUG: Attempting initial injection after delay');
            injectAdControls(adStorageService);
            
            // Try one more time with a longer delay as a fallback
            setTimeout(function() {
                if (!controlsInjected) {
                    console.log('DEBUG: Final attempt at initial injection after longer delay');
                    injectAdControls(adStorageService);
                }
            }, 1000);
        }, 500);
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
                            
                            // Get the configured ad service
                            const configuredAdService =
                                window.klaroConsentData?.templateSettings?.config?.consent_mode_settings?.ad_storage_service;
                            
                            if (configuredAdService && typeof data[configuredAdService] !== 'undefined') {
                                const isServiceEnabled = data[configuredAdService] === true;
                                console.log('DEBUG: Service enabled in consent change:', isServiceEnabled);
                                
                                // Find the service element
                                const serviceElement = document.getElementById('service-item-' + configuredAdService);
                                if (serviceElement) {
                                    const serviceListItem = serviceElement.closest('li.cm-service');
                                    if (serviceListItem) {
                                        updateControlsFromConsent(serviceListItem, isServiceEnabled);
                                    }
                                }
                            }
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
    
    // Function to update controls based on consent state
    function updateControlsFromConsent(serviceListItem, isServiceEnabled) {
        // Get the current state of the checkboxes
        // First look for our controls in the next sibling (our custom list item)
        let adPersonalizationCheckbox = null;
        let adUserDataCheckbox = null;
        let controlsContainer = null;
        
        // Try to find controls in the next sibling
        const controlsListItem = serviceListItem.nextElementSibling;
        if (controlsListItem && controlsListItem.classList.contains('klaro-geo-consent-mode-controls')) {
            adPersonalizationCheckbox = controlsListItem.querySelector('#klaro-geo-ad-personalization');
            adUserDataCheckbox = controlsListItem.querySelector('#klaro-geo-ad-user-data');
            controlsContainer = controlsListItem.querySelector('.klaro-geo-ad-controls');
            
            if (adPersonalizationCheckbox && adUserDataCheckbox && controlsContainer) {
                console.log('DEBUG: Found controls in the next sibling element');
            }
        }
        
        // If not found, try to find them in the service list item (for backward compatibility)
        if (!adPersonalizationCheckbox || !adUserDataCheckbox || !controlsContainer) {
            const inServicePersonalization = serviceListItem.querySelector('#klaro-geo-ad-personalization');
            const inServiceUserData = serviceListItem.querySelector('#klaro-geo-ad-user-data');
            const inServiceContainer = serviceListItem.querySelector('.klaro-geo-ad-controls');
            
            if (inServicePersonalization) {
                adPersonalizationCheckbox = inServicePersonalization;
                console.log('DEBUG: Found ad personalization checkbox in service list item');
            }
            
            if (inServiceUserData) {
                adUserDataCheckbox = inServiceUserData;
                console.log('DEBUG: Found ad user data checkbox in service list item');
            }
            
            if (inServiceContainer) {
                controlsContainer = inServiceContainer;
                console.log('DEBUG: Found controls container in service list item');
            }
        }
        
        // If still not found, try to find them anywhere in the document as a last resort
        if (!adPersonalizationCheckbox) {
            const globalPersonalization = document.querySelector('#klaro-geo-ad-personalization');
            if (globalPersonalization) {
                adPersonalizationCheckbox = globalPersonalization;
                console.log('DEBUG: Found ad personalization checkbox in global document');
            }
        }
        
        if (!adUserDataCheckbox) {
            const globalUserData = document.querySelector('#klaro-geo-ad-user-data');
            if (globalUserData) {
                adUserDataCheckbox = globalUserData;
                console.log('DEBUG: Found ad user data checkbox in global document');
            }
        }
        
        if (!controlsContainer) {
            const globalContainer = document.querySelector('.klaro-geo-ad-controls');
            if (globalContainer) {
                controlsContainer = globalContainer;
                console.log('DEBUG: Found controls container in global document');
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
        
        // Update the consent state
        console.log('DEBUG: Calling updateConsentState()');
        updateConsentState();
    }
    
    // Function to handle when the modal is opened
    function handleModalOpen(manager) {
        console.log('DEBUG: Modal open detected');
        
        // Check if controls are already injected
        if (controlsInjected) {
            console.log('DEBUG: Controls already injected, skipping modal open handler');
            return;
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

        // Get the ad storage event service name
        const adStorageService =
            window.klaroConsentData.templateSettings.config.consent_mode_settings.ad_storage_service;
        console.log('DEBUG: Ad storage event service name (modal open):', adStorageService);

        if (!adStorageService || adStorageService === 'no_service') {
            console.log('DEBUG: No ad storage event configured (modal open)');
            return;
        }

        // Try to inject the controls
        console.log('DEBUG: Scheduling injection after modal open');
        
        // First attempt immediately
        if (!controlsInjected) {
            console.log('DEBUG: Attempting immediate injection after modal open');
            injectAdControls(adStorageService);
        }
        
        // Then try again after a delay in case the DOM wasn't ready
        setTimeout(function() {
            // Check again if controls are already injected
            if (controlsInjected) {
                console.log('DEBUG: Controls already injected, skipping scheduled injection');
                return;
            }

            console.log('DEBUG: Attempting injection after modal open delay');
            injectAdControls(adStorageService);
            
            // Try one more time with a longer delay as a fallback
            setTimeout(function() {
                if (!controlsInjected) {
                    console.log('DEBUG: Final attempt at injection after longer delay');
                    injectAdControls(adStorageService);
                }
            }, 1000);
        }, 500);
    }

    // Function to update controls state based on service consent
    function updateControlsState(adServiceName, isEnabled) {
        console.log('DEBUG: Updating controls state for', adServiceName, 'to', isEnabled);

        // Find the service element
        const serviceElement = document.getElementById('service-item-' + adServiceName);
        if (!serviceElement) {
            console.log('DEBUG: Service element not found for state update');
            return;
        }

        // Find the parent li element
        const serviceListItem = serviceElement.closest('li.cm-service');
        if (!serviceListItem) {
            console.log('DEBUG: Service list item not found for state update');
            return;
        }

        // Find the controls list item
        const controlsListItem = serviceListItem.nextElementSibling;
        if (!controlsListItem || !controlsListItem.classList.contains('klaro-geo-consent-mode-controls')) {
            console.log('DEBUG: Controls list item not found for state update');
            return;
        }

        // Find the controls container
        const controlsContainer = controlsListItem.querySelector('.klaro-geo-ad-controls');
        if (!controlsContainer) {
            console.log('DEBUG: Controls container not found for state update');
            return;
        }

        // Update the container disabled state
        if (isEnabled) {
            controlsContainer.classList.remove('klaro-geo-controls-disabled');
            console.log('DEBUG: Removed klaro-geo-controls-disabled class');
        } else {
            controlsContainer.classList.add('klaro-geo-controls-disabled');
            console.log('DEBUG: Added klaro-geo-controls-disabled class');
        }

        // Find the checkboxes
        const adPersonalizationCheckbox = controlsListItem.querySelector('#klaro-geo-ad-personalization');
        const adUserDataCheckbox = controlsListItem.querySelector('#klaro-geo-ad-user-data');

        // Update the checkboxes
        if (adPersonalizationCheckbox) {
            adPersonalizationCheckbox.checked = isEnabled;
            console.log('DEBUG: Updated ad personalization checkbox to', isEnabled);
        }

        if (adUserDataCheckbox) {
            adUserDataCheckbox.checked = isEnabled;
            console.log('DEBUG: Updated ad user data checkbox to', isEnabled);
        }

        // Update the global consent state variables
        adPersonalizationConsent = isEnabled;
        adUserDataConsent = isEnabled;

        // Update the consent state in Google's consent mode
        updateConsentState();
    }

    // Function to inject ad controls
    function injectAdControls(adServiceName) {
        console.log("DEBUG: Injecting controls for " + adServiceName);
      
        // Check global flag first
        if (controlsInjected) {
            console.log("DEBUG: Controls already injected (global flag)");
            return;
        }
      
        // Only inject if the service name matches the one from the template settings
        const configuredAdService =
            window.klaroConsentData?.templateSettings?.config?.consent_mode_settings
                ?.ad_storage_service;
        if (adServiceName !== configuredAdService) {
            console.log(
                "DEBUG: Skipping injection for service " +
                adServiceName +
                " (configured service is " +
                configuredAdService +
                ")"
            );
            return;
        }
      
        // Function to find the service list item
        function findServiceListItem(serviceName) {
            console.log("DEBUG: findServiceListItem called for", serviceName);
      
            // **Target the service list item more precisely**
            const serviceListItem = document.querySelector(
                `.cm-service [id="service-item-${serviceName}"]`
            );
      
            if (!serviceListItem) {
                console.log("DEBUG: Ad service element not found using specific selector");
                return null;
            }
      
            // Find the parent list item
            const parentListItem = serviceListItem.closest("li.cm-service");
      
            if (!parentListItem) {
                console.log("DEBUG: Service list item not found using closest");
                return null;
            }
      
            return parentListItem;
        }
      
        // Try to find the service list item
        const serviceListItem = findServiceListItem(adServiceName);
      
        if (!serviceListItem) {
            console.log("DEBUG: Service list item still not found");
            return;
        }
      
        console.log("DEBUG: Service list item found");
      
        // Check if we've already injected the controls - either in the service item or as a next sibling
        if (serviceListItem.querySelector(".klaro-geo-ad-controls")) {
            console.log("DEBUG: Controls already injected in service item");
            controlsInjected = true;
            return;
        }
      
        // Also check if we've already injected controls as a next sibling
        const nextSibling = serviceListItem.nextElementSibling;
        if (nextSibling && nextSibling.classList.contains("klaro-geo-consent-mode-controls")) {
            console.log("DEBUG: Controls already injected as next sibling");
            controlsInjected = true;
            return;
        }
      
        // Set the global flag
        controlsInjected = true;
        console.log("DEBUG: Setting global controlsInjected flag to true");
      
        console.log("DEBUG: Injecting controls for " + adServiceName);
      
        // Create the container for the additional controls
        const controlsContainer = document.createElement("div");
        controlsContainer.className = "klaro-geo-ad-controls";
      
        // Get the current consent state from Klaro's manager
        let isServiceEnabled = false;
      
        // Try to get the consent state from Klaro's manager
        if (window.klaro && typeof window.klaro.getManager === "function") {
            try {
              let manager = null;
              try {
                // Use a safer approach to get the manager
                const getManagerFn = window.klaro.getManager;
                manager = getManagerFn.call(window.klaro);
                console.log("DEBUG: Got manager using safer approach in injectAdControls");
              } catch (e) {
                console.error(
                  "DEBUG: Error getting manager with safer approach in injectAdControls:",
                  e
                );
                // Fall back to direct call
                try {
                  manager = window.klaro.getManager();
                  console.log("DEBUG: Got manager using direct call in injectAdControls");
                } catch (e2) {
                  console.error(
                    "DEBUG: Error getting manager with direct call in injectAdControls:",
                    e2
                  );
                }
              }
      
              if (manager && manager.consents) {
                isServiceEnabled = manager.consents[adServiceName] === true;
                console.log(
                  "DEBUG: Got consent state from Klaro manager for",
                  adServiceName,
                  ":",
                  isServiceEnabled
                );
      
                // Set up the watcher if not already done
                if (!watcherSetup) {
                  setupConsentWatcher(manager);
                }
              } else {
                console.log(
                  "DEBUG: Klaro manager or consents not available during injection, using default state"
                );
                // Continue with default state
              }
            } catch (e) {
              console.error("DEBUG: Error getting Klaro manager during injection:", e);
              // Continue with default state
            }
          } else {
            console.log(
              "DEBUG: Klaro or getManager not available during injection, using default state"
            );
            // Continue with default state
          }
      
          // Initialize the sub-controls to match the main service state
          adPersonalizationConsent = isServiceEnabled;
          adUserDataConsent = isServiceEnabled;
      
          // Add or remove the disabled class based on the service state
          if (isServiceEnabled) {
            controlsContainer.classList.remove("klaro-geo-controls-disabled");
          } else {
            controlsContainer.classList.add("klaro-geo-controls-disabled");
          }
      
          console.log("Service enabled:", isServiceEnabled, "Container classes:", controlsContainer.className);
      
          // Find the parent service checkbox and add an event listener to update child controls
          const parentServiceCheckbox = serviceListItem.querySelector("input[type=\"checkbox\"]");
          if (parentServiceCheckbox) {
            console.log("DEBUG: Found parent service checkbox:", parentServiceCheckbox);
            console.log("DEBUG: Parent checkbox ID:", parentServiceCheckbox.id);
            console.log("DEBUG: Parent checkbox initial state:", parentServiceCheckbox.checked);
      
            // Log the parent checkbox's parent element to see its structure
            console.log("DEBUG: Parent checkbox parent element:", parentServiceCheckbox.parentNode);
      
            // Find the parent switch element
            const parentSwitchElement = serviceListItem.querySelector(".cm-switch");
            if (parentSwitchElement) {
              console.log("DEBUG: Parent switch element found:", parentSwitchElement ? true : false);
      
              // Add a direct click handler to the parent switch element
              if (parentSwitchElement) {
                console.log("DEBUG: Adding click listener to parent switch element");
      
                parentSwitchElement.addEventListener("click", function(e) {
                  console.log("DEBUG: Parent switch clicked");
                  console.log("DEBUG: Event:", e);
                  console.log("DEBUG: Parent checkbox state before toggle:", parentServiceCheckbox.checked);
      
                  // We don't want to stop propagation here as we want Klaro to handle the parent toggle
                  // But we do want to manually update our child controls
      
                  // The parent will be toggled by Klaro, so we need to use the opposite of the current state
                  const newState = !parentServiceCheckbox.checked;
                  console.log("DEBUG: Expected new parent state after toggle:", newState);
      
                  // Schedule an update after a short delay to allow Klaro to process the change
                  setTimeout(function() {
                    console.log("DEBUG: Delayed update after parent toggle");
                    console.log("DEBUG: Parent checkbox state after delay:", parentServiceCheckbox.checked);
      
                    // Update the container disabled state
                    if (parentServiceCheckbox.checked) {
                      controlsContainer.classList.remove("klaro-geo-controls-disabled");
                      console.log("DEBUG: Removed klaro-geo-controls-disabled class");
                    } else {
                      controlsContainer.classList.add("klaro-geo-controls-disabled");
                      console.log("DEBUG: Added klaro-geo-controls-disabled class");
                    }
      
                    // Get the current state of the checkboxes
                    // First look for our controls in the next sibling (our custom list item)
                    let adPersonalizationCheckbox = null;
                    let adUserDataCheckbox = null;
      
                    // Try to find controls in the next sibling
                    const controlsListItem = serviceListItem.nextElementSibling;
                    if (controlsListItem && controlsListItem.classList.contains("klaro-geo-consent-mode-controls")) {
                      adPersonalizationCheckbox = controlsListItem.querySelector("#klaro-geo-ad-personalization");
                      adUserDataCheckbox = controlsListItem.querySelector("#klaro-geo-ad-user-data");
      
                      if (adPersonalizationCheckbox && adUserDataCheckbox) {
                        console.log("DEBUG: Found controls in the next sibling element for parent switch handler");
                      }
                    }
      
                    // If not found, try to find them in the service list item (for backward compatibility)
                    if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                      const inServicePersonalization = serviceListItem.querySelector("#klaro-geo-ad-personalization");
                      const inServiceUserData = serviceListItem.querySelector("#klaro-geo-ad-user-data");
      
                      if (inServicePersonalization) {
                        adPersonalizationCheckbox = inServicePersonalization;
                        console.log("DEBUG: Found ad personalization checkbox in service list item for parent switch handler");
                      }
      
                      if (inServiceUserData) {
                        adUserDataCheckbox = inServiceUserData;
                        console.log("DEBUG: Found ad user data checkbox in service list item for parent switch handler");
                      }
                    }
      
                    // If still not found, try to find them anywhere in the document as a last resort
                    if (!adPersonalizationCheckbox) {
                      const globalPersonalization = document.querySelector("#klaro-geo-ad-personalization");
                      if (globalPersonalization) {
                        adPersonalizationCheckbox = globalPersonalization;
                        console.log("DEBUG: Found ad personalization checkbox in global document for parent switch handler");
                      }
                    }
      
                    if (!adUserDataCheckbox) {
                      const globalUserData = document.querySelector("#klaro-geo-ad-user-data");
                      if (globalUserData) {
                        adUserDataCheckbox = globalUserData;
                        console.log("DEBUG: Found ad user data checkbox in global document for parent switch handler");
                      }
                    }
      
                    console.log("DEBUG: Found ad personalization checkbox:", adPersonalizationCheckbox ? true : false);
                    console.log("DEBUG: Found ad user data checkbox:", adUserDataCheckbox ? true : false);
      
                    if (adPersonalizationCheckbox) {
                      const oldState = adPersonalizationCheckbox.checked;
                      adPersonalizationCheckbox.checked = parentServiceCheckbox.checked;
                      adPersonalizationConsent = parentServiceCheckbox.checked;
                      console.log("DEBUG: Updated ad personalization checkbox from", oldState, "to", adPersonalizationCheckbox.checked);
                    }
      
                    if (adUserDataCheckbox) {
                      const oldState = adUserDataCheckbox.checked;
                      adUserDataCheckbox.checked = parentServiceCheckbox.checked;
                      adUserDataConsent = parentServiceCheckbox.checked;
                      console.log("DEBUG: Updated ad user data checkbox from", oldState, "to", adUserDataCheckbox.checked);
                    }
      
                    // Update the consent state
                    console.log("DEBUG: Calling updateConsentState() from parent switch click handler");
                    updateConsentState();
                  }, 100);
                });
              }
      
              // Also add the change event listener to the parent checkbox
              parentServiceCheckbox.addEventListener("change", function(e) {
                console.log("DEBUG: Parent service checkbox change event triggered");
                console.log("DEBUG: Event type:", e.type);
                console.log("DEBUG: Event target:", e.target);
                console.log("DEBUG: Parent checkbox changed to:", this.checked);
                console.log("DEBUG: Event bubbles:", e.bubbles);
      
                // Update the container disabled state
                if (this.checked) {
                  controlsContainer.classList.remove("klaro-geo-controls-disabled");
                  console.log("DEBUG: Removed klaro-geo-controls-disabled class");
                } else {
                  controlsContainer.classList.add("klaro-geo-controls-disabled");
                  console.log("DEBUG: Added klaro-geo-controls-disabled class");
                }
      
                // Get the current state of the checkboxes
                // First look for our controls in the next sibling (our custom list item)
                let adPersonalizationCheckbox = null;
                let adUserDataCheckbox = null;
      
                // Try to find controls in the next sibling
                const controlsListItem = serviceListItem.nextElementSibling;
                if (controlsListItem && controlsListItem.classList.contains("klaro-geo-consent-mode-controls")) {
                  adPersonalizationCheckbox = controlsListItem.querySelector("#klaro-geo-ad-personalization");
                  adUserDataCheckbox = controlsListItem.querySelector("#klaro-geo-ad-user-data");
      
                  if (adPersonalizationCheckbox && adUserDataCheckbox) {
                    console.log("DEBUG: Found controls in the next sibling element for parent checkbox handler");
                  }
                }
      
                // If not found, try to find them in the service list item (for backward compatibility)
                if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                  const inServicePersonalization = serviceListItem.querySelector("#klaro-geo-ad-personalization");
                  const inServiceUserData = serviceListItem.querySelector("#klaro-geo-ad-user-data");
      
                  if (inServicePersonalization) {
                    adPersonalizationCheckbox = inServicePersonalization;
                    console.log("DEBUG: Found ad personalization checkbox in service list item for parent handler");
                  }
      
                  if (inServiceUserData) {
                    adUserDataCheckbox = inServiceUserData;
                    console.log("DEBUG: Found ad user data checkbox in service list item for parent handler");
                  }
                }
      
                // If still not found, try to find them anywhere in the document as a last resort
                if (!adPersonalizationCheckbox) {
                  const globalPersonalization = document.querySelector("#klaro-geo-ad-personalization");
                  if (globalPersonalization) {
                    adPersonalizationCheckbox = globalPersonalization;
                    console.log("DEBUG: Found ad personalization checkbox in global document for parent handler");
                  }
                }
      
                if (!adUserDataCheckbox) {
                  const globalUserData = document.querySelector("#klaro-geo-ad-user-data");
                  if (globalUserData) {
                    adUserDataCheckbox = globalUserData;
                    console.log("DEBUG: Found ad user data checkbox in global document for parent handler");
                  }
                }
      
                if (adPersonalizationCheckbox) {
                  const oldState = adPersonalizationCheckbox.checked;
                  adPersonalizationCheckbox.checked = this.checked;
                  adPersonalizationConsent = this.checked;
                  console.log("DEBUG: Updated ad personalization checkbox from", oldState, "to", adPersonalizationCheckbox.checked);
                }
      
                if (adUserDataCheckbox) {
                  const oldState = adUserDataCheckbox.checked;
                  adUserDataCheckbox.checked = this.checked;
                  adUserDataConsent = this.checked;
                  console.log("DEBUG: Updated ad user data checkbox from", oldState, "to", adUserDataCheckbox.checked);
                }
      
                // Update the consent state
                console.log("DEBUG: Calling updateConsentState() from parent checkbox change handler");
                updateConsentState();
              });
      
              // Set up a MutationObserver to watch for attribute changes on the parent checkbox
              console.log("DEBUG: Setting up MutationObserver for parent checkbox");
              const parentCheckboxObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                  if (mutation.type === "attributes" && mutation.attributeName === "checked") {
                    console.log("DEBUG: Parent checkbox checked attribute changed via mutation");
                    console.log("DEBUG: New parent checkbox state:", parentServiceCheckbox.checked);
      
                    // Update the container disabled state
                    if (parentServiceCheckbox.checked) {
                      controlsContainer.classList.remove("klaro-geo-controls-disabled");
                      console.log("DEBUG: Removed klaro-geo-controls-disabled class via mutation");
                    } else {
                      controlsContainer.classList.add("klaro-geo-controls-disabled");
                      console.log("DEBUG: Added klaro-geo-controls-disabled class via mutation");
                    }
      
                    // Get the current state of the checkboxes
                    // First look for our controls in the next sibling (our custom list item)
                    let adPersonalizationCheckbox = null;
                    let adUserDataCheckbox = null;
      
                    // Try to find controls in the next sibling
                    const controlsListItem = serviceListItem.nextElementSibling;
                    if (controlsListItem && controlsListItem.classList.contains("klaro-geo-consent-mode-controls")) {
                      adPersonalizationCheckbox = controlsListItem.querySelector("#klaro-geo-ad-personalization");
                      adUserDataCheckbox = controlsListItem.querySelector("#klaro-geo-ad-user-data");
      
                      if (adPersonalizationCheckbox && adUserDataCheckbox) {
                        console.log("DEBUG: Found controls in the next sibling element for mutation observer");
                      }
                    }
      
                    // If not found, try to find them in the service list item (for backward compatibility)
                    if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                      const inServicePersonalization = serviceListItem.querySelector("#klaro-geo-ad-personalization");
                      const inServiceUserData = serviceListItem.querySelector("#klaro-geo-ad-user-data");
      
                      if (inServicePersonalization) {
                        adPersonalizationCheckbox = inServicePersonalization;
                        console.log("DEBUG: Found ad personalization checkbox in service list item for mutation observer");
                      }
      
                      if (inServiceUserData) {
                        adUserDataCheckbox = inServiceUserData;
                        console.log("DEBUG: Found ad user data checkbox in service list item for mutation observer");
                      }
                    }
      
                    // If still not found, try to find them anywhere in the document as a last resort
                    if (!adPersonalizationCheckbox) {
                      const globalPersonalization = document.querySelector("#klaro-geo-ad-personalization");
                      if (globalPersonalization) {
                        adPersonalizationCheckbox = globalPersonalization;
                        console.log("DEBUG: Found ad personalization checkbox in global document for mutation observer");
                      }
                    }
      
                    if (!adUserDataCheckbox) {
                      const globalUserData = document.querySelector("#klaro-geo-ad-user-data");
                      if (globalUserData) {
                        adUserDataCheckbox = globalUserData;
                        console.log("DEBUG: Found ad user data checkbox in global document for mutation observer");
                      }
                    }
      
                    if (adPersonalizationCheckbox) {
                      const oldState = adPersonalizationCheckbox.checked;
                      adPersonalizationCheckbox.checked = parentServiceCheckbox.checked;
                      adPersonalizationConsent = parentServiceCheckbox.checked;
                      console.log("DEBUG: Updated ad personalization checkbox from", oldState, "to", adPersonalizationCheckbox.checked, "via mutation");
                    }
      
                    if (adUserDataCheckbox) {
                      const oldState = adUserDataCheckbox.checked;
                      adUserDataCheckbox.checked = parentServiceCheckbox.checked;
                      adUserDataConsent = parentServiceCheckbox.checked;
                      console.log("DEBUG: Updated ad user data checkbox from", oldState, "to", adUserDataCheckbox.checked, "via mutation");
                    }
      
                    // Update the consent state
                    console.log("DEBUG: Calling updateConsentState() from mutation observer");
                    updateConsentState();
                  }
                });
      
                // Start observing the parent checkbox for attribute changes
                parentCheckboxObserver.observe(parentServiceCheckbox, {
                  attributes: true
                });
              } else {
                console.log("DEBUG: Could not find parent service checkbox");
              }
            }
      
            // Create the ad personalization control
            const adPersonalizationControl = createSubControl(
              "ad-personalization",
              "Ad Personalization",
              "Allow personalized ads based on your interests",
              adPersonalizationConsent,
              function(checked) {
                adPersonalizationConsent = checked;
                updateConsentState();
              }
            );
      
            // Create the ad user data control
            const adUserDataControl = createSubControl(
              "ad-user-data",
              "Ad User Data",
              "Allow use of your data for ad targeting",
              adUserDataConsent,
              function(checked) {
                adUserDataConsent = checked;
                updateConsentState();
              }
            );
      
            // Add the controls to the container
            controlsContainer.appendChild(adPersonalizationControl);
            controlsContainer.appendChild(adUserDataControl);
      
            // Create a new list item for our controls
            const newListItem = document.createElement("li");
            newListItem.className = "cm-service klaro-geo-consent-mode-controls";
          
            // Create a div inside the list item (to match Klaro's structure)
            const newListItemDiv = document.createElement("div");
            newListItem.appendChild(newListItemDiv);
          
            // Add a title to the new list item
            const titleDiv = document.createElement("div");
            titleDiv.className = "cm-list-title";
            titleDiv.textContent = "Consent Mode Settings";
            newListItemDiv.appendChild(titleDiv);
          
            // Add a description paragraph
            const descriptionDiv = document.createElement("div");
            const descriptionPara = document.createElement("p");
            descriptionPara.className = "cm-service-description";
            descriptionPara.textContent = "Additional controls for Google Consent Mode v2";
            descriptionDiv.appendChild(descriptionPara);
            newListItemDiv.appendChild(descriptionDiv);
          
            // Add the controls container to the new list item
            newListItemDiv.appendChild(controlsContainer);
          
            // Insert the new list item after the service list item
            if (serviceListItem.nextSibling) {
              serviceListItem.parentNode.insertBefore(newListItem, serviceListItem.nextSibling);
            } else {
              serviceListItem.parentNode.appendChild(newListItem);
            }
          
            console.log("DEBUG: Inserted controls after service list item");
          
            // Set a data attribute to mark this service as processed
            serviceListItem.dataset.controlsInjected = "true";
          
            // Ensure the global flag is set
            controlsInjected = true;
            console.log("DEBUG: Set controlsInjected flag to true after successful injection");
          }
    }

  // Function to create a sub-control
  function createSubControl(id, title, description, initialState, onChange) {
    console.log(`DEBUG: Creating sub-control for ${id} with initial state:`, initialState);

    const container = document.createElement('div');
    container.className = 'cm-service klaro-geo-sub-control';
    console.log(`DEBUG: Created container for ${id} with class:`, container.className);

    // Construct the 'checked' attribute string
    const checkedAttribute = initialState ? 'checked' : '';

    // Create the control HTML
    container.innerHTML = `
      <div class="cm-service-header">
        <input id="klaro-geo-${id}" type="checkbox" class="cm-list-input" ${checkedAttribute}>
        <label for="klaro-geo-${id}" class="cm-list-label">
          <span class="cm-list-title">${title}</span>
          <span class="cm-switch"><div class="slider round"></div></span>
        </label>
        <div class="cm-service-description">
          <p class="purposes">${description}</p>
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

    // Function to update the consent state
    function updateConsentState() {
        console.log('DEBUG: updateConsentState() called');

        // Get the configured ad service
        const configuredAdService =
            window.klaroConsentData?.templateSettings?.config?.consent_mode_settings?.ad_storage_service;
        console.log('DEBUG: Configured ad service:', configuredAdService);

        if (!configuredAdService) {
            console.log('DEBUG: No ad service configured');
            return;
        }

        // Find the service element
        const serviceElement = document.getElementById('service-item-' + configuredAdService);
        console.log('DEBUG: Service element found:', serviceElement ? true : false);

        if (!serviceElement) {
            console.log('DEBUG: Ad service element not found');
            return;
        }

        // Find the parent li element
        const serviceListItem = serviceElement.closest('li.cm-service');
        console.log('DEBUG: Service list item found:', serviceListItem ? true : false);

        if (!serviceListItem) {
            console.log('DEBUG: Service list item not found');
            return;
        }

        // Check if the service is enabled in Klaro
        let isServiceEnabled = false;
        
        try {
            if (window.klaro && typeof window.klaro.getManager === 'function') {
                let manager = null;
                try {
                    // Use a safer approach to get the manager
                    const getManagerFn = window.klaro.getManager;
                    manager = getManagerFn.call(window.klaro);
                    console.log('DEBUG: Got manager using safer approach in updateConsentState');
                } catch (e) {
                    console.error('DEBUG: Error getting manager with safer approach in updateConsentState:', e);
                    // Fall back to direct call
                    try {
                        manager = window.klaro.getManager();
                        console.log('DEBUG: Got manager using direct call in updateConsentState');
                    } catch (e2) {
                        console.error('DEBUG: Error getting manager with direct call in updateConsentState:', e2);
                    }
                }
                
                console.log('DEBUG: Klaro manager found:', manager ? true : false);
    
                if (manager && manager.consents) {
                    isServiceEnabled = manager.consents[configuredAdService] === true;
                    console.log('DEBUG: Service enabled in Klaro:', isServiceEnabled);
                    console.log('DEBUG: All consents in Klaro:', manager.consents);
    
                    // If the service is disabled, we should also disable the sub-controls
                    if (!isServiceEnabled) {
                        console.log('DEBUG: Service is disabled, disabling sub-controls');
    
                        adPersonalizationConsent = false;
                        adUserDataConsent = false;
                        console.log('DEBUG: Reset consent values to false');
    
                        // Update the UI to reflect this
                        const adPersonalizationCheckbox = serviceListItem.querySelector('#klaro-geo-ad-personalization');
                        const adUserDataCheckbox = serviceListItem.querySelector('#klaro-geo-ad-user-data');
    
                        console.log('DEBUG: Found ad personalization checkbox for update:', adPersonalizationCheckbox ? true : false);
                        console.log('DEBUG: Found ad user data checkbox for update:', adUserDataCheckbox ? true : false);
    
                        if (adPersonalizationCheckbox) {
                            const oldState = adPersonalizationCheckbox.checked;
                            adPersonalizationCheckbox.checked = false;
                            console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to false');
                        }
    
                        if (adUserDataCheckbox) {
                            const oldState = adUserDataCheckbox.checked;
                            adUserDataCheckbox.checked = false;
                            console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to false');
                        }
    
                        // Add the disabled class to the container
                        const controlsContainer = serviceListItem.querySelector('.klaro-geo-ad-controls');
                        console.log('DEBUG: Found controls container for update:', controlsContainer ? true : false);
    
                        if (controlsContainer) {
                            controlsContainer.classList.add('klaro-geo-controls-disabled');
                            console.log('DEBUG: Added klaro-geo-controls-disabled class to container');
                        }
                    }
                } else {
                    console.log('DEBUG: Manager or consents not available');
                }
            } else {
                console.log('DEBUG: Klaro or getManager not available');
            }
        } catch (e) {
            console.error('DEBUG: Error checking service enabled state:', e);
            // Continue with default state
        }

        // Get the current state of the checkboxes
        // First look for our controls in the next sibling (our custom list item)
        let adPersonalizationCheckbox = null;
        let adUserDataCheckbox = null;

        // Try to find controls in the next sibling
        const controlsListItem = serviceListItem.nextElementSibling;
        if (controlsListItem && controlsListItem.classList.contains('klaro-geo-consent-mode-controls')) {
            adPersonalizationCheckbox = controlsListItem.querySelector('#klaro-geo-ad-personalization');
            adUserDataCheckbox = controlsListItem.querySelector('#klaro-geo-ad-user-data');

            if (adPersonalizationCheckbox && adUserDataCheckbox) {
                console.log('DEBUG: Found controls in the next sibling element');
            }
        }

        // If not found, try to find them in the service list item (for backward compatibility)
        if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
            const inServicePersonalization = serviceListItem.querySelector('#klaro-geo-ad-personalization');
            const inServiceUserData = serviceListItem.querySelector('#klaro-geo-ad-user-data');

            if (inServicePersonalization) {
                adPersonalizationCheckbox = inServicePersonalization;
                console.log('DEBUG: Found ad personalization checkbox in service list item');
            }

            if (inServiceUserData) {
                adUserDataCheckbox = inServiceUserData;
                console.log('DEBUG: Found ad user data checkbox in service list item');
            }
        }

        // If still not found, try to find them anywhere in the document as a last resort
        if (!adPersonalizationCheckbox) {
            const globalPersonalization = document.querySelector('#klaro-geo-ad-personalization');
            if (globalPersonalization) {
                adPersonalizationCheckbox = globalPersonalization;
                console.log('DEBUG: Found ad personalization checkbox in global document');
            }
        }

        if (!adUserDataCheckbox) {
            const globalUserData = document.querySelector('#klaro-geo-ad-user-data');
            if (globalUserData) {
                adUserDataCheckbox = globalUserData;
                console.log('DEBUG: Found ad user data checkbox in global document');
            }
        }

        const parentServiceCheckbox = serviceListItem.querySelector('input[type="checkbox"]');

        console.log('DEBUG: Found checkboxes for state update:');
        console.log('DEBUG: - Parent checkbox:', parentServiceCheckbox ? true : false);
        console.log('DEBUG: - Ad personalization checkbox:', adPersonalizationCheckbox ? true : false);
        console.log('DEBUG: - Ad user data checkbox:', adUserDataCheckbox ? true : false);

        // Make sure our internal state matches the checkbox state
        if (adPersonalizationCheckbox) {
            const oldState = adPersonalizationConsent;
            adPersonalizationConsent = adPersonalizationCheckbox.checked;
            console.log('DEBUG: Updated adPersonalizationConsent from', oldState, 'to', adPersonalizationConsent);
        }

        if (adUserDataCheckbox) {
            const oldState = adUserDataConsent;
            adUserDataConsent = adUserDataCheckbox.checked;
            console.log('DEBUG: Updated adUserDataConsent from', oldState, 'to', adUserDataConsent);
        }

        console.log('DEBUG: Current checkbox states:', {
            'parent_service_checkbox': parentServiceCheckbox ? parentServiceCheckbox.checked : 'not found',
            'ad_personalization_checkbox': adPersonalizationCheckbox ? adPersonalizationCheckbox.checked : 'not found',
            'ad_user_data_checkbox': adUserDataCheckbox ? adUserDataCheckbox.checked : 'not found',
            'adPersonalizationConsent': adPersonalizationConsent,
            'adUserDataConsent': adUserDataConsent
        });

        // Initialize when the DOM is ready
        if (document.readyState === 'loading') {
            console.log('DEBUG: Document is loading, waiting for DOMContentLoaded event');
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DEBUG: DOMContentLoaded event triggered, initializing Consent Mode Extension');
                console.log('DEBUG: klaroConsentData available:', typeof window.klaroConsentData !== 'undefined');
                if (typeof window.klaroConsentData !== 'undefined') {
                    console.log('DEBUG: klaroConsentData:', window.klaroConsentData);
                }
                
                // Try to hook into klaro.show() if it's already available
                if (window.klaro && typeof window.klaro.show === 'function' && !originalKlaroShow) {
                    console.log('DEBUG: window.klaro.show is already available at DOMContentLoaded, hooking into it');
                    hookKlaroShow();
                }
                
                // Start the initialization process
                initConsentModeExtension();
            });
        } else {
            console.log('DEBUG: Document already loaded, initializing Consent Mode Extension immediately');
            console.log('DEBUG: klaroConsentData available:', typeof window.klaroConsentData !== 'undefined');
            if (typeof window.klaroConsentData !== 'undefined') {
                console.log('DEBUG: klaroConsentData:', window.klaroConsentData);
            }
            
            // Try to hook into klaro.show() if it's already available
            if (window.klaro && typeof window.klaro.show === 'function' && !originalKlaroShow) {
                console.log('DEBUG: window.klaro.show is already available, hooking into it');
                hookKlaroShow();
            }
            
            // Start the initialization process
            initConsentModeExtension();
        }
    }
    // Log that the script has loaded
    console.log('DEBUG: Klaro Geo Consent Mode Extension script loaded');
})();