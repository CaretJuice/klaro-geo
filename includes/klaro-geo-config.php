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
    $template_config = $templates[$template_to_use] ?? $templates['default'] ?? klaro_geo_get_default_templates()['default'];
    klaro_geo_debug_log('Template config: ' . print_r($template_config, true));

    // Check if consent receipts are enabled
    $enable_consent_receipts = get_option('klaro_geo_enable_consent_receipts', false);
    klaro_geo_debug_log('Consent receipts enabled: ' . ($enable_consent_receipts ? 'yes' : 'no'));

    // Initialize custom template settings that are not part of klaroConfig
    $custom_template_settings = array(
        'consentMode' => 'basic',
        'enableConsentLogging' => $enable_consent_receipts // Use the global setting by default
    );

    // Get plugin settings from the template
    if (isset($template_config['plugin_settings'])) {
        // Get consent_mode setting
        if (isset($template_config['plugin_settings']['consent_mode'])) {
            $custom_template_settings['consentMode'] = $template_config['plugin_settings']['consent_mode'];
            klaro_geo_debug_log('Using consentMode from plugin_settings: ' . $custom_template_settings['consentMode']);
        }

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

            // Copy the value directly to klaroConfig
            $klaro_config[$key] = $value;
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

    // Process each service
    foreach ($services as $service) {
        klaro_geo_debug_log('Processing service: ' . print_r($service, true));
        $service_config = array(
            'name' => $service['name'] ?? 'undefined',
            'required' => isset($service['required']) ? filter_var($service['required'], FILTER_VALIDATE_BOOLEAN) : false,
            'default' => isset($service['default']) ? filter_var($service['default'], FILTER_VALIDATE_BOOLEAN) : false,
            'purposes' => $service['purposes'] ?? $service['service_purposes'] ?? array('analytics'),
            'cookies' => isset($service['cookies']) ? $service['cookies'] : array(),
            'onInit' => $service['onInit'] ?? '',
            'onAccept' => $service['onAccept'] ?? '',
            'onDecline' => $service['onDecline'] ?? ''
        );

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

        // Use service-specific callbacks if they exist
        if (isset($service['onInit'])) {
            $service_config['onInit'] = $service['onInit'];
        }

        if (isset($service['onAccept'])) {
            $service_config['onAccept'] = $service['onAccept'];
        }

        if (isset($service['onDecline'])) {
            $service_config['onDecline'] = $service['onDecline'];
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
            // Handle the consentMode which might be stored with quotes
            var consentMode = customTemplateSettings.consentMode || 'basic';
            // Remove quotes if they exist
            consentMode = consentMode.replace(/^['"]|['"]$/g, '');

            // Push to dataLayer
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                'event': 'Klaro Consent',
                'acceptedServices': acceptedServices,
                'consentMode': consentMode,
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

    // Add gtag default consent call before dataLayer push
    $klaro_config_content .= "// Initialize consent defaults (if needed)\n";

    // Add the global consent handler
    $klaro_config_content .= $global_consent_js . "\n";


    // Add dataLayer push for debugging
    $dataLayer_push = array(
        'event' => 'Klaro Config Loaded',
        'klaro_geo_consent_template' => $template_to_use,
        'klaro_geo_template_source' => $template_source,
        'klaro_geo_detected_country' => !empty($user_country) ? $user_country : null,
        'klaro_geo_detected_region' => !empty($user_region) ? $user_region : null,
        'klaro_geo_admin_override' => $using_debug_geo,
        'consentMode' => $custom_template_settings['consentMode']
    );

    klaro_geo_debug_log('Adding consentMode to dataLayer push: ' . $custom_template_settings['consentMode']);
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
    $consent_mode = isset($klaro_config['consent_mode']) ? $klaro_config['consent_mode'] : 'none';
    $consent_mode_js = wp_json_encode($consent_mode);

    klaro_geo_debug_log('Using consent_mode: ' . $consent_mode);

    // Append additional JavaScript code
    $klaro_config_content .= <<<JS
// Initialize gtag function if it doesn't exist
if (typeof gtag !== 'function') {
    function gtag(){dataLayer.push(arguments);}
}
JS;

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
        $consent_mode_value = !empty($custom_template_settings['consentMode']) ? "'" . $custom_template_settings['consentMode'] . "'" : "'basic'";

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
    consentMode: {$consent_mode_value},
    templateSettings: {
        consentModalTitle: "{$modal_title}",
        consentModalDescription: "{$modal_description}",
        acceptAllText: "{$accept_all_text}",
        declineAllText: "{$decline_all_text}",
        defaultConsent: {$default_consent},
        requiredConsent: {$required_consent}
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