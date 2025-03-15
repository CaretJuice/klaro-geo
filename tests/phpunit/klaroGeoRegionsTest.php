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
    }

    public function tearDown(): void {
        parent::tearDown();
        // Clean up after each test
        delete_option('klaro_geo_country_settings');
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

    /**
     * Test region settings retrieval
     */
    public function test_region_settings_retrieval() {
        // Set up test data with templates
        $settings = array(
            'default_template' => 'default',
            'fallback_behavior' => 'default',
            'countries' => array(
                'US' => array(
                    'template' => 'relaxed',
                    'regions' => array(
                        'CA' => array('template' => 'strict')
                    )
                )
            )
        );
        // Store settings and verify they are stored correctly
        update_option('klaro_geo_country_settings', wp_json_encode($settings));
        $stored = get_option('klaro_geo_country_settings');
        $this->assertNotFalse($stored, 'Settings should be stored');
        $decoded = json_decode($stored, true);
        $this->assertNotNull($decoded, 'Settings should be valid JSON');
        $this->assertEquals($settings, $decoded, 'Stored settings should match original');

        // Test getting settings for a specific region
        $_GET['klaro_geo_debug_geo'] = 'US-CA';
        $effective_settings = klaro_geo_get_effective_settings('US-CA');
        $this->assertEquals('strict', $effective_settings['template'], 'Should get region-specific template');
        unset($_GET['klaro_geo_debug_geo']);

        // Test getting settings for a country
        $_GET['klaro_geo_debug_geo'] = 'US';
        $effective_settings = klaro_geo_get_effective_settings('US');
        $this->assertEquals('relaxed', $effective_settings['template'], 'Should get country-level template');
        unset($_GET['klaro_geo_debug_geo']);

        // Test fallback to default
        $_GET['klaro_geo_debug_geo'] = 'FR';
        $effective_settings = klaro_geo_get_effective_settings('FR');
        $this->assertEquals('default', $effective_settings['template'], 'Should fall back to default template');
        unset($_GET['klaro_geo_debug_geo']);
    }
}
