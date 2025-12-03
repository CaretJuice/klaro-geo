<?php
/**
 * Class KlaroGeoRegionsTest
 *
 * @package Klaro_Geo
 */

class KlaroGeoRegionsTest extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        // Reset options before each test
        delete_option('klaro_geo_country_settings');
        delete_option('klaro_geo_templates');

        // Create the templates that tests need for validation
        // This is required because get_effective_settings validates templates exist
        $template_settings = new Klaro_Geo_Template_Settings();
        $template_settings->set_template('default', array(
            'name' => 'Default Template',
            'config' => array('default' => false)
        ));
        $template_settings->set_template('strict', array(
            'name' => 'Strict Template',
            'config' => array('default' => false, 'mustConsent' => true)
        ));
        $template_settings->set_template('relaxed', array(
            'name' => 'Relaxed Template',
            'config' => array('default' => true, 'mustConsent' => false)
        ));
        $template_settings->save();
    }

    public function tearDown(): void {
        parent::tearDown();
        // Clean up after each test
        delete_option('klaro_geo_country_settings');
        delete_option('klaro_geo_templates');
    }

    /**
     * Test region code validation
     */
    public function test_region_code_validation() {
        $valid_regions = [
            'US-CA',
            'CA-QC',
            'US-NY',
            'CA-ON'
        ];

        $invalid_regions = [
            'USA',           // Too long country code
            'US-CALI',        // Too long region code
            'U-CA',         // Too short country code
            'US-C',         // Too short region code
            'US_CA',        // Invalid separator
            'USCA'          // Missing separator
        ];

        // Test valid regions
        foreach ($valid_regions as $region) {
            $sanitized = klaro_geo_sanitize_debug_geo($region);
            $this->assertEquals($region, $sanitized, "Valid region $region should remain unchanged");
        }

        // Test invalid regions
        foreach ($invalid_regions as $region) {
            $sanitized = klaro_geo_sanitize_debug_geo($region);
            $this->assertEquals('', $sanitized, "Invalid region $region should return empty string");
        }
    }

    /**
     * Test region settings storage and retrieval
     */
    public function test_region_settings_storage() {
        $settings = array(
            'default_template' => 'default',
            'fallback_behavior' => 'default',
            'US' => array(
                'template' => 'relaxed',
                'regions' => array(
                    'CA' => array('template' => 'strict'),
                    'NY' => array('template' => 'relaxed')
                )
            )
        );

        update_option('klaro_geo_country_settings', wp_json_encode($settings));
        $stored_settings = get_option('klaro_geo_country_settings');
        $this->assertNotFalse($stored_settings, 'Settings should be stored');

        $decoded_settings = json_decode($stored_settings, true);
        $this->assertEquals('relaxed', $decoded_settings['US']['template'], 'Country template should match');
        $this->assertEquals('strict', $decoded_settings['US']['regions']['CA']['template'], 'Region template should match');
    }

    // NOTE: test_region_settings_retrieval was removed as it is now covered by
    // ContractVerificationTest::test_contract_template_resolution_order
}
