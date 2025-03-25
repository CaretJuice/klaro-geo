<?php 

// Function to generate klaro-config.js
function klaro_geo_generate_config_file() {
    // Get the services array from the database
    $services_json = get_option('klaro_geo_services');
    klaro_geo_debug_log('Raw services JSON from DB: ' . print_r($services_json, true));
    $services = klaro_geo_validate_services();
    klaro_geo_debug_log('Decoded services: ' . print_r($services, true));

    if (empty($services) || !is_array($services) || !isset($services[0]['name'])) {
        klaro_geo_debug_log('Invalid or empty services, using default');
        global $default_services;
        $services = $default_services;
        // Update the option with default services
        $encoded_services = wp_json_encode($default_services, JSON_PRETTY_PRINT);
        update_option('klaro_geo_services', $encoded_services);
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

    // Determine template source for dataLayer
    $template_source = 'fallback';

    // Use the source from effective settings
    if (isset($effective_settings['source'])) {
        $template_source = $effective_settings['source'];
    }

    klaro_geo_debug_log('Template source: ' . $template_source . ($using_debug_geo ? ' (admin debug override)' : ''));

    // Extract template from effective settings
    $template_to_use = $effective_settings['template'] ?? 'default';

    klaro_geo_debug_log('Using template: ' . $template_to_use);

    // Get template configuration
    $templates = klaro_geo_get_default_templates();
    klaro_geo_debug_log('Available templates: ' . print_r($templates, true));
    klaro_geo_debug_log('Selected template: ' . $template_to_use);

    $template_config = $templates[$template_to_use] ?? $templates['default'];
    klaro_geo_debug_log('Template config: ' . print_r($template_config, true));

    // Apply template configuration
    if (isset($template_config['config'])) {
        // Copy all config values
        foreach ($template_config['config'] as $key => $value) {
            // Skip translations for now, we'll handle them separately
            if ($key === 'translations' || $key === 'translations_json') {
                continue;
            }

            // Copy the value directly
            $klaro_config[$key] = $value;
        }

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

    // Get the purpose settings once
    $analytics_purposes = get_option('klaro_geo_analytics_purposes', array('analytics'));
    if (!is_array($analytics_purposes)) {
        $analytics_purposes = json_decode($analytics_purposes, true);
        if (!is_array($analytics_purposes)) {
            $analytics_purposes = array('analytics');
        }
    }

    $ad_purposes = get_option('klaro_geo_ad_purposes', array('advertising'));
    if (!is_array($ad_purposes)) {
        $ad_purposes = json_decode($ad_purposes, true);
        if (!is_array($ad_purposes)) {
            $ad_purposes = array('advertising');
        }
    }

    // Track which services have which purposes
    $has_analytics_services = false;
    $has_ad_services = false;

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

        klaro_geo_debug_log('Processing service: ' . print_r($service_config, true));

        // Add consent updates based on purposes
        $analytics_purposes = get_option('klaro_geo_analytics_purposes', array('analytics'));
        $ad_purposes = get_option('klaro_geo_ad_purposes', array('advertising'));

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

        // If this is google-tag-manager and no callbacks are defined, use the default values
        if ($service_config['name'] === 'google-tag-manager' &&
            (empty($service_config['onInit']) || empty($service_config['onAccept']) || empty($service_config['onDecline']))) {

            // Get default values from the function
            $defaults = get_klaro_default_values();

            klaro_geo_debug_log('Processing GTM settings with default values');

            // Only set these if they're not already set in the service
            if (empty($service_config['onInit'])) {
                $service_config['onInit'] = $defaults['gtm_oninit'];
            }

            if (empty($service_config['onAccept'])) {
                $service_config['onAccept'] = $defaults['gtm_onaccept'];
            }

            if (empty($service_config['onDecline'])) {
                $service_config['onDecline'] = $defaults['gtm_ondecline'];
            }
        }

        // Check if this service has analytics or advertising purposes
        $purposes = isset($service['purposes']) ? $service['purposes'] :
                   (isset($service['service_purposes']) ? $service['service_purposes'] : []);

        // Ensure purposes is an array
        if (!is_array($purposes)) {
            $purposes = array($purposes);
        }

        // Track if this service has analytics or advertising purposes
        if (is_array($purposes) && is_array($analytics_purposes) && count(array_intersect($purposes, $analytics_purposes)) > 0) {
            $has_analytics_services = true;
        }

        if (is_array($purposes) && is_array($ad_purposes) && count(array_intersect($purposes, $ad_purposes)) > 0) {
            $has_ad_services = true;
        }

        $klaro_config['services'][] = $service_config;
    }

    // Add global consent handlers for analytics and advertising
    $global_consent_js = "";

    // Add a global variable to store the current opts
    $global_consent_js .= "\n// Global variable to store the current Klaro opts\n";
    $global_consent_js .= "window.currentKlaroOpts = null;\n";

    // Add global onAccept handler
    $global_consent_js .= "\n// Global consent update handler\n";
    $global_consent_js .= "document.addEventListener('klaro:consent-change', function(e) {\n";
    $global_consent_js .= "    // Get consents and services from the event detail\n";
    $global_consent_js .= "    var consents = e.detail.manager.consents || {};\n";
    $global_consent_js .= "    var services = e.detail.manager.services || [];\n";
    $global_consent_js .= "    var consentUpdates = {};\n";
    $global_consent_js .= "    console.log('Klaro consent change event:', e.detail);\n";

    // Create arrays of purposes for JS
    $analytics_purposes_js = wp_json_encode($analytics_purposes);
    $ad_purposes_js = wp_json_encode($ad_purposes);

    // Add analytics consent handling
    if ($has_analytics_services) {
        $global_consent_js .= "\n    // Check for analytics consent\n";
        $global_consent_js .= "    var analyticsPurposes = {$analytics_purposes_js};\n";
        $global_consent_js .= "    var hasAnalyticsConsent = false;\n";

        // First check direct purpose consent
        $global_consent_js .= "    // Check direct purpose consent\n";
        $global_consent_js .= "    analyticsPurposes.forEach(function(purpose) {\n";
        $global_consent_js .= "        if (consents[purpose] === true) {\n";
        $global_consent_js .= "            console.log('Analytics purpose granted directly:', purpose);\n";
        $global_consent_js .= "            hasAnalyticsConsent = true;\n";
        $global_consent_js .= "        }\n";
        $global_consent_js .= "    });\n";

        // Then check service-based consent
        $global_consent_js .= "\n    // Check service-based consent\n";
        $global_consent_js .= "    if (!hasAnalyticsConsent) {\n";
        $global_consent_js .= "        services.forEach(function(service) {\n";
        $global_consent_js .= "            if (consents[service.name] === true && service.purposes) {\n";
        $global_consent_js .= "                // Check if this service has any analytics purposes\n";
        $global_consent_js .= "                var servicePurposes = Array.isArray(service.purposes) ? service.purposes : [service.purposes];\n";
        $global_consent_js .= "                var hasAnalyticsPurpose = servicePurposes.some(function(purpose) {\n";
        $global_consent_js .= "                    return analyticsPurposes.indexOf(purpose) !== -1;\n";
        $global_consent_js .= "                });\n";
        $global_consent_js .= "                if (hasAnalyticsPurpose) {\n";
        $global_consent_js .= "                    console.log('Analytics consent granted via service:', service.name);\n";
        $global_consent_js .= "                    hasAnalyticsConsent = true;\n";
        $global_consent_js .= "                }\n";
        $global_consent_js .= "            }\n";
        $global_consent_js .= "        });\n";
        $global_consent_js .= "    }\n";

        $global_consent_js .= "    consentUpdates['analytics_storage'] = hasAnalyticsConsent ? 'granted' : 'denied';\n";
    }

    // Add advertising consent handling
    if ($has_ad_services) {
        $global_consent_js .= "\n    // Check for advertising consent\n";
        $global_consent_js .= "    var adPurposes = {$ad_purposes_js};\n";
        $global_consent_js .= "    var hasAdConsent = false;\n";

        // First check direct purpose consent
        $global_consent_js .= "    // Check direct purpose consent\n";
        $global_consent_js .= "    adPurposes.forEach(function(purpose) {\n";
        $global_consent_js .= "        if (consents[purpose] === true) {\n";
        $global_consent_js .= "            console.log('Ad purpose granted directly:', purpose);\n";
        $global_consent_js .= "            hasAdConsent = true;\n";
        $global_consent_js .= "        }\n";
        $global_consent_js .= "    });\n";

        // Then check service-based consent
        $global_consent_js .= "\n    // Check service-based consent\n";
        $global_consent_js .= "    if (!hasAdConsent) {\n";
        $global_consent_js .= "        services.forEach(function(service) {\n";
        $global_consent_js .= "            if (consents[service.name] === true && service.purposes) {\n";
        $global_consent_js .= "                // Check if this service has any ad purposes\n";
        $global_consent_js .= "                var servicePurposes = Array.isArray(service.purposes) ? service.purposes : [service.purposes];\n";
        $global_consent_js .= "                var hasAdPurpose = servicePurposes.some(function(purpose) {\n";
        $global_consent_js .= "                    return adPurposes.indexOf(purpose) !== -1;\n";
        $global_consent_js .= "                });\n";
        $global_consent_js .= "                if (hasAdPurpose) {\n";
        $global_consent_js .= "                    console.log('Ad consent granted via service:', service.name);\n";
        $global_consent_js .= "                    hasAdConsent = true;\n";
        $global_consent_js .= "                }\n";
        $global_consent_js .= "            }\n";
        $global_consent_js .= "        });\n";
        $global_consent_js .= "    }\n";

        $global_consent_js .= "    consentUpdates['ad_storage'] = hasAdConsent ? 'granted' : 'denied';\n";
        $global_consent_js .= "    consentUpdates['ad_user_data'] = hasAdConsent ? 'granted' : 'denied';\n";
        $global_consent_js .= "    consentUpdates['ad_personalization'] = hasAdConsent ? 'granted' : 'denied';\n";
    }

    // Add the actual consent update call
    $global_consent_js .= "\n    // Update consent state in Google Consent Mode\n";
    $global_consent_js .= "    if (Object.keys(consentUpdates).length > 0) {\n";
    $global_consent_js .= "        console.log('Updating Google consent mode with:', consentUpdates);\n";
    $global_consent_js .= "        gtag('consent', 'update', consentUpdates);\n";
    $global_consent_js .= "    }\n";

    // Also push to dataLayer for GTM integration
    $global_consent_js .= "\n    // Push consent data to dataLayer\n";
    $global_consent_js .= "    var acceptedServices = [];\n";
    $global_consent_js .= "    for (var serviceName in consents) {\n";
    $global_consent_js .= "        if (consents[serviceName] === true) {\n";
    $global_consent_js .= "            acceptedServices.push(serviceName);\n";
    $global_consent_js .= "        }\n";
    $global_consent_js .= "    }\n";
    $global_consent_js .= "    window.dataLayer = window.dataLayer || [];\n";
    $global_consent_js .= "    window.dataLayer.push({\n";
    $global_consent_js .= "        'event': 'Klaro Consent',\n";
    $global_consent_js .= "        'acceptedServices': acceptedServices\n";
    $global_consent_js .= "    });\n";
    $global_consent_js .= "});\n";

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

    // Add the global consent handler
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

    $klaro_config_content .= "// Push debug information to dataLayer\n";
    $klaro_config_content .= "window.dataLayer = window.dataLayer || [];\n";
    $klaro_config_content .= "window.dataLayer.push(" . wp_json_encode($dataLayer_push, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ");\n\n";

    // Echo the content for testing purposes
    klaro_geo_debug_log('var klaroConfig = ' . wp_json_encode($klaro_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";");
    klaro_geo_debug_log('dataLayer push: ' . wp_json_encode($dataLayer_push, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Check if consent receipts are enabled
    $enable_consent_receipts = get_option('klaro_geo_enable_consent_receipts', false);
    klaro_geo_debug_log('Consent receipts enabled: ' . ($enable_consent_receipts ? 'yes' : 'no'));

    // Define variables for consent receipts
    $admin_ajax_url = admin_url('admin-ajax.php');
    $consent_nonce = wp_create_nonce('klaro_geo_consent_nonce');

    // Append additional JavaScript code
    $klaro_config_content .= <<<JS
// Initialize gtag function if it doesn't exist
if (typeof gtag !== 'function') {
    function gtag(){dataLayer.push(arguments);}
}

// Function to handle consent updates
function handleConsentUpdate(type, granted) {
    gtag('consent', 'update', {
        [type]: granted ? 'granted' : 'denied'
    });
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

        // Add variables for the consent receipts script
        $klaro_config_content .= <<<JS
// Consent Receipt Configuration
window.klaroConsentData = {
    consentReceiptsEnabled: true,
    templateName: "{$template_to_use}",
    templateSource: "{$template_source}",
    detectedCountry: "{$user_country}",
    detectedRegion: "{$user_region}",
    adminOverride: {$admin_override_value},
    ajaxUrl: "{$admin_ajax_url}",
    nonce: "{$consent_nonce}",
    enableConsentLogging: true,
    templateSettings: {
        consentModalTitle: "{$modal_title}",
        consentModalDescription: "{$modal_description}",
        acceptAllText: "{$accept_all_text}",
        declineAllText: "{$decline_all_text}",
        defaultConsent: {$default_consent},
        requiredConsent: {$required_consent}
    }
};

// Note: We're not adding a klaro:consent-change event listener here
// because it's already handled in klaro-geo-consent-receipts.js
// This prevents duplicate event handling

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