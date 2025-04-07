<?php
/**
 * Class ServicesPageTest
 *
 * @package Klaro_Geo
 */
if (!class_exists('WPDieException')) {
    class WPDieException extends Exception { }
}

class ServicesPageTest extends WP_UnitTestCase {
    protected $admin_user_id;

    public function setUp(): void {
        parent::setUp();
        delete_option('klaro_geo_services');
        
        $this->admin_user_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));
        
        add_filter('wp_die_ajax_handler', function() {
            throw new WPDieException();
        }, 1, 0);
    }

    public function tearDown(): void {
        delete_option('klaro_geo_services');
        wp_delete_user($this->admin_user_id);
        parent::tearDown();
    }

    /**
     * Test that the services page is added to the admin menu
     */
    public function test_services_page_added_to_menu() {
        wp_set_current_user($this->admin_user_id);
        do_action('admin_menu');
        
        global $submenu;
        $found = false;
        
        if (isset($submenu['klaro-geo'])) {
            foreach ($submenu['klaro-geo'] as $item) {
                if ($item[2] === 'klaro-geo-services') {
                    $found = true;
                    break;
                }
            }
        }
        
        $this->assertTrue($found, 'Services page should be added to admin menu');
    }

    /**
     * Test services page content rendering
     */
    public function test_services_page_content_rendering() {
        wp_set_current_user($this->admin_user_id);
        
        ob_start();
        klaro_geo_services_page_content();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Klaro Services', $output);
        $this->assertStringContainsString('Add New Service', $output);
        $this->assertStringContainsString('klaro-services-table', $output);
    }
}
