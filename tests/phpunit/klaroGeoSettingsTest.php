<?php

class KlaroGeoSettingsTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        delete_option('klaro_geo_settings');
    }

    public function tearDown(): void {
        delete_option('klaro_geo_settings');
        parent::tearDown();
    }

    public function test_get_location_settings() {
        // Set up test data
        $test_settings = array(
            'countries' => array(
                'US' => array(
                    'template' => 'us_template',
                    'regions' => array(
                        'CA' => array(
                            'template' => 'california_template'
                        )
                    )
                )
            )
        );
        update_option('klaro_geo_settings', $test_settings);

        // Test country settings
        $us_settings = klaro_geo_get_location_settings('US');
        $this->assertEquals('us_template', $us_settings['template']);

        // Test region settings
        $ca_settings = klaro_geo_get_location_settings('US-CA');
        $this->assertEquals('california_template', $ca_settings['template']);
        $this->assertTrue($ca_settings['is_region']);
    }

    public function test_update_location_settings() {
        // Test updating country settings
        $us_settings = array(
            'template' => 'us_template'
        );
        $result = klaro_geo_update_location_settings('US', $us_settings);
        $this->assertTrue($result);

        // Test updating region settings
        $ca_settings = array(
            'template' => 'california_template'
        );
        $result = klaro_geo_update_location_settings('US-CA', $ca_settings);
        $this->assertTrue($result);

        // Verify settings were saved correctly
        $all_settings = get_option('klaro_geo_settings');
        $this->assertEquals('us_template', $all_settings['countries']['US']['template']);
        $this->assertEquals('california_template', $all_settings['countries']['US']['regions']['CA']['template']);
    }

    public function test_get_country_regions() {
        // Set up test data
        $test_settings = array(
            'countries' => array(
                'US' => array(
                    'template' => 'us_template',
                    'regions' => array(
                        'CA' => array('template' => 'california_template'),
                        'NY' => array('template' => 'new_york_template')
                    )
                )
            )
        );
        update_option('klaro_geo_settings', $test_settings);

        // Test getting regions
        $regions = klaro_geo_get_country_regions('US');
        $this->assertCount(2, $regions);
        $this->assertEquals('california_template', $regions['CA']['template']);
        $this->assertEquals('new_york_template', $regions['NY']['template']);
    }

    public function test_region_settings_inheritance() {
        // Set up test data with multiple settings
        $test_settings = array(
            'countries' => array(
                'US' => array(
                    'template' => 'us_template',
                    'hideDeclineAll' => true,
                    'noticeAsModal' => false,
                    'regions' => array(
                        'CA' => array(
                            'template' => 'california_template',
                            'noticeAsModal' => true
                            // hideDeclineAll not defined - should inherit from country
                        )
                    )
                )
            )
        );
        update_option('klaro_geo_settings', $test_settings);

        // Get region settings
        $ca_settings = klaro_geo_get_location_settings('US-CA');

        // Verify region overrides and inheritance
        $this->assertEquals('california_template', $ca_settings['template'], 'Region should override country template');
        $this->assertTrue($ca_settings['hideDeclineAll'], 'Region should inherit country hideDeclineAll setting');
        $this->assertTrue($ca_settings['noticeAsModal'], 'Region should override country noticeAsModal setting');
        $this->assertTrue($ca_settings['is_region'], 'Region flag should be set');
    }

    public function test_get_effective_settings() {
        // Set up test data with inheritance and default template
        $test_settings = array(
            'default_template' => 'default',
            'countries' => array(
                'US' => array(
                    'template' => 'us_template',
                    'regions' => array(
                        'CA' => array(
                            'template' => 'california_template'
                        )
                    )
                )
            )
        );
        update_option('klaro_geo_settings', wp_json_encode($test_settings));

        // Test country settings
        $us_settings = klaro_geo_get_effective_settings('US');
        $this->assertEquals('us_template', $us_settings['template']);

        // Test region settings
        $ca_settings = klaro_geo_get_effective_settings('US-CA');
        $this->assertEquals('california_template', $ca_settings['template']);

        // Test non-existent location falls back to defaults
        $default_settings = klaro_geo_get_effective_settings('XX');
        $this->assertEquals('default', $default_settings['template']);
    }
}