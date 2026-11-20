<?php
/**
 * Class ServicesTest
 *
 * @package Klaro_Geo
 */
class ServicesTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        delete_option('klaro_geo_services');
    }

    public function tearDown(): void {
        delete_option('klaro_geo_services');
        parent::tearDown();
    }

    /**
     * Test that default services are created on activation
     */
    public function test_default_services_on_activation() {
        // Make sure we have access to default services
        require_once(plugin_dir_path(dirname(dirname(__FILE__))) . 'klaro-geo.php');

        // Verify the default services are defined in GLOBALS (renamed to klaro_geo_default_services in 0.3.5 for global prefixing)
        $this->assertArrayHasKey('klaro_geo_default_services', $GLOBALS);
        $this->assertIsArray($GLOBALS['klaro_geo_default_services']);
        $this->assertCount(7, $GLOBALS['klaro_geo_default_services']); // 3 standard + 4 consent mode services

        // Check for Google Tag Manager
        $gtm_found = false;
        foreach ($GLOBALS['klaro_geo_default_services'] as $service) {
            if ($service['name'] === 'google-tag-manager') {
                $gtm_found = true;
                $this->assertEquals(['infrastructure'], $service['purposes']);
                break;
            }
        }
        $this->assertTrue($gtm_found, 'Google Tag Manager service not found');
    }

    /**
     * Test saving a service
     */
    public function test_save_service() {
        $new_service = array(
            'name' => 'test-service',
            'required' => false,
            'default' => false,
            'purposes' => array('analytics'),
            'cookies' => array('test_cookie')
        );

        // Use the service settings class
        $service_settings = new Klaro_Geo_Service_Settings();
        $service_settings->set_service($new_service['name'], $new_service);
        $service_settings->save();

        // Get the service back using the class
        $saved_service = $service_settings->get_service($new_service['name']);
        $this->assertIsArray($saved_service);
        $this->assertEquals($new_service['name'], $saved_service['name']);
    }

    /**
     * Deleting from the services page removes an ordinary service, refuses a
     * consent mode service (consent mode depends on them) and reports a
     * position that does not exist.
     */
    public function test_delete_service_at_position() {
        $settings = Klaro_Geo_Service_Settings::get_instance();
        $settings->set(array(
            array('name' => 'analytics-storage', 'purposes' => array('analytics'), 'is_consent_mode_service' => true, 'consent_mode_key' => 'analytics_storage'),
            array('name' => 'my-pixel', 'purposes' => array('advertising')),
        ));
        $settings->save();

        $protected = klaro_geo_delete_service_at(0);
        $this->assertWPError($protected);
        $this->assertSame('klaro_geo_service_protected', $protected->get_error_code());

        $this->assertWPError(klaro_geo_delete_service_at(7));
        $this->assertWPError(klaro_geo_delete_service_at(-1));

        $this->assertTrue(klaro_geo_delete_service_at(1));

        Klaro_Geo_Service_Settings::clear_instance_cache();
        $this->assertSame(array('analytics-storage'), wp_list_pluck(Klaro_Geo_Service_Settings::get_instance()->get(), 'name'));
        Klaro_Geo_Service_Settings::clear_instance_cache();
    }

    /**
     * Test deleting a service
     */
    public function test_delete_service() {
        // First add a service
        $service = array(
            'name' => 'test-service',
            'required' => false,
            'default' => false,
            'purposes' => array('analytics'),
            'cookies' => array('test_cookie')
        );

        // Use the service settings class
        $service_settings = new Klaro_Geo_Service_Settings();
        $service_settings->set_service($service['name'], $service);
        $service_settings->save();

        // Verify the service was added
        $this->assertNotNull($service_settings->get_service($service['name']));

        // Delete the service
        $service_settings->remove_service($service['name']);
        $service_settings->save();

        // Verify the service was deleted
        $this->assertNull($service_settings->get_service($service['name']));

        // Get all services and verify count
        $all_services = $service_settings->get();
        $this->assertIsArray($all_services);

        // Check if the service we added was removed
        $found = false;
        foreach ($all_services as $s) {
            if (isset($s['name']) && $s['name'] === $service['name']) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Service should have been removed');
    }
}
