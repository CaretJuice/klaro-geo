<?php
/**
 * Class CountryConfigTest
 *
 * @package Klaro_Geo
 */
class CountryConfigTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        delete_option('klaro_geo_services');
        delete_option('klaro_geo_settings');
        delete_option('klaro_geo_templates');

        // Set up default templates
        $templates = klaro_geo_get_default_templates();
        update_option('klaro_geo_templates', wp_json_encode($templates));
    }

    public function tearDown(): void {
        delete_option('klaro_geo_services');
        delete_option('klaro_geo_settings');
        delete_option('klaro_geo_templates');
        parent::tearDown();
    }

    /**
     * Test that strict template has services disabled by default
     */
    public function test_strict_template_config() {
        // Set up a test service using global settings
        $test_service = [
            'name' => 'test-service',
            'purposes' => ['analytics'],
            'cookies' => []
        ];
        update_option('klaro_geo_services', json_encode([$test_service]));

        // Set up settings with strict template for US
        $settings = [
            'default_template' => 'default',
            'countries' => [
                'US' => [
                    'template' => 'strict',
                    'regions' => []
                ]
            ]
        ];
        update_option('klaro_geo_settings', wp_json_encode($settings));

        // Set debug country to US
        set_query_var('klaro_geo_debug_geo', 'US');

        // Generate config
        klaro_geo_generate_config_file();

        // Read the generated config file
        $config_file = plugin_dir_path(dirname(dirname(__FILE__))) . 'klaro-config.js';
        $config_content = file_get_contents($config_file);

        // Parse the config content to get the service settings using a more robust method
        if (preg_match('/var\s+klaroConfig\s*=\s*(\{[\s\S]*?\}\s*);/m', $config_content, $matches)) {
            $config = json_decode($matches[1], true);

            // Check that the service is disabled by default for strict template
            $this->assertNotNull($config, 'Failed to parse config JSON');
            $this->assertArrayHasKey('default', $config, 'Config does not have default key');
            $this->assertArrayHasKey('required', $config, 'Config does not have required key');
            $this->assertFalse($config['default'], 'Service should be disabled by default with strict template using the Global setting');
            $this->assertFalse($config['required'], 'Service should not be required with strict template using the Global setting');
        } else {
            $this->fail('Could not extract config from JavaScript file');
        }
    }

    /**
     * Test that relaxed template has services enabled by default
     */
    public function test_relaxed_template_config() {
        // Set up a test service using global settings
        $test_service = [
            'name' => 'test-service',
            'purposes' => ['analytics'],
            'cookies' => []
        ];
        update_option('klaro_geo_services', json_encode([$test_service]));

        // Set up settings with relaxed template for UK
        $settings = [
            'default_template' => 'default',
            'countries' => [
                'UK' => [
                    'template' => 'relaxed',
                    'regions' => []
                ]
            ]
        ];
        update_option('klaro_geo_settings', wp_json_encode($settings));

        // Set debug country to UK
        set_query_var('klaro_geo_debug_geo', 'UK');

        // Generate config
        klaro_geo_generate_config_file();

        // Read the generated config file
        $config_file = plugin_dir_path(dirname(dirname(__FILE__))) . 'klaro-config.js';
        $config_content = file_get_contents($config_file);

        // Parse the config content to get the service settings using a more robust method
        if (preg_match('/var\s+klaroConfig\s*=\s*(\{[\s\S]*?\}\s*);/m', $config_content, $matches)) {
            $config = json_decode($matches[1], true);

            // Check that the service is enabled by default for relaxed template
            $this->assertNotNull($config, 'Failed to parse config JSON');
            $this->assertArrayHasKey('default', $config, 'Config does not have default key');
            $this->assertArrayHasKey('required', $config, 'Config does not have required key');
            $this->assertTrue($config['default'], 'Service should be enabled by default with relaxed template using the Global setting');
            $this->assertFalse($config['required'], 'Service should not be required with relaxed template using the Global setting');
        } else {
            $this->fail('Could not extract config from JavaScript file');
        }
    }
}