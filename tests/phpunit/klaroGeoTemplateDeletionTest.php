<?php
/**
 * Test Template Deletion
 * 
 * This file contains tests for the template deletion functionality.
 */

// Exit if accessed directly
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Test Template Deletion
 */
class Klaro_Geo_Template_Deletion_Test {
    /**
     * Run all tests
     */
    public static function run_tests() {
        self::test_delete_template();
        self::test_delete_template_in_use();
        self::test_delete_default_template();
    }

    /**
     * Test deleting a template
     */
    private static function test_delete_template() {
        // Get current templates
        $templates = get_option('klaro_geo_templates', array());
        
        // Make sure we have a default template
        if (!isset($templates['default'])) {
            self::log_error('Default template not found');
            return;
        }
        
        // Create a test template
        $test_template = array(
            'name' => 'Test Template for Deletion',
            'config' => array(
                'translations' => array(
                    'zz' => array(
                        'acceptAll' => 'Accept All Test',
                        'declineAll' => 'Decline All Test'
                    )
                )
            )
        );
        
        // Add the test template
        $templates['test_deletion'] = $test_template;
        update_option('klaro_geo_templates', $templates);
        
        // Initialize the template settings class
        $template_settings = new Klaro_Geo_Template_Settings();
        
        // Check if the template was added
        $templates = $template_settings->get();
        if (!isset($templates['test_deletion'])) {
            self::log_error('Test template was not added');
            return;
        }
        
        // Delete the template
        $template_settings->remove_template('test_deletion');
        $template_settings->save();
        
        // Check if the template was deleted
        $templates = $template_settings->get();
        if (isset($templates['test_deletion'])) {
            self::log_error('Test template was not deleted');
            return;
        }
        
        self::log_success('Template deletion test passed');
    }

    /**
     * Test deleting a template that is in use
     */
    private static function test_delete_template_in_use() {
        // Get current templates
        $templates = get_option('klaro_geo_templates', array());
        
        // Create a test template
        $test_template = array(
            'name' => 'Test Template In Use',
            'config' => array(
                'translations' => array(
                    'zz' => array(
                        'acceptAll' => 'Accept All Test',
                        'declineAll' => 'Decline All Test'
                    )
                )
            )
        );
        
        // Add the test template
        $templates['test_in_use'] = $test_template;
        update_option('klaro_geo_templates', $templates);
        
        // Get current country settings
        $country_settings = get_option('klaro_geo_country_settings', array());
        
        // Assign the template to a test country
        $country_settings['TEST'] = array(
            'template' => 'test_in_use',
            'services' => array()
        );
        update_option('klaro_geo_country_settings', $country_settings);
        
        // Check if the template is in use
        $template_in_use = false;
        $countries_using_template = array();
        
        foreach ($country_settings as $country_code => $country_data) {
            if (isset($country_data['template']) && $country_data['template'] === 'test_in_use') {
                $template_in_use = true;
                $countries_using_template[] = $country_code;
            }
        }
        
        if (!$template_in_use) {
            self::log_error('Template should be in use but is not');
            return;
        }
        
        // Initialize the template settings class
        $template_settings = new Klaro_Geo_Template_Settings();
        
        // Try to delete the template (this should fail)
        $template_settings->remove_template('test_in_use');
        $template_settings->save();
        
        // Check if the template was deleted (it should not be)
        $templates = $template_settings->get();
        if (!isset($templates['test_in_use'])) {
            self::log_error('Template in use was deleted when it should not have been');
            return;
        }
        
        // Clean up - remove the template from the country
        unset($country_settings['TEST']);
        update_option('klaro_geo_country_settings', $country_settings);
        
        // Now delete the template (this should succeed)
        $template_settings->remove_template('test_in_use');
        $template_settings->save();
        
        // Check if the template was deleted
        $templates = $template_settings->get();
        if (isset($templates['test_in_use'])) {
            self::log_error('Test template was not deleted after removing from country');
            return;
        }
        
        self::log_success('Template in-use deletion test passed');
    }

    /**
     * Test deleting the default template
     */
    private static function test_delete_default_template() {
        // Initialize the template settings class
        $template_settings = new Klaro_Geo_Template_Settings();
        
        // Try to delete the default template
        $template_settings->remove_template('default');
        $template_settings->save();
        
        // Check if the default template still exists (it should)
        $templates = $template_settings->get();
        if (!isset($templates['default'])) {
            self::log_error('Default template was deleted when it should not have been');
            return;
        }
        
        self::log_success('Default template protection test passed');
    }

    /**
     * Log an error message
     */
    private static function log_error($message) {
        echo '<div class="notice notice-error"><p><strong>Template Deletion Test Error:</strong> ' . esc_html($message) . '</p></div>';
    }

    /**
     * Log a success message
     */
    private static function log_success($message) {
        echo '<div class="notice notice-success"><p><strong>Template Deletion Test Success:</strong> ' . esc_html($message) . '</p></div>';
    }
}

// Run tests if this is a test request
if (isset($_GET['klaro_geo_run_tests']) && current_user_can('manage_options')) {
    add_action('admin_notices', function() {
        Klaro_Geo_Template_Deletion_Test::run_tests();
    });
}