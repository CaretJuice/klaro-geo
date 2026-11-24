<?php
/**
 * Tests for the Klaro_Geo_Template_Settings class
 */

class KlaroGeoTemplateSettingsClassTest extends WP_UnitTestCase {
    /**
     * Test option name
     */
    private $option_name = 'klaro_geo_test_templates';

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Make sure the option doesn't exist
        delete_option($this->option_name);

        // Clear singleton caches AFTER deleting options to ensure fresh data
        Klaro_Geo_Template_Settings::clear_instance_cache();
        Klaro_Geo_Country_Settings::clear_instance_cache();
        Klaro_Geo_Service_Settings::clear_instance_cache();

        // Mock the option name for testing
        add_filter('pre_option_klaro_geo_templates', array($this, 'mock_templates'));
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Clean up
        delete_option($this->option_name);

        // Remove filter
        remove_filter('pre_option_klaro_geo_templates', array($this, 'mock_templates'));

        parent::tearDown();
    }
    
    /**
     * Mock templates for testing
     */
    public function mock_templates($value) {
        $test_value = get_option($this->option_name, null);
        return $test_value !== null ? $test_value : $value;
    }

    /**
     * Test constructor and default templates
     */
    public function test_constructor() {
        // Test with default values but use our test option name
        $settings = new Klaro_Geo_Template_Settings($this->option_name);

        // Set default templates manually since we're using a test option name
        $default_templates = $settings->get_default_templates();
        $settings->set($default_templates);

        // Check that default templates are loaded
        $templates = $settings->get();
        $this->assertArrayHasKey('default', $templates);
        $this->assertArrayHasKey('strict', $templates);
        $this->assertArrayHasKey('relaxed', $templates);
    }

    /**
     * Test getting default templates
     */
    public function test_get_default_templates() {
        // Create settings
        $settings = new Klaro_Geo_Template_Settings();
        
        // Get default templates
        $templates = $settings->get_default_templates();
        
        // Check that default templates exist
        $this->assertArrayHasKey('default', $templates);
        $this->assertArrayHasKey('strict', $templates);
        $this->assertArrayHasKey('relaxed', $templates);
        
        // Check template properties
        $this->assertEquals('Default Template', $templates['default']['name']);
        $this->assertEquals('Strict Opt-In', $templates['strict']['name']);
        $this->assertEquals('Relaxed Opt-Out', $templates['relaxed']['name']);
        
        // Check template configs
        $this->assertFalse($templates['default']['config']['default']);
        $this->assertTrue($templates['strict']['config']['mustConsent']);
        $this->assertTrue($templates['relaxed']['config']['default']);
    }

    /**
     * Test getting and setting templates
     */
    public function test_template_operations() {
        // Create settings
        $settings = new Klaro_Geo_Template_Settings();
        
        // Test getting a template
        $default_template = $settings->get_template('default');
        $this->assertNotNull($default_template);
        $this->assertEquals('Default Template', $default_template['name']);
        
        // Test getting a non-existent template
        $this->assertNull($settings->get_template('non_existent'));
        
        // Test setting a template
        $new_template = array(
            'name' => 'Test Template',
            'description' => 'A test template',
            'config' => array(
                'default' => true,
                'mustConsent' => false
            )
        );
        $settings->set_template('test', $new_template);
        
        // Check that template was set
        $this->assertEquals($new_template, $settings->get_template('test'));
        
        // Test removing a template
        $settings->remove_template('test');
        
        // Check that template was removed
        $this->assertNull($settings->get_template('test'));
    }

    /**
     * Test getting and setting template config
     */
    public function test_template_config() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Template_Settings($this->option_name);

        // Set up a test template
        $template = array(
            'name' => 'Default Template',
            'description' => 'The default template',
            'config' => array(
                'default' => false,
                'mustConsent' => false,
                'acceptAll' => true
            )
        );
        $settings->set_template('default', $template);

        // Test getting a template config
        $default_config = $settings->get_template_config('default');
        $this->assertNotNull($default_config);
        $this->assertFalse($default_config['default']);

        // Test getting a non-existent template config
        $this->assertNull($settings->get_template_config('non_existent'));
        
        // Test setting a template config for an existing template
        $new_config = array(
            'default' => true,
            'mustConsent' => false,
            'customSetting' => 'value'
        );
        $settings->set_template_config('default', $new_config);
        
        // Check that config was set
        $this->assertEquals($new_config, $settings->get_template_config('default'));
        
        // Test setting a template config for a non-existent template
        $settings->set_template_config('new_template', $new_config);
        
        // Check that template was created with the config
        $new_template = $settings->get_template('new_template');
        $this->assertNotNull($new_template);
        $this->assertEquals('new_template', $new_template['name']);
        $this->assertEquals($new_config, $new_template['config']);
    }

}