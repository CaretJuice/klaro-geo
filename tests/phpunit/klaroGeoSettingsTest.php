<?php

class KlaroGeoSettingsTest extends WP_UnitTestCase {
    private $country_settings;

    public function setUp(): void {
        parent::setUp();
        delete_option('klaro_geo_country_settings');
        delete_option('klaro_geo_templates');

        // Create templates for consistency with other test files
        // and to support any future template validation
        $template_settings = new Klaro_Geo_Template_Settings();
        $template_settings->set_template('default', array(
            'name' => 'Default Template',
            'config' => array('default' => false)
        ));
        $template_settings->set_template('us_template', array(
            'name' => 'US Template',
            'config' => array('default' => false, 'required' => false)
        ));
        $template_settings->set_template('california_template', array(
            'name' => 'California Template',
            'config' => array('default' => false, 'required' => false)
        ));
        $template_settings->set_template('new_york_template', array(
            'name' => 'New York Template',
            'config' => array('default' => false, 'required' => false)
        ));
        $template_settings->save();

        $this->country_settings = new Klaro_Geo_Country_Settings();
    }

    public function tearDown(): void {
        delete_option('klaro_geo_country_settings');
        delete_option('klaro_geo_templates');
        parent::tearDown();
    }

    public function test_get_location_settings() {
        // Set up test data
        $test_settings = array(
            'US' => array(
                'template' => 'us_template',
                'regions' => array(
                    'CA' => array(
                        'template' => 'california_template'
                    )
                )
            )
        );

        // Use class methods to set and save the settings
        $this->country_settings->set($test_settings);
        $this->country_settings->save();

        // Test country settings
        $us_settings = $this->country_settings->get_location_settings('US');
        $this->assertEquals('us_template', $us_settings['template']);

        // Test region settings
        $ca_settings = $this->country_settings->get_location_settings('US-CA');
        $this->assertEquals('california_template', $ca_settings['template']);
        $this->assertTrue($ca_settings['is_region']);
    }

    public function test_update_location_settings() {
        // Test updating country settings
        $us_settings = array(
            'template' => 'us_template'
        );
        $result = $this->country_settings->update_location_settings('US', $us_settings);
        $this->assertTrue($result);

        // Test updating region settings
        $ca_settings = array(
            'template' => 'california_template'
        );
        $result = $this->country_settings->update_location_settings('US-CA', $ca_settings);
        $this->assertTrue($result);

        // Save the settings to the database
        $this->country_settings->save();

        // Reload the settings
        $this->country_settings = new Klaro_Geo_Country_Settings();

        // Verify settings were saved correctly
        $all_settings = $this->country_settings->get();
        $this->assertIsArray($all_settings);
        $this->assertEquals('us_template', $all_settings['US']['template']);
        $this->assertEquals('california_template', $all_settings['US']['regions']['CA']);
    }
    public function test_get_country_regions() {
        // Set up test data
        $test_settings = array(
            'US' => array(
                'template' => 'us_template',
                'regions' => array(
                    'CA' => array('template' => 'california_template'),
                    'NY' => array('template' => 'new_york_template')
                )
            )
        );

        // Use class methods to set and save the settings
        $this->country_settings->set($test_settings);
        $this->country_settings->save();

        // Test getting regions
        $regions = $this->country_settings->get_country_regions('US');
        $this->assertCount(2, $regions);
        $this->assertEquals('california_template', $regions['CA']['template']);
        $this->assertEquals('new_york_template', $regions['NY']['template']);
    }

    public function test_region_settings_inheritance() {
        // Templates are now created in setUp() - no filter needed

        // Set up test data with multiple settings
        $test_settings = array(
            'default_template' => 'default',
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
        );

        // Use class methods to set and save the settings
        $this->country_settings->set($test_settings);
        $this->country_settings->save();

        // Get region settings
        $ca_settings = $this->country_settings->get_location_settings('US-CA');

        // Verify region overrides and inheritance
        $this->assertEquals('california_template', $ca_settings['template'], 'Region should override country template');
        $this->assertTrue($ca_settings['hideDeclineAll'], 'Region should inherit country hideDeclineAll setting');
        $this->assertTrue($ca_settings['noticeAsModal'], 'Region should override country noticeAsModal setting');
        $this->assertTrue($ca_settings['is_region'], 'Region flag should be set');
    }
}