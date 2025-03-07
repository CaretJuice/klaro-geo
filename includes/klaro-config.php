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
    $settings = json_decode(get_option('klaro_geo_settings'), true);
    if (empty($settings)) {
        $settings = klaro_geo_get_default_geo_settings();
    }

    // Get user location
    $location = klaro_geo_get_user_location();
    $user_country = $location['country'];
    $user_region = $location['region'];

    klaro_geo_debug_log('User location - Country: ' . $user_country . ', Region: ' . $user_region);

    // Get effective settings for the location
    $effective_settings = klaro_geo_get_effective_settings($user_country . ($user_region ? '-' . $user_region : ''));

    // Determine template source
    $template_source = 'fallback';
    if (isset($effective_settings['source'])) {
        $template_source = $effective_settings['source'];
    } elseif (!empty($user_country) && !empty($user_region)) {
        $template_source = 'geo-match region';
    } elseif (!empty($user_country)) {
        $template_source = 'geo-match country';
    }

    klaro_geo_debug_log('Template source: ' . $template_source);

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
    } else {
        // Ensure default values are set if template config is missing
        $klaro_config['default'] = false;
        $klaro_config['required'] = false;
    }
    klaro_geo_debug_log('Template config applied: ' . print_r($klaro_config, true));

    // Build the services configuration
    $klaro_config['services'] = array();
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
            $defaults = function_exists('get_klaro_default_values') ? get_klaro_default_values() : array(
                'gtm_oninit' => 'window.dataLayer = window.dataLayer || []; window.gtag = function() { dataLayer.push(arguments); }; gtag(\'consent\', \'default\', {\'ad_storage\': \'denied\', \'analytics_storage\': \'denied\', \'ad_user_data\': \'denied\', \'ad_personalization\': \'denied\'}); gtag(\'set\', \'ads_data_redaction\', true);',
                'gtm_onaccept' => 'if (opts.consents.analytics || opts.consents.advertising) { for(let k of Object.keys(opts.consents)){ if (opts.consents[k]){ let eventName = \'klaro-\'+k+\'-accepted\'; dataLayer.push({\'event\': eventName}); } } }',
                'gtm_ondecline' => ''
            );

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

        // For services without explicit callbacks, generate them based on purposes
        if (empty($service_config['onAccept']) || empty($service_config['onDecline'])) {
            // Add consent updates based on purposes
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

            $purposes = isset($service['purposes']) ? $service['purposes'] :
                       (isset($service['service_purposes']) ? $service['service_purposes'] : []);

            // Ensure purposes is an array
            if (!is_array($purposes)) {
                $purposes = array($purposes);
            }

            // Generate analytics consent updates
            if (is_array($purposes) && is_array($analytics_purposes) && count(array_intersect($purposes, $analytics_purposes)) > 0) {
                if (empty($service_config['onAccept'])) {
                    $service_config['onAccept'] = "gtag('consent', 'update', {'analytics_storage': 'granted'});";
                }

                if (empty($service_config['onDecline'])) {
                    $service_config['onDecline'] = "gtag('consent', 'update', {'analytics_storage': 'denied'});";
                }
            }

            // Generate ad consent updates
            if (is_array($purposes) && is_array($ad_purposes) && count(array_intersect($purposes, $ad_purposes)) > 0) {
                if (empty($service_config['onAccept'])) {
                    $service_config['onAccept'] = ($service_config['onAccept'] ?? '') .
                        "gtag('consent', 'update', {'ad_storage': 'granted', 'ad_user_data': 'granted', 'ad_personalization': 'granted'});";
                }

                if (empty($service_config['onDecline'])) {
                    $service_config['onDecline'] = ($service_config['onDecline'] ?? '') .
                        "gtag('consent', 'update', {'ad_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'});";
                }
            }
        }

        $klaro_config['services'][] = $service_config;
    }
            
    // Generate the JavaScript content
    $klaro_config_content = "// Detected/Debug Country Code: " . esc_js($user_country) . "\n\n";

    // Format the klaroConfig variable in a way that's easy to parse for tests
    $klaro_config_content .= 'var klaroConfig = ' . wp_json_encode($klaro_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";

    // Add a clear separator comment to help with parsing
    $klaro_config_content .= "// ===== END OF KLARO CONFIG =====\n\n";

    // Add dataLayer push for debugging
    $dataLayer_push = array(
        'event' => 'klaro_geo_klaro_config_loaded',
        'klaro_geo_consent_template' => $template_to_use,
        'klaro_geo_template_source' => $template_source,
        'klaro_geo_detected_country' => !empty($user_country) ? $user_country : null,
        'klaro_geo_detected_region' => !empty($user_region) ? $user_region : null
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
// Initialize gtag function
function gtag(){dataLayer.push(arguments);}

// Set default consent state
gtag('consent', 'default', {
    'ad_storage': 'denied',
    'analytics_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied'
});

// Enable data redaction by default
gtag('set', 'ads_data_redaction', true);

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

        // Add variables for the consent receipts script
        $klaro_config_content .= <<<JS
// Consent Receipt Configuration
window.klaroConsentReceiptsEnabled = true;
window.klaroConsentTemplateName = "{$template_to_use}";
window.klaroConsentTemplateSource = "{$template_source}";
window.klaroDetectedCountry = "{$user_country}";
window.klaroDetectedRegion = "{$user_region}";
window.klaroAjaxUrl = "{$admin_ajax_url}";
window.klaroNonce = "{$consent_nonce}";

// Template settings for consent receipt
window.klaroTemplateSettings = {
    consentModalTitle: "{$modal_title}",
    consentModalDescription: "{$modal_description}",
    acceptAllText: "{$accept_all_text}",
    declineAllText: "{$decline_all_text}",
    defaultConsent: {$default_consent},
    requiredConsent: {$required_consent}
};

// Basic dataLayer push for consent changes
document.addEventListener('klaro:consent-change', function(e) {
    // Create a simple consent receipt for dataLayer
    var consentReceipt = {
        'event': 'klaro_geo_consent_receipt',
        'klaro_geo_consent_timestamp': new Date().toISOString(),
        'klaro_geo_consent_choices': {}
    };

    // Add each service consent choice to the receipt
    for (var serviceName in e.detail.manager.consents) {
        consentReceipt.klaro_geo_consent_choices[serviceName] = e.detail.manager.consents[serviceName];
    }

    // Push the consent receipt to the dataLayer
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(consentReceipt);
});

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

?>