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
    function safeUpdateConsent(consentUpdate) {
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
    
    // Create a custom event for when the modal opens
    const modalOpenEvent = document.createEvent('Event');
    modalOpenEvent.initEvent('klaroModalOpen', true, true);
    
    // Set up a MutationObserver to detect when the Klaro modal is opened
    function setupModalObserver() {
        console.log('DEBUG: Setting up modal observer');
        
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
                console.log('DEBUG: Modal opened detected by MutationObserver');
                
                // Dispatch our custom event
                document.dispatchEvent(modalOpenEvent);
                
                // Also call our handler directly
                handleModalOpen();
            } else if (!modal && modalVisible) {
                modalVisible = false;
                console.log('DEBUG: Modal closed detected by MutationObserver');
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
    
    // Add event listener for our custom modal open event
    document.addEventListener('klaroModalOpen', function() {
        console.log('DEBUG: klaroModalOpen event received');
        handleModalOpen();
    });

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
                
                // Try to use Klaro's built-in addEventListener if available
                if (typeof klaroAccess.addEventListener === 'function') {
                    console.log('DEBUG: Klaro addEventListener function found, registering for modal events');
                    
                    try {
                        // Register for modal events
                        klaroAccess.addEventListener('notice.shown', function() {
                            console.log('DEBUG: Klaro notice.shown event received');
                            handleModalOpen();
                        });
                        
                        klaroAccess.addEventListener('modal.shown', function() {
                            console.log('DEBUG: Klaro modal.shown event received');
                            handleModalOpen();
                        });
                        
                        console.log('DEBUG: Successfully registered for Klaro events');
                    } catch (e) {
                        console.error('DEBUG: Error registering for Klaro events:', e);
                    }
                } else {
                    console.log('DEBUG: Klaro addEventListener function not found');
                }
                
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
    
    // Safe wrapper for gtag calls - simplified to reduce console noise
    function safeGtag() {
        // Check if gtag is available
        if (typeof window.gtag !== 'function') {
            return false;
        }
        
        try {
            window.gtag.apply(null, arguments);
            return true;
        } catch (e) {
            console.error('DEBUG: Error calling gtag:', e);
            return false;
        }
    }

    // Function to safely apply initialization code
    function safelyApplyInitializationCode() {
        console.log('DEBUG: Safely applying initialization code function is now deprecated');
        console.log('DEBUG: Initialization code is now directly included in the onInit callback of the Google Tag Manager service');
        
        // This function is now deprecated as the initialization code is directly included in the onInit callback
        // We're keeping it for backward compatibility, but it doesn't do anything anymore
        return;
    }
    
    // Function to log gtag availability - simplified to reduce console noise
    function logGtagStatus() {
        // This function is kept for backward compatibility but doesn't log anything
        // to reduce console noise
        return;
    }
    
    // Function to initialize the consent mode extension
    function initConsentModeExtension() {
        console.log('DEBUG: initConsentModeExtension called');
        
        // Check if the manager is already available in the klaroGeo namespace
        if (window.klaroGeo && window.klaroGeo.manager) {
            console.log('DEBUG: Klaro manager already available in klaroGeo namespace');
            initializeExtension(window.klaroGeo.manager);
            return;
        }
        
        // Listen for the klaro-manager-ready event
        document.addEventListener('klaro-manager-ready', function(event) {
            console.log('DEBUG: Received klaro-manager-ready event');
            if (event.detail && event.detail.manager) {
                console.log('DEBUG: Got manager from event');
                initializeExtension(event.detail.manager);
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
                
                if (typeof window.klaro.getManager === 'function') {
                    console.log('DEBUG: window.klaro.getManager is a function');
                    
                    try {
                        // Get the manager
                        let manager = null;
                        try {
                            // Use a safer approach to get the manager
                            const getManagerFn = window.klaro.getManager;
                            manager = getManagerFn.call(window.klaro);
                            console.log('DEBUG: Got manager using safer approach');
                        } catch (e) {
                            console.error('DEBUG: Error getting manager with safer approach:', e);
                            // Fall back to direct call
                            try {
                                manager = window.klaro.getManager();
                                console.log('DEBUG: Got manager using direct call');
                            } catch (e2) {
                                console.error('DEBUG: Error getting manager with direct call:', e2);
                            }
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
                            safeUpdateConsent(consentUpdate);
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
        
        // Save the sub-control states
        saveSubControlStates();
    }
    
    // Function to update controls based on consent state
    function updateControlsFromConsent(serviceListItem, isServiceEnabled) {
        // Update the UI controls
        updateControlsUI(serviceListItem, isServiceEnabled);
        
        // Update the consent state with force update to ensure it fires
        console.log('DEBUG: Calling updateConsentState() with force update');
        updateConsentState(true);
    }
    
    // Function to handle when the modal is opened
    function handleModalOpen() {
        console.log('DEBUG: Modal open detected');
        
        // Set the editing flag to true when the modal opens
        isEditingInModal = true;
        needsConsentUpdate = false;
        console.log('DEBUG: Set isEditingInModal to true');
        
        // Check if the modal is actually visible
        const klaroModal = document.querySelector('.klaro .cookie-modal') || document.querySelector('.klaro.we-love-cookies');
        console.log('DEBUG: Klaro modal found in DOM:', klaroModal ? 'yes' : 'no');
        
        if (!klaroModal) {
            console.log('DEBUG: Modal not found in DOM, will try again later');
            setTimeout(handleModalOpen, 300);
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

        // Update the consent state in Google's consent mode with force update
        updateConsentState(true);
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
                
                saveSubControlStates();
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
                
                saveSubControlStates();
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
                    saveSubControlStates();
                }
                
                // Update the consent state
                updateConsentState(true); // Force update on user action
            });
        }
    }
    
    // Function to inject the ad controls
    function injectAdControls(adServiceName) {
        console.log('DEBUG: injectAdControls called for service:', adServiceName);
        console.log('DEBUG: Current time:', new Date().toISOString());
        console.log('DEBUG: controlsInjected flag:', controlsInjected);
        
        // Check global flag first
        if (controlsInjected) {
            // Check if controls actually exist
            const existingControls = document.querySelector('.klaro-geo-consent-mode-controls');
            if (existingControls) {
                console.log('DEBUG: Controls already injected (global flag)');
                return;
            } else {
                // If controls don't exist despite the flag being set, reset the flag
                console.log('DEBUG: Controls not found despite flag, resetting flag');
                controlsInjected = false;
            }
        }

        // Only inject if the service name matches the one from the template settings
        if (adServiceName !== adStorageServiceName) {
            console.log(
                'DEBUG: Skipping injection for service ' +
                adServiceName +
                ' (configured service is ' +
                adStorageServiceName +
                ')'
            );
            return;
        }

        // Find the ad service element - try multiple selectors to be more robust
        console.log('DEBUG: Looking for service element with name:', adServiceName);
        
        // Dump all service items for debugging first
        const allItems = document.querySelectorAll('li.cm-service');
        console.log('DEBUG: All service items found:', allItems.length);
        allItems.forEach((item, index) => {
            const title = item.querySelector('.cm-service-title') || item.querySelector('.cm-list-title');
            const inputId = item.querySelector('input[type="checkbox"]')?.id;
            console.log('DEBUG: Service', index, 'title:', title ? title.textContent : 'No title', 'input ID:', inputId || 'No input ID');
        });
        
        // Special case for Google Ads - if that's what we're looking for, try a direct approach first
        let serviceElement = null;
        let serviceListItem = null;
        
        if (adServiceName === 'google-ads') {
            console.log('DEBUG: Looking specifically for Google Ads service');
            
            // Try to find by title text first (most reliable)
            const allServices = document.querySelectorAll('li.cm-service');
            for (let i = 0; i < allServices.length; i++) {
                const item = allServices[i];
                const titleElement = item.querySelector('.cm-service-title') || item.querySelector('.cm-list-title');
                
                if (titleElement && titleElement.textContent === 'Google Ads') {
                    console.log('DEBUG: Found Google Ads service by exact title match');
                    serviceListItem = item;
                    serviceElement = item.querySelector('input[type="checkbox"]');
                    break;
                }
            }
            
            // If still not found, try by ID
            if (!serviceElement) {
                serviceElement = document.getElementById('service-item-google-ads');
                if (serviceElement) {
                    console.log('DEBUG: Found Google Ads service by exact ID match');
                    serviceListItem = serviceElement.closest('li.cm-service');
                }
            }
        }
        
        // If not found by special case, try the standard approaches
        if (!serviceElement || !serviceListItem) {
            // First try to find the input element by ID
            serviceElement = document.getElementById('service-item-' + adServiceName);
            
            if (serviceElement) {
                console.log('DEBUG: Found service element by ID');
                // Find the parent li element
                serviceListItem = serviceElement.closest('li.cm-service');
                if (serviceListItem) {
                    console.log('DEBUG: Found service list item from ID');
                }
            }
            
            // If not found by ID or parent not found, try other methods
            if (!serviceElement || !serviceListItem) {
                console.log('DEBUG: Service element or list item not found by ID, trying alternative selectors');
                
                // Try to find by looking at all service list items and checking their title
                const allServiceItems = document.querySelectorAll('li.cm-service');
                console.log('DEBUG: Found', allServiceItems.length, 'service items in total');
                
                for (let i = 0; i < allServiceItems.length; i++) {
                    const item = allServiceItems[i];
                    const titleElement = item.querySelector('.cm-service-title') || item.querySelector('.cm-list-title');
                    
                    if (titleElement) {
                        console.log('DEBUG: Service item', i, 'title:', titleElement.textContent);
                        
                        if (titleElement.textContent.toLowerCase().includes(adServiceName.toLowerCase())) {
                            serviceListItem = item;
                            // Find the input element within this list item
                            serviceElement = item.querySelector('input[type="checkbox"]');
                            console.log('DEBUG: Found matching service by title text:', titleElement.textContent);
                            break;
                        }
                    }
                }
            }
            
            // If still not found, try one more approach with input IDs
            if (!serviceElement || !serviceListItem) {
                console.log('DEBUG: Still not found, trying one more approach with input IDs');
                
                // Try to find any input that contains the service name in its ID
                const inputs = document.querySelectorAll('input[id*="' + adServiceName + '"]');
                console.log('DEBUG: Found', inputs.length, 'inputs containing', adServiceName, 'in their ID');
                
                for (let i = 0; i < inputs.length; i++) {
                    const input = inputs[i];
                    console.log('DEBUG: Checking input with ID:', input.id);
                    
                    serviceListItem = input.closest('li.cm-service');
                    if (serviceListItem) {
                        serviceElement = input;
                        console.log('DEBUG: Found service element by partial ID match');
                        break;
                    }
                }
            }
        }
        
        if (!serviceElement || !serviceListItem) {
            console.log('DEBUG: Service element or list item not found after trying all methods');
            // Dump all service items for debugging
            const allItems = document.querySelectorAll('li.cm-service');
            console.log('DEBUG: All service items found:', allItems.length);
            allItems.forEach((item, index) => {
                const title = item.querySelector('.cm-service-title') || item.querySelector('.cm-list-title');
                console.log('DEBUG: Service', index, 'title:', title ? title.textContent : 'No title');
            });
            return;
        }
        
        console.log('DEBUG: Successfully found service element and list item');

        // Check if we've already injected the controls anywhere in the document
        const existingControls = document.querySelector('.klaro-geo-ad-controls');
        if (existingControls) {
            console.log('DEBUG: Controls already injected somewhere in the document');
            controlsInjected = true;
            return;
        }

        // Set the global flag
        controlsInjected = true;
        console.log('DEBUG: Setting global controlsInjected flag to true');

        console.log('DEBUG: Injecting controls for ' + adServiceName);

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
                    isServiceEnabled = manager.consents[adServiceName] === true;
                    console.log('DEBUG: Got consent state from Klaro manager for', adServiceName, ':', isServiceEnabled);
                    
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

        console.log('Service enabled:', isServiceEnabled, 'Container classes:', controlsContainer.className);

        // Find the parent service checkbox and add an event listener to update child controls
        const parentServiceCheckbox = serviceListItem.querySelector('input[type="checkbox"]');
        if (parentServiceCheckbox) {
            console.log('DEBUG: Found parent service checkbox:', parentServiceCheckbox);
            console.log('DEBUG: Parent checkbox ID:', parentServiceCheckbox.id);
            console.log('DEBUG: Parent checkbox initial state:', parentServiceCheckbox.checked);

            // Log the parent checkbox's parent element to see its structure
            console.log('DEBUG: Parent checkbox parent element:', parentServiceCheckbox.parentNode);

            // Find the parent switch element
            const parentSwitchElement = serviceListItem.querySelector('.cm-switch');
            console.log('DEBUG: Parent switch element found:', parentSwitchElement ? true : false);

            // Add a direct click handler to the parent switch element
            if (parentSwitchElement) {
                console.log('DEBUG: Adding click listener to parent switch element');

                parentSwitchElement.addEventListener('click', function(e) {
                    console.log('DEBUG: Parent switch clicked');
                    console.log('DEBUG: Event:', e);
                    console.log('DEBUG: Parent checkbox state before toggle:', parentServiceCheckbox.checked);

                    // We don't want to stop propagation here as we want Klaro to handle the parent toggle
                    // But we do want to manually update our child controls

                    // The parent will be toggled by Klaro, so we need to use the opposite of the current state
                    const newState = !parentServiceCheckbox.checked;
                    console.log('DEBUG: Expected new parent state after toggle:', newState);

                    // Schedule an update after a short delay to allow Klaro to process the change
                    setTimeout(function() {
                        console.log('DEBUG: Delayed update after parent toggle');
                        console.log('DEBUG: Parent checkbox state after delay:', parentServiceCheckbox.checked);

                        // Update the container disabled state
                        if (parentServiceCheckbox.checked) {
                            controlsContainer.classList.remove('klaro-geo-controls-disabled');
                            console.log('DEBUG: Removed klaro-geo-controls-disabled class');
                        } else {
                            controlsContainer.classList.add('klaro-geo-controls-disabled');
                            console.log('DEBUG: Added klaro-geo-controls-disabled class');
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
                                console.log('DEBUG: Found controls in the next sibling element for parent switch handler');
                            }
                        }

                        // If not found, try to find them in the service list item (for backward compatibility)
                        if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                            const inServicePersonalization = serviceListItem.querySelector('#klaro-geo-ad-personalization');
                            const inServiceUserData = serviceListItem.querySelector('#klaro-geo-ad-user-data');

                            if (inServicePersonalization) {
                                adPersonalizationCheckbox = inServicePersonalization;
                                console.log('DEBUG: Found ad personalization checkbox in service list item for parent switch handler');
                            }

                            if (inServiceUserData) {
                                adUserDataCheckbox = inServiceUserData;
                                console.log('DEBUG: Found ad user data checkbox in service list item for parent switch handler');
                            }
                        }

                        // If still not found, try to find them anywhere in the document as a last resort
                        if (!adPersonalizationCheckbox) {
                            const globalPersonalization = document.querySelector('#klaro-geo-ad-personalization');
                            if (globalPersonalization) {
                                adPersonalizationCheckbox = globalPersonalization;
                                console.log('DEBUG: Found ad personalization checkbox in global document for parent switch handler');
                            }
                        }

                        if (!adUserDataCheckbox) {
                            const globalUserData = document.querySelector('#klaro-geo-ad-user-data');
                            if (globalUserData) {
                                adUserDataCheckbox = globalUserData;
                                console.log('DEBUG: Found ad user data checkbox in global document for parent switch handler');
                            }
                        }

                        console.log('DEBUG: Found ad personalization checkbox:', adPersonalizationCheckbox ? true : false);
                        console.log('DEBUG: Found ad user data checkbox:', adUserDataCheckbox ? true : false);

                        if (adPersonalizationCheckbox) {
                            const oldState = adPersonalizationCheckbox.checked;
                            adPersonalizationCheckbox.checked = parentServiceCheckbox.checked;
                            adPersonalizationConsent = parentServiceCheckbox.checked;
                            console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked);
                        }

                        if (adUserDataCheckbox) {
                            const oldState = adUserDataCheckbox.checked;
                            adUserDataCheckbox.checked = parentServiceCheckbox.checked;
                            adUserDataConsent = parentServiceCheckbox.checked;
                            console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked);
                        }

                        // Update the consent state
                        console.log('DEBUG: Calling updateConsentState() from parent switch click handler');
                        updateConsentState();
                    }, 100);
                });
            }

            // Also add the change event listener to the parent checkbox
            parentServiceCheckbox.addEventListener('change', function(e) {
                console.log('DEBUG: Parent service checkbox change event triggered');
                console.log('DEBUG: Event type:', e.type);
                console.log('DEBUG: Event target:', e.target);
                console.log('DEBUG: Parent checkbox changed to:', this.checked);
                console.log('DEBUG: Event bubbles:', e.bubbles);

                // Update the container disabled state
                if (this.checked) {
                    controlsContainer.classList.remove('klaro-geo-controls-disabled');
                    console.log('DEBUG: Removed klaro-geo-controls-disabled class');
                } else {
                    controlsContainer.classList.add('klaro-geo-controls-disabled');
                    console.log('DEBUG: Added klaro-geo-controls-disabled class');
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
                        console.log('DEBUG: Found controls in the next sibling element for parent checkbox handler');
                    }
                }

                // If not found, try to find them in the service list item (for backward compatibility)
                if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                    const inServicePersonalization = serviceListItem.querySelector('#klaro-geo-ad-personalization');
                    const inServiceUserData = serviceListItem.querySelector('#klaro-geo-ad-user-data');

                    if (inServicePersonalization) {
                        adPersonalizationCheckbox = inServicePersonalization;
                        console.log('DEBUG: Found ad personalization checkbox in service list item for parent handler');
                    }

                    if (inServiceUserData) {
                        adUserDataCheckbox = inServiceUserData;
                        console.log('DEBUG: Found ad user data checkbox in service list item for parent handler');
                    }
                }

                // If still not found, try to find them anywhere in the document as a last resort
                if (!adPersonalizationCheckbox) {
                    const globalPersonalization = document.querySelector('#klaro-geo-ad-personalization');
                    if (globalPersonalization) {
                        adPersonalizationCheckbox = globalPersonalization;
                        console.log('DEBUG: Found ad personalization checkbox in global document for parent handler');
                    }
                }

                if (!adUserDataCheckbox) {
                    const globalUserData = document.querySelector('#klaro-geo-ad-user-data');
                    if (globalUserData) {
                        adUserDataCheckbox = globalUserData;
                        console.log('DEBUG: Found ad user data checkbox in global document for parent handler');
                    }
                }

                console.log('DEBUG: Found ad personalization checkbox:', adPersonalizationCheckbox ? true : false);
                console.log('DEBUG: Found ad user data checkbox:', adUserDataCheckbox ? true : false);

                if (adPersonalizationCheckbox) {
                    const oldState = adPersonalizationCheckbox.checked;
                    adPersonalizationCheckbox.checked = this.checked;
                    adPersonalizationConsent = this.checked;
                    console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked);
                }

                if (adUserDataCheckbox) {
                    const oldState = adUserDataCheckbox.checked;
                    adUserDataCheckbox.checked = this.checked;
                    adUserDataConsent = this.checked;
                    console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked);
                }

                // Update the consent state
                console.log('DEBUG: Calling updateConsentState() from parent checkbox change handler');
                updateConsentState();
            });

            // Set up a MutationObserver to watch for attribute changes on the parent checkbox
            console.log('DEBUG: Setting up MutationObserver for parent checkbox');
            const parentCheckboxObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'checked') {
                        console.log('DEBUG: Parent checkbox checked attribute changed via mutation');
                        console.log('DEBUG: New parent checkbox state:', parentServiceCheckbox.checked);

                        // Update the container disabled state
                        if (parentServiceCheckbox.checked) {
                            controlsContainer.classList.remove('klaro-geo-controls-disabled');
                            console.log('DEBUG: Removed klaro-geo-controls-disabled class via mutation');
                        } else {
                            controlsContainer.classList.add('klaro-geo-controls-disabled');
                            console.log('DEBUG: Added klaro-geo-controls-disabled class via mutation');
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
                                console.log('DEBUG: Found controls in the next sibling element for mutation observer');
                            }
                        }

                        // If not found, try to find them in the service list item (for backward compatibility)
                        if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                            const inServicePersonalization = serviceListItem.querySelector('#klaro-geo-ad-personalization');
                            const inServiceUserData = serviceListItem.querySelector('#klaro-geo-ad-user-data');

                            if (inServicePersonalization) {
                                adPersonalizationCheckbox = inServicePersonalization;
                                console.log('DEBUG: Found ad personalization checkbox in service list item for mutation observer');
                            }

                            if (inServiceUserData) {
                                adUserDataCheckbox = inServiceUserData;
                                console.log('DEBUG: Found ad user data checkbox in service list item for mutation observer');
                            }
                        }

                        // If still not found, try to find them anywhere in the document as a last resort
                        if (!adPersonalizationCheckbox) {
                            const globalPersonalization = document.querySelector('#klaro-geo-ad-personalization');
                            if (globalPersonalization) {
                                adPersonalizationCheckbox = globalPersonalization;
                                console.log('DEBUG: Found ad personalization checkbox in global document for mutation observer');
                            }
                        }

                        if (!adUserDataCheckbox) {
                            const globalUserData = document.querySelector('#klaro-geo-ad-user-data');
                            if (globalUserData) {
                                adUserDataCheckbox = globalUserData;
                                console.log('DEBUG: Found ad user data checkbox in global document for mutation observer');
                            }
                        }

                        if (adPersonalizationCheckbox) {
                            const oldState = adPersonalizationCheckbox.checked;
                            adPersonalizationCheckbox.checked = parentServiceCheckbox.checked;
                            adPersonalizationConsent = parentServiceCheckbox.checked;
                            console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked, 'via mutation');
                        }

                        if (adUserDataCheckbox) {
                            const oldState = adUserDataCheckbox.checked;
                            adUserDataCheckbox.checked = parentServiceCheckbox.checked;
                            adUserDataConsent = parentServiceCheckbox.checked;
                            console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked, 'via mutation');
                        }

                        // Update the consent state
                        console.log('DEBUG: Calling updateConsentState() from mutation observer');
                        updateConsentState();
                    }
                });
            });

            // Start observing the parent checkbox for attribute changes
            parentCheckboxObserver.observe(parentServiceCheckbox, {
                attributes: true
            });
        } else {
            console.log('DEBUG: Could not find parent service checkbox');
        }

        // Create the ad personalization control
        const adPersonalizationControl = createSubControl(
            'ad-personalization',
            'Ad Personalization',
            'Allow personalized ads based on your interests',
            adPersonalizationConsent,
            function(checked) {
                adPersonalizationConsent = checked;
                updateConsentState();
            }
        );

        // Create the ad user data control
        const adUserDataControl = createSubControl(
            'ad-user-data',
            'Ad User Data',
            'Allow use of your data for ad targeting',
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
        const newListItem = document.createElement('li');
        newListItem.className = 'cm-service klaro-geo-consent-mode-controls';

        // Create a div inside the list item (to match Klaro's structure)
        const newListItemDiv = document.createElement('div');
        newListItem.appendChild(newListItemDiv);

        // Add a title to the new list item
        const titleDiv = document.createElement('div');
        titleDiv.className = 'cm-list-title';
        titleDiv.textContent = 'Consent Mode Settings';
        newListItemDiv.appendChild(titleDiv);

        // Add a description paragraph
        const descriptionDiv = document.createElement('div');
        const descriptionPara = document.createElement('p');
        descriptionPara.className = 'cm-service-description';
        descriptionPara.textContent = 'Additional controls for Google Consent Mode v2';
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

        console.log('DEBUG: Inserted controls after service list item');

        // Set a data attribute to mark this service as processed
        serviceListItem.dataset.controlsInjected = 'true';

        // Ensure the global flag is set
        controlsInjected = true;
        console.log('DEBUG: Set controlsInjected flag to true after successful injection');
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

       console.log('Service enabled:', isServiceEnabled, 'Container classes:', controlsContainer.className);

       // Find the service element first
       const serviceElement = document.getElementById('service-item-' + adStorageServiceName);
       if (!serviceElement) {
           console.error('DEBUG: Service element not found for', adStorageServiceName);
           return;
       }
       
       // Find the parent li element
       const serviceListItem = serviceElement.closest('li.cm-service');
       if (!serviceListItem) {
           console.error('DEBUG: Service list item not found for', adServiceName);
           return;
       }
       
       // Find the parent service checkbox and add an event listener to update child controls
       const parentServiceCheckbox = serviceElement.querySelector('input[type="checkbox"]');
       if (parentServiceCheckbox) {
           console.log('DEBUG: Found parent service checkbox:', parentServiceCheckbox);
           console.log('DEBUG: Parent checkbox ID:', parentServiceCheckbox.id);
           console.log('DEBUG: Parent checkbox initial state:', parentServiceCheckbox.checked);

           // Log the parent checkbox's parent element to see its structure
           console.log('DEBUG: Parent checkbox parent element:', parentServiceCheckbox.parentNode);

           // Find the parent switch element
           const parentSwitchElement = serviceElement.querySelector('.cm-switch');
           console.log('DEBUG: Parent switch element found:', parentSwitchElement ? true : false);

           // Add a direct click handler to the parent switch element
           if (parentSwitchElement) {
               console.log('DEBUG: Adding click listener to parent switch element');

               parentSwitchElement.addEventListener('click', function(e) {
                   console.log('DEBUG: Parent switch clicked');
                   console.log('DEBUG: Event:', e);
                   console.log('DEBUG: Parent checkbox state before toggle:', parentServiceCheckbox.checked);

                   // We don't want to stop propagation here as we want Klaro to handle the parent toggle
                   // But we do want to manually update our child controls

                   // The parent will be toggled by Klaro, so we need to use the opposite of the current state
                   const newState = !parentServiceCheckbox.checked;
                   console.log('DEBUG: Expected new parent state after toggle:', newState);

                   // Schedule an update after a short delay to allow Klaro to process the change
                   setTimeout(function() {
                       console.log('DEBUG: Delayed update after parent toggle');
                       console.log('DEBUG: Parent checkbox state after delay:', parentServiceCheckbox.checked);

                       // Update the container disabled state
                       if (parentServiceCheckbox.checked) {
                           controlsContainer.classList.remove('klaro-geo-controls-disabled');
                           console.log('DEBUG: Removed klaro-geo-controls-disabled class');
                       } else {
                           controlsContainer.classList.add('klaro-geo-controls-disabled');
                           console.log('DEBUG: Added klaro-geo-controls-disabled class');
                       }

                       // Get the current state of the checkboxes
                       // First look for our controls in the next sibling (our custom list item)
                       let adPersonalizationCheckbox = null;
                       let adUserDataCheckbox = null;

                       // Try to find controls in the next sibling
                       const controlsListItem = serviceElement.nextElementSibling;
                       if (controlsListItem && controlsListItem.classList.contains('klaro-geo-consent-mode-controls')) {
                           adPersonalizationCheckbox = controlsListItem.querySelector('#klaro-geo-ad-personalization');
                           adUserDataCheckbox = controlsListItem.querySelector('#klaro-geo-ad-user-data');

                           if (adPersonalizationCheckbox && adUserDataCheckbox) {
                               console.log('DEBUG: Found controls in the next sibling element for parent switch handler');
                           }
                       }

                       // If not found, try to find them in the service list item (for backward compatibility)
                       if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                           const inServicePersonalization = serviceElement.querySelector('#klaro-geo-ad-personalization');
                           const inServiceUserData = serviceElement.querySelector('#klaro-geo-ad-user-data');

                           if (inServicePersonalization) {
                               adPersonalizationCheckbox = inServicePersonalization;
                               console.log('DEBUG: Found ad personalization checkbox in service list item for parent switch handler');
                           }

                           if (inServiceUserData) {
                               adUserDataCheckbox = inServiceUserData;
                               console.log('DEBUG: Found ad user data checkbox in service list item for parent switch handler');
                           }
                       }

                       // If still not found, try to find them anywhere in the document as a last resort
                       if (!adPersonalizationCheckbox) {
                           const globalPersonalization = document.querySelector('#klaro-geo-ad-personalization');
                           if (globalPersonalization) {
                               adPersonalizationCheckbox = globalPersonalization;
                               console.log('DEBUG: Found ad personalization checkbox in global document for parent switch handler');
                           }
                       }

                       if (!adUserDataCheckbox) {
                           const globalUserData = document.querySelector('#klaro-geo-ad-user-data');
                           if (globalUserData) {
                               adUserDataCheckbox = globalUserData;
                               console.log('DEBUG: Found ad user data checkbox in global document for parent switch handler');
                           }
                       }

                       console.log('DEBUG: Found ad personalization checkbox:', adPersonalizationCheckbox ? true : false);
                       console.log('DEBUG: Found ad user data checkbox:', adUserDataCheckbox ? true : false);

                       if (adPersonalizationCheckbox) {
                           const oldState = adPersonalizationCheckbox.checked;
                           adPersonalizationCheckbox.checked = parentServiceCheckbox.checked;
                           adPersonalizationConsent = parentServiceCheckbox.checked;
                           console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked);
                       }

                       if (adUserDataCheckbox) {
                           const oldState = adUserDataCheckbox.checked;
                           adUserDataCheckbox.checked = parentServiceCheckbox.checked;
                           adUserDataConsent = parentServiceCheckbox.checked;
                           console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked);
                       }

                       // Update the consent state
                       console.log('DEBUG: Calling updateConsentState() from parent switch click handler');
                       updateConsentState();
                   }, 100);
               });
           }

           // Also add the change event listener to the parent checkbox
           parentServiceCheckbox.addEventListener('change', function(e) {
               console.log('DEBUG: Parent service checkbox change event triggered');
               console.log('DEBUG: Event type:', e.type);
               console.log('DEBUG: Event target:', e.target);
               console.log('DEBUG: Parent checkbox changed to:', this.checked);
               console.log('DEBUG: Event bubbles:', e.bubbles);

               // Update the container disabled state
               if (this.checked) {
                   controlsContainer.classList.remove('klaro-geo-controls-disabled');
                   console.log('DEBUG: Removed klaro-geo-controls-disabled class');
               } else {
                   controlsContainer.classList.add('klaro-geo-controls-disabled');
                   console.log('DEBUG: Added klaro-geo-controls-disabled class');
               }

               // Get the current state of the checkboxes
               // First look for our controls in the next sibling (our custom list item)
               let adPersonalizationCheckbox = null;
               let adUserDataCheckbox = null;

               // Try to find controls in the next sibling
               const controlsListItem = serviceElement.nextElementSibling;
               if (controlsListItem && controlsListItem.classList.contains('klaro-geo-consent-mode-controls')) {
                   adPersonalizationCheckbox = controlsListItem.querySelector('#klaro-geo-ad-personalization');
                   adUserDataCheckbox = controlsListItem.querySelector('#klaro-geo-ad-user-data');

                   if (adPersonalizationCheckbox && adUserDataCheckbox) {
                       console.log('DEBUG: Found controls in the next sibling element for parent checkbox handler');
                   }
               }

               // If not found, try to find them in the service list item (for backward compatibility)
               if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                   const inServicePersonalization = serviceElement.querySelector('#klaro-geo-ad-personalization');
                   const inServiceUserData = serviceElement.querySelector('#klaro-geo-ad-user-data');

                   if (inServicePersonalization) {
                       adPersonalizationCheckbox = inServicePersonalization;
                       console.log('DEBUG: Found ad personalization checkbox in service list item for parent handler');
                   }

                   if (inServiceUserData) {
                       adUserDataCheckbox = inServiceUserData;
                       console.log('DEBUG: Found ad user data checkbox in service list item for parent handler');
                   }
               }

               // If still not found, try to find them anywhere in the document as a last resort
               if (!adPersonalizationCheckbox) {
                   const globalPersonalization = document.querySelector('#klaro-geo-ad-personalization');
                   if (globalPersonalization) {
                       adPersonalizationCheckbox = globalPersonalization;
                       console.log('DEBUG: Found ad personalization checkbox in global document for parent handler');
                   }
               }

               if (!adUserDataCheckbox) {
                   const globalUserData = document.querySelector('#klaro-geo-ad-user-data');
                   if (globalUserData) {
                       adUserDataCheckbox = globalUserData;
                       console.log('DEBUG: Found ad user data checkbox in global document for parent handler');
                   }
               }

               console.log('DEBUG: Found ad personalization checkbox:', adPersonalizationCheckbox ? true : false);
               console.log('DEBUG: Found ad user data checkbox:', adUserDataCheckbox ? true : false);

               if (adPersonalizationCheckbox) {
                   const oldState = adPersonalizationCheckbox.checked;
                   adPersonalizationCheckbox.checked = this.checked;
                   adPersonalizationConsent = this.checked;
                   console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked);
               }

               if (adUserDataCheckbox) {
                   const oldState = adUserDataCheckbox.checked;
                   adUserDataCheckbox.checked = this.checked;
                   adUserDataConsent = this.checked;
                   console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked);
               }

               // Update the consent state
               console.log('DEBUG: Calling updateConsentState() from parent checkbox change handler');
               updateConsentState();
           });

           // Set up a MutationObserver to watch for attribute changes on the parent checkbox
           console.log('DEBUG: Setting up MutationObserver for parent checkbox');
           const parentCheckboxObserver = new MutationObserver(function(mutations) {
               mutations.forEach(function(mutation) {
                   if (mutation.type === 'attributes' && mutation.attributeName === 'checked') {
                       console.log('DEBUG: Parent checkbox checked attribute changed via mutation');
                       console.log('DEBUG: New parent checkbox state:', parentServiceCheckbox.checked);

                       // Update the container disabled state
                       if (parentServiceCheckbox.checked) {
                           controlsContainer.classList.remove('klaro-geo-controls-disabled');
                           console.log('DEBUG: Removed klaro-geo-controls-disabled class via mutation');
                       } else {
                           controlsContainer.classList.add('klaro-geo-controls-disabled');
                           console.log('DEBUG: Added klaro-geo-controls-disabled class');
                       }

                       // Get the current state of the checkboxes
                       // First look for our controls in the next sibling (our custom list item)
                       let adPersonalizationCheckbox = null;
                       let adUserDataCheckbox = null;

                       // Try to find controls in the next sibling
                       const controlsListItem = serviceElement.nextElementSibling;
                       if (controlsListItem && controlsListItem.classList.contains('klaro-geo-consent-mode-controls')) {
                           adPersonalizationCheckbox = controlsListItem.querySelector('#klaro-geo-ad-personalization');
                           adUserDataCheckbox = controlsListItem.querySelector('#klaro-geo-ad-user-data');

                           if (adPersonalizationCheckbox && adUserDataCheckbox) {
                               console.log('DEBUG: Found controls in the next sibling element for mutation observer');
                           }
                       }

                       // If not found, try to find them in the service list item (for backward compatibility)
                       if (!adPersonalizationCheckbox || !adUserDataCheckbox) {
                           const inServicePersonalization = serviceElement.querySelector('#klaro-geo-ad-personalization');
                           const inServiceUserData = serviceElement.querySelector('#klaro-geo-ad-user-data');

                           if (inServicePersonalization) {
                               adPersonalizationCheckbox = inServicePersonalization;
                               console.log('DEBUG: Found ad personalization checkbox in service list item for mutation observer');
                           }

                           if (inServiceUserData) {
                               adUserDataCheckbox = inServiceUserData;
                               console.log('DEBUG: Found ad user data checkbox in service list item for mutation observer');
                           }
                       }

                       // If still not found, try to find them anywhere in the document as a last resort
                       if (!adPersonalizationCheckbox) {
                           const globalPersonalization = document.querySelector('#klaro-geo-ad-personalization');
                           if (globalPersonalization) {
                               adPersonalizationCheckbox = globalPersonalization;
                               console.log('DEBUG: Found ad personalization checkbox in global document for mutation observer');
                           }
                       }

                       if (!adUserDataCheckbox) {
                           const globalUserData = document.querySelector('#klaro-geo-ad-user-data');
                           if (globalUserData) {
                               adUserDataCheckbox = globalUserData;
                               console.log('DEBUG: Found ad user data checkbox in global document for mutation observer');
                           }
                       }

                       console.log('DEBUG: Found ad personalization checkbox:', adPersonalizationCheckbox ? true : false);
                       console.log('DEBUG: Found ad user data checkbox:', adUserDataCheckbox ? true : false);

                       if (adPersonalizationCheckbox) {
                           const oldState = adPersonalizationCheckbox.checked;
                           adPersonalizationCheckbox.checked = parentServiceCheckbox.checked;
                           adPersonalizationConsent = parentServiceCheckbox.checked;
                           console.log('DEBUG: Updated ad personalization checkbox from', oldState, 'to', adPersonalizationCheckbox.checked, 'via mutation');
                       }

                       if (adUserDataCheckbox) {
                           const oldState = adUserDataCheckbox.checked;
                           adUserDataCheckbox.checked = parentServiceCheckbox.checked;
                           adUserDataConsent = parentServiceCheckbox.checked;
                           console.log('DEBUG: Updated ad user data checkbox from', oldState, 'to', adUserDataCheckbox.checked, 'via mutation');
                       }

                       // Update the consent state
                       console.log('DEBUG: Calling updateConsentState() from mutation observer');
                       updateConsentState();
                   }
               });
           });

           // Start observing the parent checkbox for attribute changes
           parentCheckboxObserver.observe(parentServiceCheckbox, {
               attributes: true
           });
       } else {
           console.log('DEBUG: Could not find parent service checkbox');
       }

       // Create the ad personalization control
       const adPersonalizationControl = createSubControl(
           'ad-personalization',
           'Ad Personalization',
           'Allow personalized ads based on your interests',
           adPersonalizationConsent,
           function(checked) {
               adPersonalizationConsent = checked;
               updateConsentState();
           }
       );

       // Create the ad user data control
       const adUserDataControl = createSubControl(
           'ad-user-data',
           'Ad User Data',
           'Allow use of your data for ad targeting',
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
       const newListItem = document.createElement('li');
       newListItem.className = 'cm-service klaro-geo-consent-mode-controls';

       // Create a div inside the list item (to match Klaro's structure)
       const newListItemDiv = document.createElement('div');
       newListItem.appendChild(newListItemDiv);

       // Add a title to the new list item
       const titleDiv = document.createElement('div');
       titleDiv.className = 'cm-list-title';
       titleDiv.textContent = 'Consent Mode Settings';
       newListItemDiv.appendChild(titleDiv);

       // Add a description paragraph
       const descriptionDiv = document.createElement('div');
       const descriptionPara = document.createElement('p');
       descriptionPara.className = 'cm-service-description';
       descriptionPara.textContent = 'Additional controls for Google Consent Mode v2';
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

       console.log('DEBUG: Inserted controls after service list item');

       // Set a data attribute to mark this service as processed
       serviceListItem.dataset.controlsInjected = 'true';

       // Ensure the global flag is set
       controlsInjected = true;
       console.log('DEBUG: Set controlsInjected flag to true after successful injection');


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
        safeUpdateConsent(consentUpdate);
        
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
    
    // Function to hook into Klaro's show method
    function hookKlaroShowMethod() {
        console.log('DEBUG: hookKlaroShowMethod() called');

        let originalKlaroShow;

        if (typeof window.klaro === 'undefined') {
            console.log('DEBUG: window.klaro is undefined');
            return false;
        }
        if (typeof window.klaro.show !== 'function') {
            console.log('DEBUG: window.klaro.show is not a function');
            return false;
        }

        console.log('DEBUG: Klaro show before hook:', window.klaro.show);
        originalKlaroShow = window.klaro.show;
        
        // Override the show method
        window.klaro.show = function() {
            console.log('DEBUG: Klaro show method called');
            console.log('DEBUG: Arguments:', arguments);
            console.log('DEBUG: Current time:', new Date().toISOString());
            
            // Call the original show method
            originalKlaroShow.apply(this, arguments);
            
            // Call our modal open handler
            console.log('DEBUG: Calling handleModalOpen from show method hook');
            setTimeout(handleModalOpen, 100);
            
            return true;
        };
        
        console.log('DEBUG: Successfully hooked into Klaro show method');
        return true;
    }
    
    // Initialize when the DOM is ready
    if (document.readyState === 'loading') {
        console.log('DEBUG: Document is loading, waiting for DOMContentLoaded event');
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DEBUG: DOMContentLoaded - document.readyState:', document.readyState);
            console.log('DEBUG: DOMContentLoaded event triggered, initializing Consent Mode Extension');
            console.log('DEBUG: klaroConsentData available:', typeof window.klaroConsentData !== 'undefined');
            if (typeof window.klaroConsentData !== 'undefined') {
                console.log('DEBUG: klaroConsentData:', window.klaroConsentData);
            }
            
            // Start the initialization process
            initConsentModeExtension();
            
            // Try to hook into Klaro's show method
            if (!hookKlaroShowMethod()) {
                console.log('DEBUG: window.klaro before hook attempt:', window.klaro);
                // If Klaro is not available yet, set up a polling mechanism to try again
                let hookAttempts = 0;
                const MAX_HOOK_ATTEMPTS = 10;
                
                const tryHookingKlaro = function() {
                    hookAttempts++;
                    console.log('DEBUG: Attempting to hook into Klaro show method (attempt ' + hookAttempts + ' of ' + MAX_HOOK_ATTEMPTS + ')');
                    
                    if (hookKlaroShowMethod() || hookAttempts >= MAX_HOOK_ATTEMPTS) {
                        console.log('DEBUG: Finished attempting to hook into Klaro show method');
                        return;
                    }
                    
                    // Try again after a delay
                    setTimeout(tryHookingKlaro, 1000);
                };
                
                // Start trying to hook into Klaro
                setTimeout(tryHookingKlaro, 1000);
            }
        });
    } else {
        console.log('DEBUG: Document already loaded, initializing Consent Mode Extension immediately');
        console.log('DEBUG: klaroConsentData available:', typeof window.klaroConsentData !== 'undefined');
        if (typeof window.klaroConsentData !== 'undefined') {
            console.log('DEBUG: klaroConsentData:', window.klaroConsentData);
        }
        
        // Start the initialization process
        initConsentModeExtension();
        
        // Try to hook into Klaro's show method
        if (!hookKlaroShowMethod()) {
            // If Klaro is not available yet, set up a polling mechanism to try again
            let hookAttempts = 0;
            const MAX_HOOK_ATTEMPTS = 10;
            
            const tryHookingKlaro = function() {
                hookAttempts++;
                console.log('DEBUG: Attempting to hook into Klaro show method (attempt ' + hookAttempts + ' of ' + MAX_HOOK_ATTEMPTS + ')');
                
                if (hookKlaroShowMethod() || hookAttempts >= MAX_HOOK_ATTEMPTS) {
                    console.log('DEBUG: Finished attempting to hook into Klaro show method');
                    return;
                }
                
                // Try again after a delay
                setTimeout(tryHookingKlaro, 500);
            };
            
            // Start trying to hook into Klaro
            setTimeout(tryHookingKlaro, 500);
        }
    }
    
    // Log that the script has loaded
    console.log('DEBUG: Klaro Geo Consent Mode Extension script loaded');
})();