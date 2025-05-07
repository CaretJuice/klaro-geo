<?php
/**
 * Tests for Klaro Geo Consent Mode functionality
 *
 * @package Klaro_Geo
 */

class KlaroGeoConsentModeTest extends WP_UnitTestCase {
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Reset options
        delete_option('klaro_geo_templates');
        delete_option('klaro_geo_services');
        
        // Set up default templates with consent mode settings
        $templates = array(
            'default' => array(
                'name' => 'Default Template',
                'config' => array(
                    'default' => false,
                    'required' => false,
                ),
                'plugin_settings' => array(
                    'enable_consent_logging' => true,
                ),
                'consent_mode_settings' => array(
                    'initialize_consent_mode' => false,
                    'ad_storage_service' => 'no_service',
                    'ad_user_data' => false,
                    'ad_personalization' => false,
                ),
            ),
            'consent_mode_enabled' => array(
                'name' => 'Consent Mode Enabled',
                'config' => array(
                    'default' => false,
                    'required' => false,
                ),
                'plugin_settings' => array(
                    'enable_consent_logging' => true,
                ),
                'consent_mode_settings' => array(
                    'initialize_consent_mode' => true,
                    'ad_storage_service' => 'google-ads',
                    'ad_user_data' => true,
                    'ad_personalization' => true,
                ),
            ),
        );
        
        // Save templates
        update_option('klaro_geo_templates', $templates);
        
        // Set up services including Google Ads
        $services = array(
            array(
                'name' => 'google-ads',
                'title' => 'Google Ads',
                'purposes' => array('advertising'),
                'default' => false,
                'required' => false,
                'cookies' => array(),
            ),
        );
        
        // Save services
        update_option('klaro_geo_services', wp_json_encode($services));
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Clean up
        delete_option('klaro_geo_templates');
        delete_option('klaro_geo_services');
        
