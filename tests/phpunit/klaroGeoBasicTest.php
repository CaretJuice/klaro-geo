<?php
/**
 * Class BasicTest
 *
 * @package Klaro_Geo
 */
class BasicTest extends WP_UnitTestCase {
    /**
     * Test plugin initialization
     */
    public function test_plugin_initialized() {
        // Check for functions we know exist in klaro-geo.php
        $this->assertTrue(function_exists('klaro_geo_enqueue_scripts'), 'klaro_geo_enqueue_scripts should exist');
        $this->assertTrue(function_exists('klaro_geo_admin_scripts'), 'klaro_geo_admin_scripts should exist');
    }
}
