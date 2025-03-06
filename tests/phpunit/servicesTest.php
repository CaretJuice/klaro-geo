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
        require_once(plugin_dir_path(dirname(dirname(__FILE__))) . 'klaro.php');

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
            'service_name' => 'test-service',
            'service_required' => false,
            'service_default' => false,
            'service_purposes' => array('analytics'),
            'service_cookies' => array('test_cookie')
        );
        
        $services = array($new_service);
        update_option('klaro_geo_services', json_encode($services));
        
        $saved_services = json_decode(get_option('klaro_geo_services'), true);
        $this->assertIsArray($saved_services);
        $this->assertCount(1, $saved_services);
        $this->assertEquals($new_service['service_name'], $saved_services[0]['service_name']);
    }

    /**
     * Test deleting a service
     */
    public function test_delete_service() {
        // First add a service
        $initial_services = array(
            array(
                'service_name' => 'test-service',
                'service_required' => false,
                'service_default' => false,
                'service_purposes' => array('analytics'),
                'service_cookies' => array('test_cookie')
            )
        );
        
        update_option('klaro_geo_services', json_encode($initial_services));
        
        // Delete the service
        $services = json_decode(get_option('klaro_geo_services'), true);
        array_splice($services, 0, 1);
        update_option('klaro_geo_services', json_encode($services));
        
        $saved_services = json_decode(get_option('klaro_geo_services'), true);
        $this->assertIsArray($saved_services);
        $this->assertCount(0, $saved_services);
    }
}
