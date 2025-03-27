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

        // Verify the default services are defined in GLOBALS
        $this->assertArrayHasKey('default_services', $GLOBALS);
        $this->assertIsArray($GLOBALS['default_services']);
        $this->assertCount(1, $GLOBALS['default_services']); // Default Google Tag Manager service
        $this->assertEquals('google-tag-manager', $GLOBALS['default_services'][0]['name']);
        $this->assertEquals(['analytics', 'advertising'], $GLOBALS['default_services'][0]['purposes']);
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