        parent::tearDown();
    }
    
    /**
     * Test that consent mode settings are included in the template data
     */
    public function test_consent_mode_settings_in_template_data() {
        // Get template data
        $template_data = klaro_geo_get_template_data('consent_mode_enabled');
        
        // Check that consent mode settings are included
        $this->assertArrayHasKey('consent_mode_settings', $template_data);
        $this->assertArrayHasKey('initialize_consent_mode', $template_data['consent_mode_settings']);
        $this->assertArrayHasKey('ad_storage_service', $template_data['consent_mode_settings']);
        $this->assertArrayHasKey('ad_user_data', $template_data['consent_mode_settings']);
        $this->assertArrayHasKey('ad_personalization', $template_data['consent_mode_settings']);
        
        // Check specific values
        $this->assertTrue($template_data['consent_mode_settings']['initialize_consent_mode']);
        $this->assertEquals('google-ads', $template_data['consent_mode_settings']['ad_storage_service']);
        $this->assertTrue($template_data['consent_mode_settings']['ad_user_data']);
        $this->assertTrue($template_data['consent_mode_settings']['ad_personalization']);
    }
    
    /**
     * Test that consent mode functionality exists
     */
    public function test_consent_mode_script_enqueued() {
        // Check if the compatibility file exists (for backward compatibility)
        $compat_script_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'js/klaro-geo-consent-mode.js';
        $this->assertFileExists($compat_script_path, 'Consent mode compatibility script file should exist');

        // Check if the main file exists (where the functionality has been moved)
        $main_script_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'js/klaro-geo.js';
        $this->assertFileExists($main_script_path, 'Main klaro-geo.js file should exist');

        // Check if the main file contains the consent mode functionality
        $main_script_content = file_get_contents($main_script_path);
        $this->assertStringContainsString('updateGoogleConsentMode', $main_script_content,
            'Main script should contain the consent mode functionality');
    }
    
    /**
     * Test that consent mode settings are passed to JavaScript
     */
    public function test_consent_mode_settings_passed_to_js() {
        // Mock the current template
        add_filter('klaro_geo_current_template_data', function($template_data) {
            return array(
                'consent_mode_settings' => array(
                    'initialize_consent_mode' => true,
                    'ad_storage_service' => 'google-ads',
                    'ad_user_data' => true,
                    'ad_personalization' => true,
                ),
            );
        });
        
        // Capture localized script data
        $localized_data = null;
        add_filter('klaro_geo_localize_script_data', function($data) use (&$localized_data) {
            $localized_data = $data;
            return $data;
        });
        
        // Call the function that would localize the script
        if (function_exists('klaro_geo_localize_scripts')) {
            klaro_geo_localize_scripts();
            
            // Check if the consent mode settings were included
            $this->assertArrayHasKey('templateSettings', $localized_data);
            $this->assertArrayHasKey('config', $localized_data['templateSettings']);
            $this->assertArrayHasKey('consent_mode_settings', $localized_data['templateSettings']['config']);
            $this->assertTrue($localized_data['templateSettings']['config']['consent_mode_settings']['initialize_consent_mode']);
            $this->assertEquals('google-ads', $localized_data['templateSettings']['config']['consent_mode_settings']['ad_storage_service']);
        } else {
            // If the function doesn't exist, this test is inconclusive
            $this->markTestIncomplete('klaro_geo_localize_scripts function not available');
        }
        
        // Remove the filters
        remove_all_filters('klaro_geo_current_template_data');
        remove_all_filters('klaro_geo_localize_script_data');
    }
    
    /**
     * Test that consent mode is disabled by default
     */
    public function test_consent_mode_disabled_by_default() {
        // Get template data for the default template
        $template_data = klaro_geo_get_template_data('default');
        
        // Check that consent mode is disabled by default
        $this->assertArrayHasKey('consent_mode_settings', $template_data);
        $this->assertFalse($template_data['consent_mode_settings']['initialize_consent_mode']);
        $this->assertEquals('no_service', $template_data['consent_mode_settings']['ad_storage_service']);
    }
    
    /**
     * Test that consent mode settings are saved correctly
     */
    public function test_consent_mode_settings_saved() {
        // Get the templates
        $templates = get_option('klaro_geo_templates', array());
        
        // Modify the consent mode settings
        $templates['default']['consent_mode_settings'] = array(
            'initialize_consent_mode' => true,
            'ad_storage_service' => 'google-ads',
            'ad_user_data' => true,
            'ad_personalization' => false,
        );
        
        // Save the templates
        update_option('klaro_geo_templates', $templates);
        
        // Get the template data again
        $template_data = klaro_geo_get_template_data('default');
        
        // Check that the settings were saved correctly
        $this->assertTrue($template_data['consent_mode_settings']['initialize_consent_mode']);
        $this->assertEquals('google-ads', $template_data['consent_mode_settings']['ad_storage_service']);
        $this->assertTrue($template_data['consent_mode_settings']['ad_user_data']);
        $this->assertFalse($template_data['consent_mode_settings']['ad_personalization']);
    }
    
    /**
     * Test that consent mode settings are merged with defaults
     */
    public function test_consent_mode_settings_merged_with_defaults() {
        // Get the templates
        $templates = get_option('klaro_geo_templates', array());
        
        // Set incomplete consent mode settings
        $templates['default']['consent_mode_settings'] = array(
            'initialize_consent_mode' => true,
            // Missing ad_storage_service
            'ad_user_data' => true,
            // Missing ad_personalization
        );
        
        // Save the templates
        update_option('klaro_geo_templates', $templates);
        
        // Get the template data
        $template_data = klaro_geo_get_template_data('default');
        
        // Check that missing settings were filled with defaults
        $this->assertTrue($template_data['consent_mode_settings']['initialize_consent_mode']);
        $this->assertEquals('no_service', $template_data['consent_mode_settings']['ad_storage_service']);
        $this->assertTrue($template_data['consent_mode_settings']['ad_user_data']);
        $this->assertFalse($template_data['consent_mode_settings']['ad_personalization']);
    }
}