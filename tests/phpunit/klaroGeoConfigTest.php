<?php
/**
 * Class KlaroConfigTest
 *
 * @package Klaro_Geo
 */
class KlaroConfigTest extends WP_UnitTestCase {
    /**
     * The Klaro config the current visitor would receive: the template PHP
     * resolved for this request, read from the frontend payload.
     */
    private function extractConfigFromOutput($config_content) {
        $payload = klaro_geo_get_frontend_payload()['payload'];
        $this->assertSame('server', $payload['mode']);
        return $payload['templates'][$payload['resolved']['template']]['config'] ?? null;
    }

    public function setUp(): void {
        parent::setUp();

        // Include the main plugin file (this is CRUCIAL)
        require_once(plugin_dir_path(dirname(__DIR__)) . 'klaro-geo.php');
        require_once(plugin_dir_path(dirname(__DIR__)) . 'includes/klaro-geo-config.php');
        require_once(plugin_dir_path(dirname(__DIR__)) . 'includes/admin/klaro-geo-admin.php');

        // Reset options to defaults and set up the default services
        klaro_geo_activate();
    }

    public function tearDown(): void {
        // Reset the services to defaults in tearDown instead of deleting them
        $default_services = [
            [
                "name" => "google-tag-manager",
                "required" => false,
                "purposes" => ["analytics", "advertising"],
                "default" => false,
                "cookies" => []
            ]
        ];
        update_option('klaro_geo_services', wp_json_encode($default_services));

        // Clean up other options
        delete_option('klaro_geo_country_settings');
        delete_option('klaro_geo_gtm_oninit');
        delete_option('klaro_geo_gtm_onaccept');
        delete_option('klaro_geo_gtm_ondecline');
        delete_option('klaro_geo_analytics_purposes');
        delete_option('klaro_geo_ad_purposes');

        parent::tearDown();
    }

    public function test_services_are_set() {
        $services = klaro_geo_validate_services();
        $this->assertIsArray($services);
        $this->assertNotEmpty($services);
        $this->assertArrayHasKey('name',$services[0]);
    }

    public function test_analytics_service_config() {
        // Set up analytics purposes
        update_option('klaro_geo_analytics_purposes', json_encode(['analytics', 'functional']));

        // Generate the config content (returned as string since 0.3.5; no longer written to disk)
        $config_content = klaro_geo_generate_config_file();
        $this->assertIsString($config_content, 'Config generation failed');
        $this->assertNotEmpty($config_content, 'Config content is empty');

        // Add debug logging
        klaro_geo_debug_log('Analytics test - Config content length: ' . strlen($config_content));
        klaro_geo_debug_log('Analytics test - Config content first 100 chars: ' . substr($config_content, 0, 100));
        klaro_geo_debug_log('Analytics test - Full config content: ' . $config_content);

        // Extract the config from the generated content
        $config = $this->extractConfigFromOutput($config_content);

        // Verify we got a valid config
        $this->assertNotNull($config, 'Failed to extract config from output');

        // Verify config structure
        $this->assertIsArray($config, 'Config is not an array');
        $this->assertArrayHasKey('services', $config, 'Config does not have services key');
        $this->assertIsArray($config['services'], 'Services is not an array');
        $this->assertNotEmpty($config['services'], 'Services array is empty');

        // Find the service with analytics purposes
        $found_service = false;
        foreach ($config['services'] as $service) {
            $this->assertArrayHasKey('purposes', $service, 'Service does not have purposes key');
            if (in_array('analytics', $service['purposes']) || in_array('functional', $service['purposes'])) {
                $found_service = true;
                break;
            }
        }
        $this->assertTrue($found_service, 'Could not find service with analytics or functional purposes');
    }

    public function test_advertising_service_config() {
        // Set up advertising purposes
        update_option('klaro_geo_ad_purposes', json_encode(['advertising', 'personalization']));

        // Generate the config content (returned as string since 0.3.5; no longer written to disk)
        $config_content = klaro_geo_generate_config_file();
        $this->assertIsString($config_content, 'Config generation failed');
        $this->assertNotEmpty($config_content, 'Config content is empty');

        // Add debug logging
        klaro_geo_debug_log('Advertising test - Config content length: ' . strlen($config_content));
        klaro_geo_debug_log('Advertising test - Config content first 100 chars: ' . substr($config_content, 0, 100));
        klaro_geo_debug_log('Advertising test - Full config content: ' . $config_content);

        // Extract the config from the generated content
        $config = $this->extractConfigFromOutput($config_content);

        // Verify we got a valid config
        $this->assertNotNull($config, 'Failed to extract config from output');

        // Verify config structure
        $this->assertIsArray($config, 'Config is not an array');
        $this->assertArrayHasKey('services', $config, 'Config does not have services key');
        $this->assertIsArray($config['services'], 'Services is not an array');
        $this->assertNotEmpty($config['services'], 'Services array is empty');

        // Find the service with advertising purposes
        $found_service = false;
        foreach ($config['services'] as $service) {
            $this->assertArrayHasKey('purposes', $service, 'Service does not have purposes key');
            if (in_array('advertising', $service['purposes']) || in_array('personalization', $service['purposes'])) {
                $found_service = true;
                break;
            }
        }
        $this->assertTrue($found_service, 'Could not find service with advertising or personalization purposes');
    }
}