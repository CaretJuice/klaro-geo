<?php

// Function to generate klaro-config.js
function klaro_geo_generate_config_file() {
    // Get the services using the service settings class
    $service_settings = Klaro_Geo_Service_Settings::get_instance();
    $services = $service_settings->get();

    if (empty($services) || !is_array($services) || !isset($services[0]['name'])) {
        klaro_geo_debug_log('Invalid or empty services, using default');
        $services = $service_settings->get_default_services();
        // Update the services using the service settings class
        $service_settings->set($services);
        $service_settings->save();
    }

    klaro_geo_debug_log('Services: ' . count($services) . ' services configured');

    // Initialize config with template settings
    $klaro_config = array();

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
    $template_settings = Klaro_Geo_Template_Settings::get_instance();
    $templates = $template_settings->get();
    klaro_geo_debug_log('Templates available: ' . implode(', ', array_keys($templates)));

    // Get the template config from the database, or fall back to default if not found
    if (!function_exists('klaro_geo_get_default_templates')) {
        // If the function doesn't exist, include the defaults file that defines it
        require_once dirname(__FILE__) . '/klaro-geo-defaults.php';
    }

    // Try to get the template, tracking which fallback was used
    // IMPORTANT: Update $template_to_use and $template_source when falling back
    // so that metadata (klaroGeoConsentTemplate, klaroGeoTemplateSource) reflects actual template used
    $template_source_detail = '';
    $original_template_requested = $template_to_use;
    if (isset($templates[$template_to_use])) {
        $template_config = $templates[$template_to_use];
        $template_source_detail = 'exact match from database';
    } elseif (isset($templates['default'])) {
        $template_config = $templates['default'];
        $template_source_detail = 'fallback to "default" template (requested "' . $template_to_use . '" not found)';
        klaro_geo_debug_log('WARNING: Requested template "' . $template_to_use . '" not found in database, falling back to "default"');
        // Update variables so metadata reflects actual template used
        $template_to_use = 'default';
        $template_source = 'fallback';
    } else {
        $template_config = klaro_geo_get_default_templates()['default'];
        $template_source_detail = 'fallback to hardcoded defaults (neither "' . $original_template_requested . '" nor "default" found in database)';
        klaro_geo_debug_log('WARNING: Neither requested template "' . $original_template_requested . '" nor "default" found in database, using hardcoded defaults');
        // Update variables so metadata reflects actual template used
        $template_to_use = 'default';
        $template_source = 'hardcoded-fallback';
    }

    klaro_geo_debug_log('Template lookup: ' . $template_source_detail);

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
        }
    }

    // Apply template configuration
    if (isset($template_config['config'])) {
        // Copy all config values
        foreach ($template_config['config'] as $key => $value) {
            // Skip translations for now, we'll handle them separately
            if ($key === 'translations' || $key === 'translations_json') {
                continue;
            }

            // Clean up consent_mode_settings - remove legacy initialization_code
            // The initialization code is now generated dynamically with custom service consent keys
            if ($key === 'consent_mode_settings' && is_array($value)) {
                unset($value['initialization_code']);
                unset($value['initialize_consent_mode']); // Also remove legacy toggle
            }

            // Copy the value directly to klaroConfig
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

        // Log applied template settings (compact)
        klaro_geo_debug_log('Config applied: default=' . (($klaro_config['default'] ?? false) ? 'true' : 'false') .
            ', mustConsent=' . (($klaro_config['mustConsent'] ?? false) ? 'true' : 'false'));
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

    // Set default cookiePath if not configured
    if (!isset($klaro_config['cookiePath']) || empty($klaro_config['cookiePath'])) {
        $klaro_config['cookiePath'] = '/';
    }

    // Auto-detect cookieDomain with leading dot for subdomain sharing if not explicitly set
    if (!isset($klaro_config['cookieDomain']) || $klaro_config['cookieDomain'] === '') {
        // Get the site's domain from WordPress
        $raw_site_url = get_site_url();
        $site_url = parse_url($raw_site_url, PHP_URL_HOST);

        if ($site_url) {
            // Add leading dot for subdomain sharing (e.g., ".example.com")
            $klaro_config['cookieDomain'] = '.' . preg_replace('/^www\./', '', $site_url);
            klaro_geo_debug_log('cookieDomain auto-detected: ' . $klaro_config['cookieDomain']);
        } else {
            // Fallback: leave cookieDomain empty but log a warning
            klaro_geo_debug_log('WARNING: Could not auto-detect cookieDomain');
            $klaro_config['cookieDomain'] = '';
        }
    } else {
        klaro_geo_debug_log('cookieDomain from template: ' . $klaro_config['cookieDomain']);
    }

    // Build the services configuration
    $klaro_config['services'] = array();

    // NOTE: Consent mode is ALWAYS enabled
    // Consent mode services are now first-class Klaro services with is_consent_mode_service=true
    // The old template-level analytics_storage_service/ad_storage_service mappings are removed

    // Build map of consent mode services for quick lookup
    $consent_mode_services = array();
    $parent_child_map = array(); // Maps parent service name to array of child service names
    foreach ($services as $service) {
        if (isset($service['is_consent_mode_service']) && $service['is_consent_mode_service'] === true) {
            $consent_mode_key = $service['consent_mode_key'] ?? '';
            if (!empty($consent_mode_key)) {
                $consent_mode_services[$consent_mode_key] = $service['name'];
            }
            // Track parent-child relationships
            if (isset($service['parent_service']) && !empty($service['parent_service'])) {
                $parent = $service['parent_service'];
                if (!isset($parent_child_map[$parent])) {
                    $parent_child_map[$parent] = array();
                }
                $parent_child_map[$parent][] = $service['name'];
            }
        }
    }

    klaro_geo_debug_log('Consent mode services detected: ' . implode(', ', array_values($consent_mode_services)));

    // Get template's default consent state (for inheritance)
    $template_default_consent = isset($template_config['config']['default']) && $template_config['config']['default'] ? true : false;

    // Get gtag settings from template if available (ads_data_redaction, url_passthrough)
    $consent_mode_settings = array();
    if (isset($template_config['consent_mode_settings'])) {
        $consent_mode_settings = $template_config['consent_mode_settings'];
    } else if (isset($template_config['config']) && isset($template_config['config']['consent_mode_settings'])) {
        $consent_mode_settings = $template_config['config']['consent_mode_settings'];
    }
    $gtag_settings = isset($consent_mode_settings['gtag_settings']) ? $consent_mode_settings['gtag_settings'] : array();
    $ads_data_redaction = isset($gtag_settings['ads_data_redaction']) ? $gtag_settings['ads_data_redaction'] : 'true';
    $url_passthrough = isset($gtag_settings['url_passthrough']) ? $gtag_settings['url_passthrough'] : 'false';

    // Build consent defaults - all start as 'denied' for Basic Consent Mode
    // Standard Google Consent Mode v2 keys default to 'denied'
    $dynamic_consent_defaults = array(
        'ad_storage' => 'denied',
        'analytics_storage' => 'denied',
        'ad_user_data' => 'denied',
        'ad_personalization' => 'denied',
    );

    // Add dynamic service consent keys for ALL services
    // Respects: required services → granted, service override → explicit default, otherwise → template default
    foreach ($services as $service) {
        $service_name = $service['name'] ?? '';
        if (!empty($service_name)) {
            // Convert service name to consent key format (e.g., 'google-analytics' -> 'google_analytics_consent')
            $consent_key = str_replace('-', '_', $service_name) . '_consent';

            // Determine the default value based on priority:
            // 1. Required services → always 'granted'
            // 2. Service has explicit default → use it
            // 3. Otherwise → inherit from template default
            $is_required = isset($service['required']) && filter_var($service['required'], FILTER_VALIDATE_BOOLEAN);
            $has_explicit_default = isset($service['default']) && $service['default'] !== null && $service['default'] !== '';

            if ($is_required) {
                $dynamic_consent_defaults[$consent_key] = 'granted';
            } elseif ($has_explicit_default) {
                $service_default = filter_var($service['default'], FILTER_VALIDATE_BOOLEAN);
                $dynamic_consent_defaults[$consent_key] = $service_default ? 'granted' : 'denied';
            } else {
                // Inherit from template default
                $dynamic_consent_defaults[$consent_key] = $template_default_consent ? 'granted' : 'denied';
            }

            // For consent mode services, also set the standard Google Consent Mode key
            if (isset($service['is_consent_mode_service']) && $service['is_consent_mode_service'] === true) {
                $consent_mode_key = $service['consent_mode_key'] ?? '';
                if (!empty($consent_mode_key) && isset($dynamic_consent_defaults[$consent_mode_key])) {
                    // Set the standard key to match the service's default
                    $dynamic_consent_defaults[$consent_mode_key] = $dynamic_consent_defaults[$consent_key];
                }
            }
        }
    }

    klaro_geo_debug_log('Generated consolidated consent defaults for ' . count($dynamic_consent_defaults) . ' keys');

    // Process each service
    foreach ($services as $service) {
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
        }

        if (isset($service['default']) && $service['default'] !== null) {
            $service_config['default'] = filter_var($service['default'], FILTER_VALIDATE_BOOLEAN);
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

        // Add consent mode metadata for JavaScript to use
        if (isset($service['is_consent_mode_service']) && $service['is_consent_mode_service'] === true) {
            $service_config['is_consent_mode_service'] = true;
            if (isset($service['consent_mode_key'])) {
                $service_config['consent_mode_key'] = $service['consent_mode_key'];
            }
            if (isset($service['parent_service'])) {
                $service_config['parent_service'] = $service['parent_service'];
            }
        }

        // Skip hidden services
        if (isset($service['hidden']) && $service['hidden'] === true) {
            klaro_geo_debug_log('Skipping hidden service: ' . ($service['name'] ?? 'unknown'));
            continue;
        }

        $klaro_config['services'][] = $service_config;
    }

    // Store parent-child map for JavaScript to use for dependency enforcement
    $klaro_config['consent_mode_dependencies'] = $parent_child_map;

    // Transform styling settings from object to array format if needed
    if (isset($klaro_config['styling']) && isset($klaro_config['styling']['theme'])) {
        if (is_array($klaro_config['styling']['theme'])) {
            // Check if theme is in object format (with keys like 'color', 'position', 'width')
            $is_associative = false;
            foreach (array_keys($klaro_config['styling']['theme']) as $key) {
                if (is_string($key)) {
                    $is_associative = true;
                    break;
                }
            }

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
            }
        }
    }

    // Generate the JavaScript content
    $klaro_config_content = "// Detected/Debug Country Code: " . esc_js($user_country) . "\n\n";

    // Format the klaroConfig variable in a way that's easy to parse for tests
    $klaro_config_content .= 'var klaroConfig = ' . wp_json_encode($klaro_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n\n";

    // Add a clear separator comment to help with parsing
    $klaro_config_content .= "// ===== END OF KLARO CONFIG =====\n\n";

    // Add dataLayer push for debugging with JavaScript to include latest consent receipt
    $klaro_config_content .= "// Push debug information to dataLayer with latest consent receipt\n";
    $klaro_config_content .= "window.dataLayer = window.dataLayer || [];\n";
    $klaro_config_content .= "
// Get the latest consent receipt if available
var latestReceipt = null;
try {
    if (typeof getLatestConsentReceipt === 'function') {
        latestReceipt = getLatestConsentReceipt();
    } else {
        // Fallback if the function isn't loaded yet
        var existingData = window.localStorage.getItem('klaro_consent_receipts');
        if (existingData) {
            var receipts = JSON.parse(existingData);
            if (Array.isArray(receipts) && receipts.length > 0) {
                latestReceipt = receipts[receipts.length - 1];
            }
        }
    }
} catch (e) {
    console.error('Error retrieving latest consent receipt:', e);
}

// Create the dataLayer push object
var klaroConfigLoadedData = {
    'event': 'Klaro Event',
    'eventSource': 'klaro-geo',
    'klaroEventName': 'klaroConfigLoaded',
    'klaroGeoConsentTemplate': " . wp_json_encode($template_to_use) . ",
    'klaroGeoTemplateSource': " . wp_json_encode($template_source) . ",
    'klaroGeoDetectedCountry': " . (!empty($user_country) ? wp_json_encode($user_country) : 'null') . ",
    'klaroGeoDetectedRegion': " . (!empty($user_region) ? wp_json_encode($user_region) : 'null') . ",
    'klaroGeoAdminOverride': " . ($using_debug_geo ? 'true' : 'false') . ",
    'klaroGeoEnableConsentLogging': " . ($custom_template_settings['enableConsentLogging'] ? 'true' : 'false') . "
};

// Add the consent receipt if available
if (latestReceipt) {
    klaroConfigLoadedData.klaroGeoConsentReceipt = latestReceipt;
    console.log('Adding latest consent receipt to klaroConfigLoaded event:', latestReceipt.receipt_id);
}

// Push to dataLayer
window.dataLayer.push(klaroConfigLoadedData);

// ===== DATALAYER INITIALIZATION =====
// Initialize dataLayer and gtag function for GTM
// NOTE: Consent mode is handled by the Klaro Geo GTM template, not via gtag commands
// The gtag() approach has timing issues - GTM template uses native consent APIs
window.dataLayer = window.dataLayer || [];
window.gtag = function(){dataLayer.push(arguments);};
\n";

    // Log summary of config generation
    klaro_geo_debug_log('Config generated: template=' . $template_to_use . ', services=' . count($klaro_config['services']) .
        ', consentLogging=' . ($custom_template_settings['enableConsentLogging'] ? 'true' : 'false'));

    // Define variables for consent receipts
    $admin_ajax_url = admin_url('admin-ajax.php');
    $consent_nonce = wp_create_nonce('klaro_geo_consent_nonce');

    // Get the consent_mode value for JavaScript (always v2 - consent mode is always enabled)
    $consent_mode = 'v2';
    $consent_mode_js = wp_json_encode($consent_mode);

    // Add consent receipt functionality if enabled
    if ($enable_consent_receipts) {
        // Prepare template settings values
        $modal_title = isset($template_config['config']['translations']['zz']['consentModal']['title']) ?
            $template_config['config']['translations']['zz']['consentModal']['title'] : 'Privacy Settings';

        $modal_description = isset($template_config['config']['translations']['zz']['consentModal']['description']) ?
            $template_config['config']['translations']['zz']['consentModal']['description'] : '';

        $accept_all_text = isset($template_config['config']['translations']['zz']['acceptAll']) ?
            $template_config['config']['translations']['zz']['acceptAll'] : 'Accept All';

        $decline_all_text = isset($template_config['config']['translations']['zz']['decline']) ?
            $template_config['config']['translations']['zz']['decline'] : 'Decline All';

        $default_consent = isset($template_config['config']['default']) && $template_config['config']['default'] ? 'true' : 'false';
        $required_consent = isset($template_config['config']['required']) && $template_config['config']['required'] ? 'true' : 'false';
        $admin_override_value = $using_debug_geo ? 'true' : 'false';

        // Prepare custom template settings for JavaScript
        $enable_consent_logging_value = $custom_template_settings['enableConsentLogging'] ? 'true' : 'false';

        // NOTE: initialize_consent_mode has been removed - consent mode is always enabled

        // Get dataLayer settings
        $suppress_consents_events = get_option('klaro_geo_suppress_consents_events', true) ? 'true' : 'false';

        // Pre-encode consent_defaults for JavaScript output
        $consent_defaults_json = wp_json_encode($dynamic_consent_defaults);

        // Get GTM ID for consent queue mode detection
        $gtm_id = get_option('klaro_geo_gtm_id', '');

        // Pre-encode consent mode services map for JavaScript
        $consent_mode_services_json = wp_json_encode($consent_mode_services);
        $parent_child_map_json = wp_json_encode($parent_child_map);

        // Add variables for the consent receipts script
        $klaro_config_content .= <<<JS
// Consent Receipt Configuration
// NOTE: Consent mode is ALWAYS enabled - consent mode services are now first-class Klaro services
window.klaroConsentData = {
    gtmId: "{$gtm_id}",
    templateName: "{$template_to_use}",
    templateSource: "{$template_source}",
    detectedCountry: "{$user_country}",
    detectedRegion: "{$user_region}",
    adminOverride: {$admin_override_value},
    ajaxUrl: "{$admin_ajax_url}",
    nonce: "{$consent_nonce}",
    enableConsentLogging: {$enable_consent_logging_value},
    consentMode: {$consent_mode_js},
    suppressConsentsEvents: {$suppress_consents_events},
    consentModeServices: {$consent_mode_services_json},
    parentChildMap: {$parent_child_map_json},
    templateSettings: {
        consentModalTitle: "{$modal_title}",
        consentModalDescription: "{$modal_description}",
        acceptAllText: "{$accept_all_text}",
        declineAllText: "{$decline_all_text}",
        defaultConsent: {$default_consent},
        requiredConsent: {$required_consent},
        config: {
            consent_mode_settings: {
                consent_defaults: {$consent_defaults_json},
                gtag_settings: {
                    ads_data_redaction: {$ads_data_redaction},
                    url_passthrough: {$url_passthrough}
                }
            }
        }
    }
};
JS;
    }

    // Write the content to klaro-config.js in the plugin root directory
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $klaro_config_file = $plugin_dir . 'klaro-config.js';

    $result = file_put_contents($klaro_config_file, $klaro_config_content);
    if ($result === false) {
        klaro_geo_debug_log('Failed to write config file');
    }
    return $result;
}
