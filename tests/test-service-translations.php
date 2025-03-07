<?php
/**
 * Test Service Translations
 * 
 * This file contains tests for the service translations functionality.
 */

// Exit if accessed directly
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Test Service Translations
 */
class Klaro_Geo_Service_Translations_Test {
    /**
     * Run all tests
     */
    public static function run_tests() {
        self::test_save_service_translations();
        self::test_load_service_translations();
        self::test_service_translations_from_templates();
    }

    /**
     * Test saving service translations
     */
    private static function test_save_service_translations() {
        // Get current services
        $services_json = get_option('klaro_geo_services', '[]');
        $services = json_decode($services_json, true);
        
        if (!is_array($services) || empty($services)) {
            // Create a test service if none exists
            $services = array(
                array(
                    'name' => 'test-service',
                    'required' => false,
                    'purposes' => array('analytics'),
                    'default' => false,
                    'cookies' => array(),
                    'translations' => array(
                        'zz' => array(
                            'title' => 'Test Service ZZ',
                            'description' => 'Test Service Description ZZ'
                        )
                    )
                )
            );
        } else {
            // Update the first service with test translations
            $services[0]['translations'] = array(
                'zz' => array(
                    'title' => 'Test Service ZZ',
                    'description' => 'Test Service Description ZZ'
                )
            );
        }
        
        // Add test translations for English and French
        $services[0]['translations']['en'] = array(
            'title' => 'Test Service EN',
            'description' => 'Test Service Description EN'
        );
        
        $services[0]['translations']['fr'] = array(
            'title' => 'Test Service FR',
            'description' => 'Test Service Description FR'
        );
        
        // Save services
        update_option('klaro_geo_services', wp_json_encode($services));
        
        // Verify save
        $updated_services_json = get_option('klaro_geo_services', '[]');
        $updated_services = json_decode($updated_services_json, true);
        
        if (!is_array($updated_services) || empty($updated_services)) {
            self::log_error('Failed to save services');
            return;
        }
        
        // Check if translations were saved
        if (!isset($updated_services[0]['translations'])) {
            self::log_error('Service translations not saved');
            return;
        }
        
        $saved_translations = $updated_services[0]['translations'];
        
        // Check if all languages were saved
        foreach (array('zz', 'en', 'fr') as $lang) {
            if (!isset($saved_translations[$lang])) {
                self::log_error("Language $lang not saved in service translations");
                return;
            }
        }
        
        // Check specific values
        if ($saved_translations['fr']['title'] !== 'Test Service FR') {
            self::log_error('French service title not saved correctly');
            return;
        }
        
        self::log_success('Service translations saved successfully');
    }

    /**
     * Test loading service translations
     */
    private static function test_load_service_translations() {
        // Get services
        $services_json = get_option('klaro_geo_services', '[]');
        $services = json_decode($services_json, true);
        
        if (!is_array($services) || empty($services)) {
            self::log_error('No services found');
            return;
        }
        
        // Check if translations exist
        if (!isset($services[0]['translations'])) {
            self::log_error('Service translations not found');
            return;
        }
        
        $translations = $services[0]['translations'];
        
        // Check if all expected languages exist
        foreach (array('zz', 'en', 'fr') as $lang) {
            if (!isset($translations[$lang])) {
                self::log_error("Language $lang not found in service translations");
                return;
            }
        }
        
        // Check specific values
        if ($translations['fr']['title'] !== 'Test Service FR') {
            self::log_error('French service title not loaded correctly');
            return;
        }
        
        self::log_success('Service translations loaded successfully');
    }

    /**
     * Test service translations from templates
     */
    private static function test_service_translations_from_templates() {
        // Get templates
        $templates = get_option('klaro_geo_templates', array());
        
        // Make sure we have a default template
        if (!isset($templates['default'])) {
            self::log_error('Default template not found');
            return;
        }
        
        // Make sure template has translations
        if (!isset($templates['default']['config']['translations'])) {
            self::log_error('Template translations not found');
            return;
        }
        
        $template_translations = $templates['default']['config']['translations'];
        
        // Add a new language to the template if it doesn't exist
        if (!isset($template_translations['es'])) {
            $template_translations['es'] = array(
                'consentModal' => array(
                    'title' => 'Test Title ES',
                    'description' => 'Test Description ES'
                ),
                'acceptAll' => 'Accept All ES',
                'declineAll' => 'Decline All ES'
            );
            
            // Save updated translations
            $templates['default']['config']['translations'] = $template_translations;
            update_option('klaro_geo_templates', $templates);
        }
        
        // Get services
        $services_json = get_option('klaro_geo_services', '[]');
        $services = json_decode($services_json, true);
        
        if (!is_array($services) || empty($services)) {
            self::log_error('No services found');
            return;
        }
        
        // Add Spanish translation to the service
        $services[0]['translations']['es'] = array(
            'title' => 'Test Service ES',
            'description' => 'Test Service Description ES'
        );
        
        // Save services
        update_option('klaro_geo_services', wp_json_encode($services));
        
        // Simulate loading services in the admin page
        $services = function_exists('klaro_geo_validate_services') ? klaro_geo_validate_services() : json_decode(get_option('klaro_geo_services', '[]'), true);
        
        // Check if Spanish translation exists in the service
        if (!isset($services[0]['translations']['es'])) {
            self::log_error('Spanish translation not found in service after adding to template');
            return;
        }
        
        // Check specific values
        if ($services[0]['translations']['es']['title'] !== 'Test Service ES') {
            self::log_error('Spanish service title not loaded correctly');
            return;
        }
        
        self::log_success('Service translations from templates loaded successfully');
    }

    /**
     * Log an error message
     */
    private static function log_error($message) {
        error_log('[Klaro Geo Test Error] ' . $message);
        echo '<div class="notice notice-error"><p><strong>Klaro Geo Test Error:</strong> ' . esc_html($message) . '</p></div>';
    }

    /**
     * Log a success message
     */
    private static function log_success($message) {
        error_log('[Klaro Geo Test Success] ' . $message);
        echo '<div class="notice notice-success"><p><strong>Klaro Geo Test Success:</strong> ' . esc_html($message) . '</p></div>';
    }
}

// Run tests if this is a test request
if (isset($_GET['klaro_geo_run_tests']) && current_user_can('manage_options')) {
    add_action('admin_notices', function() {
        Klaro_Geo_Service_Translations_Test::run_tests();
    });
}