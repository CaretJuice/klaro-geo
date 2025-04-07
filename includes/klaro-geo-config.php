<?php 

// Function to generate klaro-config.js
function klaro_geo_generate_config_file() {
    // Get the services using the service settings class
    $service_settings = new Klaro_Geo_Service_Settings();
    $services = $service_settings->get();

    if (empty($services) || !is_array($services) || !isset($services[0]['name'])) {
        klaro_geo_debug_log('Invalid or empty services, using default');
        $services = $service_settings->get_default_services();
        // Update the services using the service settings class
        $service_settings->set($services);
        $service_settings->save();
    }

    klaro_geo_debug_log('Services to be used in config: ' . print_r($services, true));


    // Initialize config with template settings
    $klaro_config = array();

    // Get settings using new nested structure
    $settings_option = get_option('klaro_geo_country_settings');

    // Check if the option is already an array (not JSON encoded)
    if (is_array($settings_option)) {
        $settings = $settings_option;
        klaro_geo_debug_log('Country settings already an array, no decoding needed');
    } else {
        // Try to decode the JSON string
        $settings = json_decode($settings_option, true);
        klaro_geo_debug_log('Decoded country settings from JSON string');
    }

    // If empty or invalid, use default settings
    if (empty($settings) || !is_array($settings)) {
        $settings = klaro_geo_get_default_geo_settings();
        klaro_geo_debug_log('Using default geo settings');
    }

    // Get user location
    $location = klaro_geo_get_user_location();
    $user_country = $location['country'];
    $user_region = $location['region'];
    $using_debug_geo = $location['is_admin_override'];

    klaro_geo_debug_log('User location - Country: ' . $user_country . ', Region: ' . $user_region .
                       ($using_debug_geo ? ' (admin override)' : ''));

    // Get effective settings for the location, passing the admin override flag
    $effective_settings = klaro_geo_get_effective_settings(
        $user_country . ($user_region ? '-' . $user_region : ''),
        $using_debug_geo
    );

    // Default template source to fallback for dataLayer
    $template_source = 'fallback';

    // Override template source by detected template
    if (isset($effective_settings['source'])) {
        $template_source = $effective_settings['source'];
    }

    klaro_geo_debug_log('Template source: ' . $template_source . ($using_debug_geo ? ' (admin debug override)' : ''));

    // Extract template from effective settings
    $template_to_use = $effective_settings['template'] ?? 'default';

    klaro_geo_debug_log('Using template: ' . $template_to_use);

    // Get template configuration from the database
    $template_settings = new Klaro_Geo_Template_Settings();
    $templates = $template_settings->get();
    klaro_geo_debug_log('Available templates from database: ' . print_r($templates, true));

    // Get the template config from the database, or fall back to default if not found
    if (!function_exists('klaro_geo_get_default_templates')) {
        // If the function doesn't exist, include the defaults file that defines it
        require_once dirname(__FILE__) . '/klaro-geo-defaults.php';
    }
    $template_config = $templates[$template_to_use] ?? $templates['default'] ?? klaro_geo_get_default_templates()['default'];
    klaro_geo_debug_log('Template config: ' . print_r($template_config, true));

    // Check if consent receipts are enabled
    $enable_consent_receipts = get_option('klaro_geo_enable_consent_receipts', false);
    klaro_geo_debug_log('Consent receipts enabled: ' . ($enable_consent_receipts ? 'yes' : 'no'));

    // Initialize custom template settings that are not part of klaroConfig
    $custom_template_settings = array(
        'enableConsentLogging' => $enable_consent_receipts // Use the global setting by default
    );

    // Get plugin settings from the template
    if (isset($template_config['plugin_settings'])) {
        // Get enableConsentLogging setting
        if (isset($template_config['plugin_settings']['enable_consent_logging'])) {
            $custom_template_settings['enableConsentLogging'] = $template_config['plugin_settings']['enable_consent_logging'];
            klaro_geo_debug_log('Using enableConsentLogging from plugin_settings: ' . ($custom_template_settings['enableConsentLogging'] ? 'true' : 'false'));
        }
    }

    // Apply template configuration
    if (isset($template_config['config'])) {
        // Log the template config for debugging
        klaro_geo_debug_log('Template config before applying: ' . print_r($template_config['config'], true));

        // Copy all config values
        foreach ($template_config['config'] as $key => $value) {
            // Skip translations for now, we'll handle them separately
            if ($key === 'translations' || $key === 'translations_json') {
                continue;
            }
            
            // Special handling for consent_mode_settings to prevent gtag calls during initialization
            if ($key === 'consent_mode_settings' && is_array($value)) {
                // Create a copy of the value
                $consent_mode_settings = $value;
                
                // Remove the initialization_code from the config
                // We'll handle it separately in the JavaScript
                if (isset($consent_mode_settings['initialization_code'])) {
                    $consent_mode_settings['initialization_code_original'] = $consent_mode_settings['initialization_code'];
                    $consent_mode_settings['initialization_code'] = '// Initialization code moved to JavaScript';
                }
                
                // Copy the modified value
                $klaro_config[$key] = $consent_mode_settings;
            } else {
                // Copy the value directly to klaroConfig
                $klaro_config[$key] = $value;
            }
        }

        // Log the klaro_config after applying template settings
        klaro_geo_debug_log('klaro_config after applying template settings: ' . print_r($klaro_config, true));
        klaro_geo_debug_log('Custom template settings: ' . print_r($custom_template_settings, true));

        // Handle translations - prioritize translations_json if it exists and is valid
        if (isset($template_config['config']['translations_json'])) {
            $translations_json = $template_config['config']['translations_json'];
            $decoded_translations = json_decode($translations_json, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_translations)) {
                // Use the decoded JSON translations
                $klaro_config['translations'] = $decoded_translations;
                klaro_geo_debug_log('Using translations from JSON field');
            } else {
                // Fall back to the basic translations if JSON is invalid
                $klaro_config['translations'] = $template_config['config']['translations'] ?? [];
                klaro_geo_debug_log('Invalid translations JSON, using basic translations');
            }
        } else {
            // Use the basic translations
            $klaro_config['translations'] = $template_config['config']['translations'] ?? [];
            klaro_geo_debug_log('Using basic translations');
        }

        klaro_geo_debug_log('Applied template settings - default: ' .
            (($klaro_config['default'] ?? false) ? 'true' : 'false') . ', required: ' .
            (($klaro_config['required'] ?? false) ? 'true' : 'false'));

        // Log additional settings
        klaro_geo_debug_log('Additional settings - noAutoLoad: ' .
            (($klaro_config['noAutoLoad'] ?? false) ? 'true' : 'false') . ', embedded: ' .
            (($klaro_config['embedded'] ?? false) ? 'true' : 'false') . ', autoFocus: ' .
            (($klaro_config['autoFocus'] ?? false) ? 'true' : 'false') . ', showNoticeTitle: ' .
            (($klaro_config['showNoticeTitle'] ?? false) ? 'true' : 'false'));
    } else {
        // Ensure default values are set if template config is missing
        $klaro_config['default'] = false;
        $klaro_config['required'] = false;
        $klaro_config['noAutoLoad'] = false;
        $klaro_config['embedded'] = false;
        $klaro_config['showDescriptionEmptyStore'] = true;
        $klaro_config['autoFocus'] = false;
        $klaro_config['showNoticeTitle'] = false;
        $klaro_config['disablePoweredBy'] = false;
        $klaro_config['cookiePath'] = '/';
    }
    klaro_geo_debug_log('Template config applied: ' . print_r($klaro_config, true));

    // Build the services configuration
    $klaro_config['services'] = array();

    // Check if consent mode is enabled in the template
    // First check if consent_mode_settings is directly in the template
    if (isset($template_config['consent_mode_settings'])) {
        $consent_mode_settings = $template_config['consent_mode_settings'];
    } 
    // Then check if it's in the config array (as set by the admin form)
    else if (isset($template_config['config']) && isset($template_config['config']['consent_mode_settings'])) {
        $consent_mode_settings = $template_config['config']['consent_mode_settings'];
    } else {
        $consent_mode_settings = [];
    }

    $initialize_consent_mode = isset($consent_mode_settings['initialize_consent_mode']) ?
        filter_var($consent_mode_settings['initialize_consent_mode'], FILTER_VALIDATE_BOOLEAN) : false;

    $analytics_storage_service = isset($consent_mode_settings['analytics_storage_service']) ?
        $consent_mode_settings['analytics_storage_service'] : 'no_service';

    $ad_storage_service = isset($consent_mode_settings['ad_storage_service']) ?
        $consent_mode_settings['ad_storage_service'] : 'no_service';

    $initialization_code = isset($consent_mode_settings['initialization_code']) ?
        $consent_mode_settings['initialization_code'] : '';

    klaro_geo_debug_log('Consent mode settings from template - initialize_consent_mode: ' . ($initialize_consent_mode ? 'true' : 'false'));
    klaro_geo_debug_log('Consent mode settings from template - analytics_storage_service: ' . $analytics_storage_service);
    klaro_geo_debug_log('Consent mode settings from template - ad_storage_service: ' . $ad_storage_service);

    // Process each service
    foreach ($services as $service) {
        klaro_geo_debug_log('Processing service: ' . print_r($service, true));
        $service_config = array(
            'name' => $service['name'] ?? 'undefined',
            'purposes' => $service['purposes'] ?? $service['service_purposes'] ?? array('analytics'),
            'cookies' => isset($service['cookies']) ? $service['cookies'] : array(),
            'onInit' => isset($service['callback']['onInit']) ? $service['callback']['onInit'] : ($service['onInit'] ?? ''),
            'onAccept' => isset($service['callback']['onAccept']) ? $service['callback']['onAccept'] : ($service['onAccept'] ?? ''),
            'onDecline' => isset($service['callback']['onDecline']) ? $service['callback']['onDecline'] : ($service['onDecline'] ?? '')
        );

        // Only add required and default if they're explicitly set (not null)
        // This allows inheriting these values from the template
        if (isset($service['required']) && $service['required'] !== null) {
            $service_config['required'] = filter_var($service['required'], FILTER_VALIDATE_BOOLEAN);
            klaro_geo_debug_log('Service ' . $service['name'] . ' has explicit required setting: ' .
                ($service_config['required'] ? 'true' : 'false'));
        } else {
            klaro_geo_debug_log('Service ' . $service['name'] . ' inherits required setting from template');
        }

        if (isset($service['default']) && $service['default'] !== null) {
            $service_config['default'] = filter_var($service['default'], FILTER_VALIDATE_BOOLEAN);
            klaro_geo_debug_log('Service ' . $service['name'] . ' has explicit default setting: ' .
                ($service_config['default'] ? 'true' : 'false'));
        } else {
            klaro_geo_debug_log('Service ' . $service['name'] . ' inherits default setting from template');
        }

        // Add optional fields if they exist
        if (isset($service['optOut'])) {
            $service_config['optOut'] = filter_var($service['optOut'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($service['onlyOnce'])) {
            $service_config['onlyOnce'] = filter_var($service['onlyOnce'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($service['contextualConsentOnly'])) {
            $service_config['contextualConsentOnly'] = filter_var($service['contextualConsentOnly'], FILTER_VALIDATE_BOOLEAN);
        }

        // Add translations if they exist
        if (isset($service['translations']) && !empty($service['translations'])) {
            $service_config['translations'] = $service['translations'];
        }

        // Log the callback values for debugging
        klaro_geo_debug_log('Service ' . $service['name'] . ' callbacks:');
        klaro_geo_debug_log('  onInit: ' . (isset($service['callback']['onInit']) ? 'From callback array' : 'From direct property'));
        klaro_geo_debug_log('  onAccept: ' . (isset($service['callback']['onAccept']) ? 'From callback array' : 'From direct property'));
        klaro_geo_debug_log('  onDecline: ' . (isset($service['callback']['onDecline']) ? 'From callback array' : 'From direct property'));

        // Apply Google Consent Mode modifications if enabled
        if ($initialize_consent_mode) {
            // Check if this is the Google Tag Manager service and if initialize_consent_mode is true
            if ($service['name'] === 'google-tag-manager' && $initialize_consent_mode && !empty($initialization_code)) {
                // Store the initialization code but don't append it directly
                // We'll handle it in JavaScript to prevent gtag errors
                $service_config['initialization_code_original'] = $initialization_code;
                $service_config['onInit'] = $service_config['onInit'] . "\n" . '// Initialization code moved to JavaScript';
                klaro_geo_debug_log('Stored initialization code for GTM to be handled in JavaScript');
            }

            // Check if this service matches the analytics storage event
            $is_analytics_service = $analytics_storage_service !== 'no_service' && $service['name'] === $analytics_storage_service;

            // Check if this service matches the ad storage event
            $is_ad_service = $ad_storage_service !== 'no_service' && $service['name'] === $ad_storage_service;

            // Modify onAccept callback for analytics storage
            if ($is_analytics_service) {
                // Store the original code but use a safer version that checks for gtag
                $analytics_accept_code = "\nif (typeof window.gtag === 'function') {\n  window.gtag('consent', 'update', {\n    analytics_storage: 'granted',\n  });\n}\n";
                $service_config['onAccept'] = $service_config['onAccept'] . $analytics_accept_code;
                klaro_geo_debug_log('Added analytics_storage accept code to ' . $service['name']);
                klaro_geo_debug_log('Updated onAccept callback: ' . $service_config['onAccept']);

                // Add onDecline callback for analytics storage if not already present
                $analytics_decline_code = "\nif (typeof window.gtag === 'function') {\n  window.gtag('consent', 'update', {\n    analytics_storage: 'denied',\n  });\n}\n";
                $service_config['onDecline'] = $service_config['onDecline'] . $analytics_decline_code;
                klaro_geo_debug_log('Added analytics_storage decline code to ' . $service['name']);
                klaro_geo_debug_log('Updated onDecline callback: ' . $service_config['onDecline']);
            }

            // Modify onAccept callback for ad storage
            if ($is_ad_service) {
                $ad_accept_code = "\nif (typeof window.gtag === 'function') {\n  window.gtag('consent', 'update', {\n    ad_storage: 'granted',\n    ad_user_data: 'granted',\n    ad_personalization: 'granted'\n  });\n}\n";
                $service_config['onAccept'] = $service_config['onAccept'] . $ad_accept_code;
                klaro_geo_debug_log('Added ad_storage accept code to ' . $service['name']);
                klaro_geo_debug_log('Updated onAccept callback: ' . $service_config['onAccept']);

                // Add onDecline callback for ad storage if not already present
                $ad_decline_code = "\nif (typeof window.gtag === 'function') {\n  window.gtag('consent', 'update', {\n    ad_storage: 'denied',\n    ad_user_data: 'denied',\n    ad_personalization: 'denied'\n  });\n}\n";
                $service_config['onDecline'] = $service_config['onDecline'] . $ad_decline_code;
                klaro_geo_debug_log('Added ad_storage decline code to ' . $service['name']);
                klaro_geo_debug_log('Updated onDecline callback: ' . $service_config['onDecline']);
            }

            // Our custom code to control the 'checked' attribute
            // This code should be executed *after* the consent mode updates
            if ($is_ad_service || $is_analytics_service) {
                $checkbox_accept_code = "\n" .
                "const adPersonalizationCheckbox = document.querySelector('#klaro-geo-ad-personalization');\n" .
                "const adUserDataCheckbox = document.querySelector('#klaro-geo-ad-user-data');\n" .
                "if (adPersonalizationCheckbox) {\n" .
                "    adPersonalizationCheckbox.checked = true;\n" .
                "}\n" .
                "if (adUserDataCheckbox) {\n" .
                "    adUserDataCheckbox.checked = true;\n" .
                "}\n" .
                "// Remove disabled class from controls container\n" .
                "const controlsContainer = document.querySelector('.klaro-geo-ad-controls');\n" .
                "if (controlsContainer) {\n" .
                "    controlsContainer.classList.remove('klaro-geo-controls-disabled');\n" .
                "}\n";

                $service_config['onAccept'] = $service_config['onAccept'] . $checkbox_accept_code;
                klaro_geo_debug_log('Added checkbox control code to onAccept for ' . $service['name']);

                $checkbox_decline_code = "\n" .
                "const adPersonalizationCheckbox = document.querySelector('#klaro-geo-ad-personalization');\n" .
                "const adUserDataCheckbox = document.querySelector('#klaro-geo-ad-user-data');\n" .
                "if (adPersonalizationCheckbox) {\n" .
                "    adPersonalizationCheckbox.checked = false;\n" .
                "}\n" .
                "if (adUserDataCheckbox) {\n" .
                "    adUserDataCheckbox.checked = false;\n" .
                "}\n" .
                "// Add disabled class to controls container\n" .
                "const controlsContainer = document.querySelector('.klaro-geo-ad-controls');\n" .
                "if (controlsContainer) {\n" .
                "    controlsContainer.classList.add('klaro-geo-controls-disabled');\n" .
                "}\n";

                $service_config['onDecline'] = $service_config['onDecline'] . $checkbox_decline_code;
                klaro_geo_debug_log('Added checkbox control code to onDecline for ' . $service['name']);
            }
        }

        $klaro_config['services'][] = $service_config;
    }

    // Add global consent handlers for GTM integration using the new callback approach
    $global_consent_js = <<<JS
    // Global variable to store the current Klaro opts
    window.currentKlaroOpts = null;

    // Function to handle consent updates and trigger dataLayer events
    function handleConsentUpdate(manager, eventType, data) {
        console.log('Klaro consent update:', eventType, data);

        if (eventType === 'saveConsents') {
            // Get the current consents
            var consents = manager.consents;
            console.log('Klaro consent data:', consents);

            // Store the current opts for use by the consent receipt handler
            window.currentKlaroOpts = { consents: consents };

            // Populate acceptedServices for dataLayer
            var acceptedServices = [];
            for (var serviceName in consents) {
                if (consents[serviceName] === true) {
                    acceptedServices.push(serviceName);
                }
            }

            // Get custom template settings
            var customTemplateSettings = window.klaroConsentData || {};

            // Push to dataLayer
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                'event': 'Klaro Consent',
                'acceptedServices': acceptedServices,
                'consentType': data.type // 'save', 'accept', or 'decline'
            });

            // Set a timestamp to help prevent duplicate processing
            window.lastWatcherConsentTimestamp = Date.now();

            // Create a synthetic event object for the handleConsentChange function
            var syntheticEvent = {
                detail: {
                    manager: manager
                }
            };

            // Call the existing handleConsentChange function from klaro-geo-consent-receipts.js
            if (typeof handleConsentChange === 'function') {
                console.log('Triggering handleConsentChange from watcher');
                handleConsentChange(syntheticEvent);
            } else {
                console.warn('handleConsentChange function not found. Make sure klaro-geo-consent-receipts.js is loaded.');
            }
        }
    }

    // Initialize the consent manager watcher when Klaro is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for Klaro to be available
        var klaroWatcherInterval = setInterval(function() {
            if (window.klaro && typeof window.klaro.getManager === 'function') {
                clearInterval(klaroWatcherInterval);

                // Get the manager and set up the watcher
                var manager = window.klaro.getManager();
                if (manager) {
                    manager.watch({
                        update: handleConsentUpdate
                    });
                    console.log('Klaro consent manager watcher initialized');
                }
            }
        }, 100);
    });
    JS;

    // Store the global consent JS for later use
    // Transform styling settings from object to array format if needed
    if (isset($klaro_config['styling']) && isset($klaro_config['styling']['theme'])) {
        klaro_geo_debug_log('Found styling theme settings: ' . print_r($klaro_config['styling']['theme'], true));

        if (is_array($klaro_config['styling']['theme'])) {
            // Check if theme is in object format (with keys like 'color', 'position', 'width')
            // We need to check if it's an associative array rather than a numeric array
            $is_associative = false;
            foreach (array_keys($klaro_config['styling']['theme']) as $key) {
                if (is_string($key)) {
                    $is_associative = true;
                    break;
                }
            }

            klaro_geo_debug_log('Styling theme is ' . ($is_associative ? 'an associative array (object format)' : 'a numeric array (already in correct format)'));

            if ($is_associative) {

            // Extract values from the object
            $theme_values = array();

            // Add color if it exists
            if (isset($klaro_config['styling']['theme']['color'])) {
                $theme_values[] = $klaro_config['styling']['theme']['color'];
            }

            // Add position if it exists
            if (isset($klaro_config['styling']['theme']['position'])) {
                $theme_values[] = $klaro_config['styling']['theme']['position'];
            }

            // Add width if it exists
            if (isset($klaro_config['styling']['theme']['width'])) {
                $theme_values[] = $klaro_config['styling']['theme']['width'];
            }

            // Replace the object with the array
            $klaro_config['styling']['theme'] = $theme_values;

            klaro_geo_debug_log('Transformed styling theme from object to array format: ' . print_r($theme_values, true));
        }
    }

    // Log the final config for debugging
    if (isset($klaro_config['styling'])) {
        klaro_geo_debug_log('Final styling configuration: ' . print_r($klaro_config['styling'], true));
    }

    // Generate the JavaScript content
    $klaro_config_content = "// Detected/Debug Country Code: " . esc_js($user_country) . "\n\n";

    // Format the klaroConfig variable in a way that's easy to parse for tests
    $klaro_config_content .= 'var klaroConfig = ' . wp_json_encode($klaro_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";

    // Add a clear separator comment to help with parsing
    $klaro_config_content .= "// ===== END OF KLARO CONFIG =====\n\n";

    // Add the global consent handler (always included for compatibility)
    $klaro_config_content .= $global_consent_js . "\n";


    // Add dataLayer push for debugging
    $dataLayer_push = array(
        'event' => 'Klaro Config Loaded',
        'klaro_geo_consent_template' => $template_to_use,
        'klaro_geo_template_source' => $template_source,
        'klaro_geo_detected_country' => !empty($user_country) ? $user_country : null,
        'klaro_geo_detected_region' => !empty($user_region) ? $user_region : null,
        'klaro_geo_admin_override' => $using_debug_geo
    );

    klaro_geo_debug_log('enableConsentLogging setting: ' . ($custom_template_settings['enableConsentLogging'] ? 'true' : 'false'));

    $klaro_config_content .= "// Push debug information to dataLayer\n";
    $klaro_config_content .= "window.dataLayer = window.dataLayer || [];\n";
    $klaro_config_content .= "window.dataLayer.push(" . wp_json_encode($dataLayer_push, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ");\n\n";

    // Echo the content for testing purposes
    klaro_geo_debug_log('var klaroConfig = ' . wp_json_encode($klaro_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";");
    klaro_geo_debug_log('dataLayer push: ' . wp_json_encode($dataLayer_push, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


    // Define variables for consent receipts
    $admin_ajax_url = admin_url('admin-ajax.php');
    $consent_nonce = wp_create_nonce('klaro_geo_consent_nonce');

    // Get the consent_mode value for JavaScript
    $consent_mode = $initialize_consent_mode ? 'v2' : 'none';
    $consent_mode_js = wp_json_encode($consent_mode);

    klaro_geo_debug_log('Using consent_mode: ' . $consent_mode);
    klaro_geo_debug_log('Template config consent_mode_settings: ' . print_r($consent_mode_settings, true));

    // No need to append gtag initialization code here as it's added to the Google Tag Manager service

    // Add consent receipt functionality if enabled
    if ($enable_consent_receipts) {
        // Prepare template settings values
        $modal_title = isset($template_config['config']['translations']['en']['consentModal']['title']) ?
            $template_config['config']['translations']['en']['consentModal']['title'] : 'Privacy Settings';

        $modal_description = isset($template_config['config']['translations']['en']['consentModal']['description']) ?
            $template_config['config']['translations']['en']['consentModal']['description'] : '';

        $accept_all_text = isset($template_config['config']['translations']['en']['acceptAll']) ?
            $template_config['config']['translations']['en']['acceptAll'] : 'Accept All';

        $decline_all_text = isset($template_config['config']['translations']['en']['declineAll']) ?
            $template_config['config']['translations']['en']['declineAll'] : 'Decline All';

        $default_consent = isset($template_config['config']['default']) && $template_config['config']['default'] ? 'true' : 'false';
        $required_consent = isset($template_config['config']['required']) && $template_config['config']['required'] ? 'true' : 'false';
        $admin_override_value = $using_debug_geo ? 'true' : 'false';

        // Prepare custom template settings for JavaScript
        $enable_consent_logging_value = $custom_template_settings['enableConsentLogging'] ? 'true' : 'false';

        // Pre-calculate values for heredoc
        $initialize_consent_mode_js = $initialize_consent_mode ? 'true' : 'false';

        // Add variables for the consent receipts script
        $klaro_config_content .= <<<JS
// Consent Receipt Configuration
window.klaroConsentData = {
    templateName: "{$template_to_use}",
    templateSource: "{$template_source}",
    detectedCountry: "{$user_country}",
    detectedRegion: "{$user_region}",
    adminOverride: {$admin_override_value},
    ajaxUrl: "{$admin_ajax_url}",
    nonce: "{$consent_nonce}",
    enableConsentLogging: {$enable_consent_logging_value},
    consentMode: {$consent_mode_js},
    templateSettings: {
        consentModalTitle: "{$modal_title}",
        consentModalDescription: "{$modal_description}",
        acceptAllText: "{$accept_all_text}",
        declineAllText: "{$decline_all_text}",
        defaultConsent: {$default_consent},
        requiredConsent: {$required_consent},
        config: {
            consent_mode_settings: {
                initialize_consent_mode: {$initialize_consent_mode_js},
                analytics_storage_service: "{$analytics_storage_service}",
                ad_storage_service: "{$ad_storage_service}",
                initialization_code: `{$initialization_code}`
            }
        }
    }
};
JS;
    }

    // Write the content to klaro-config.js in the plugin root directory
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $klaro_config_file = $plugin_dir . 'klaro-config.js';
    
    klaro_geo_debug_log('Plugin directory: ' . $plugin_dir);
    klaro_geo_debug_log('Config file path: ' . $klaro_config_file);
    klaro_geo_debug_log('Directory writable: ' . (is_writable(dirname($klaro_config_file)) ? 'yes' : 'no'));
    klaro_geo_debug_log('File exists: ' . (file_exists($klaro_config_file) ? 'yes' : 'no'));

    $result = file_put_contents($klaro_config_file, $klaro_config_content);
    if ($result === false) {
        klaro_geo_debug_log('Failed to write config file');
    }
    return $result;
}
}
?>