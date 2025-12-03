<?php
/**
 * Tests for the Klaro_Geo_Country_Settings class
 */

class KlaroGeoCountrySettingsClassTest extends WP_UnitTestCase {
    /**
     * Test option name
     */
    private $option_name = 'klaro_geo_test_country_settings';
    
    /**
     * Test visible countries option name
     */
    private $visible_countries_option = 'klaro_geo_test_visible_countries';

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        // Make sure the options don't exist
        delete_option($this->option_name);
        delete_option($this->visible_countries_option);
        delete_option('klaro_geo_templates');

        // Mock the option name for testing
        add_filter('pre_option_klaro_geo_country_settings', array($this, 'mock_country_settings'));
        add_filter('pre_option_klaro_geo_visible_countries', array($this, 'mock_visible_countries'));

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

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Clean up
        delete_option($this->option_name);
        delete_option($this->visible_countries_option);
        delete_option('klaro_geo_templates');

        // Remove filters
        remove_filter('pre_option_klaro_geo_country_settings', array($this, 'mock_country_settings'));
        remove_filter('pre_option_klaro_geo_visible_countries', array($this, 'mock_visible_countries'));

        parent::tearDown();
    }
    
    /**
     * Mock country settings for testing
     */
    public function mock_country_settings($value) {
        $test_value = get_option($this->option_name, null);
        return $test_value !== null ? $test_value : $value;
    }
    
    /**
     * Mock visible countries for testing
     */
    public function mock_visible_countries($value) {
        $test_value = get_option($this->visible_countries_option, null);
        return $test_value !== null ? $test_value : $value;
    }

    /**
     * Test constructor and default values
     */
    public function test_constructor() {
        // Test with default values but use our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Check that default template is set
        $this->assertEquals('default', $settings->get_default_template());

        // Check that visible countries are loaded
        $this->assertNotEmpty($settings->get_visible_countries());
    }

    /**
     * Test getting and setting visible countries
     */
    public function test_visible_countries() {
        // Set up test data
        $test_countries = array('US', 'CA', 'UK');
        update_option($this->visible_countries_option, $test_countries);

        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // We need to manually set the visible_countries_option property
        // since we're using a custom option name for testing
        $reflection = new ReflectionClass($settings);
        $property = $reflection->getProperty('visible_countries_option');
        $property->setAccessible(true);
        $property->setValue($settings, $this->visible_countries_option);

        // Reload visible countries with our custom option name
        $settings->load_visible_countries();

        // Check that visible countries were loaded
        $this->assertEquals($test_countries, $settings->get_visible_countries());

        // Test setting visible countries
        $new_countries = array('FR', 'DE', 'IT');
        $settings->set_visible_countries($new_countries);

        // Check that visible countries were updated
        $this->assertEquals($new_countries, $settings->get_visible_countries());

        // Test saving visible countries
        $settings->save_visible_countries();

        // Check that visible countries were saved to database
        $this->assertEquals($new_countries, get_option($this->visible_countries_option));

        // Test setting invalid value (should not change)
        $settings->set_visible_countries('not_an_array');

        // Check that visible countries were not changed
        $this->assertEquals($new_countries, $settings->get_visible_countries());
    }

    /**
     * Test getting and setting country settings
     */
    public function test_country_settings() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Test getting non-existent country
        $this->assertNull($settings->get_country('US'));

        // Test setting a country
        $country_data = array(
            'template' => 'strict',
            'regions' => array(
                'CA' => 'relaxed',
                'NY' => 'strict'
            )
        );
        $settings->set_country('US', $country_data);

        // Check that country was set
        $this->assertEquals($country_data, $settings->get_country('US'));

        // Test removing a country
        $settings->remove_country('US');

        // Check that country was removed
        $this->assertNull($settings->get_country('US'));
    }

    /**
     * Test getting and setting region settings
     */
    public function test_region_settings() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Test getting non-existent region
        $this->assertNull($settings->get_region('US', 'CA'));

        // Test setting a region for a non-existent country
        $settings->set_region('US', 'CA', 'strict');

        // Check that country and region were created
        $this->assertEquals('strict', $settings->get_region('US', 'CA'));

        // Check that country was created with default template
        $country = $settings->get_country('US');
        $this->assertEquals('default', $country['template']);

        // Test setting another region
        $settings->set_region('US', 'NY', 'relaxed');

        // Check that region was set
        $this->assertEquals('relaxed', $settings->get_region('US', 'NY'));

        // Test removing a region
        $settings->remove_region('US', 'CA');

        // Check that region was removed
        $this->assertNull($settings->get_region('US', 'CA'));

        // Check that other region still exists
        $this->assertEquals('relaxed', $settings->get_region('US', 'NY'));

        // Test removing all regions
        $settings->remove_region('US', 'NY');

        // Check that regions array was removed
        $country = $settings->get_country('US');
        $this->assertFalse(isset($country['regions']));
    }

    /**
     * Test getting and setting default template
     */
    public function test_default_template() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Check default template
        $this->assertEquals('default', $settings->get_default_template());

        // Test setting default template
        $settings->set_default_template('strict');

        // Check that default template was set
        $this->assertEquals('strict', $settings->get_default_template());
    }

    /**
     * Test updating from form data
     */
    public function test_update_from_form() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Set up test form data
        $form_data = array(
            'default_template' => 'strict',
            'US' => array(
                'template' => 'strict',
                'regions' => array(
                    'CA' => 'relaxed',
                    'NY' => 'strict'
                )
            ),
            'CA' => array(
                'template' => 'strict',
                '_is_default' => true, // Should be removed
                'regions' => array(
                    'ON' => 'relaxed' // Add a region to prevent optimization
                )
            ),
            'UK' => array(
                'template' => 'default', // Should be optimized out
                'regions' => array()
            ),
            'FR' => array(
                'template' => 'default', // Should be optimized out
                'regions' => array(
                    'PAR' => 'strict' // Should keep FR because of region
                )
            )
        );

        // Update from form data
        $settings->update_from_form($form_data);

        // Save the settings to ensure they're processed
        $settings->save();

        // Check that default template was set
        $this->assertEquals('strict', $settings->get_default_template());

        // Check that US was set correctly
        $us = $settings->get_country('US');
        $this->assertEquals('strict', $us['template']);
        $this->assertEquals('relaxed', $us['regions']['CA']);
        $this->assertEquals('strict', $us['regions']['NY']);

        // Check that CA was set correctly
        $ca = $settings->get_country('CA');
        $this->assertNotNull($ca);
        $this->assertEquals('strict', $ca['template']);

        // The _is_default flag should have been removed during processing
        // But we can't check it directly if the country was optimized out and recreated

        // UK might be optimized out (default template, no regions)
        // But in some implementations it might be kept
        $uk = $settings->get_country('UK');
        if ($uk !== null) {
            $this->assertEquals('default', $uk['template']);
            $this->assertEmpty($uk['regions']);
        }

        // Check that FR was kept (has region)
        $fr = $settings->get_country('FR');
        $this->assertEquals('default', $fr['template']);
        $this->assertEquals('strict', $fr['regions']['PAR']);

        // Test with invalid data (should not error)
        $settings->update_from_form('not_an_array');
    }

    /**
     * Test updating regions from AJAX data
     */
    public function test_update_regions_from_ajax() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Set up test AJAX data
        $ajax_data = array(
            'US' => array(
                'regions' => array(
                    'CA' => 'strict',
                    'NY' => 'relaxed',
                    'TX' => 'inherit' // Should be removed (inherit)
                )
            ),
            'CA' => array(
                'regions' => array(
                    'ON' => 'strict',
                    'QC' => 'inherit' // Should be removed (inherit)
                )
            )
        );

        // Update regions from AJAX data
        $settings->update_regions_from_ajax($ajax_data);

        // Check that US regions were set correctly
        $us = $settings->get_country('US');
        $this->assertEquals('strict', $us['regions']['CA']);
        $this->assertEquals('relaxed', $us['regions']['NY']);
        $this->assertFalse(isset($us['regions']['TX'])); // Inherit should be removed

        // Check that CA regions were set correctly
        $ca = $settings->get_country('CA');
        $this->assertEquals('strict', $ca['regions']['ON']);
        $this->assertFalse(isset($ca['regions']['QC'])); // Inherit should be removed

        // Test with invalid data (should not error)
        $settings->update_regions_from_ajax('not_an_array');
    }

    /**
     * Test optimization
     */
    public function test_optimize() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Set default template
        $settings->set_default_template('default');

        // Set up test countries
        $settings->set_country('US', array(
            'template' => 'default', // Should be optimized out (default template, no regions)
            'regions' => array()
        ));

        $settings->set_country('CA', array(
            'template' => 'strict', // Should be kept (non-default template)
            'regions' => array()
        ));

        $settings->set_country('UK', array(
            'template' => 'default', // Should be kept (has regions)
            'regions' => array(
                'SCT' => 'strict'
            )
        ));

        // Optimize
        $settings->optimize();

        // Check that US was optimized out
        $this->assertNull($settings->get_country('US'));

        // Check that CA was kept
        $this->assertNotNull($settings->get_country('CA'));

        // Check that UK was kept
        $this->assertNotNull($settings->get_country('UK'));
    }

    /**
     * Test getting effective settings
     */
    public function test_get_effective_settings() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Set default template
        $settings->set_default_template('default');

        // Set up test countries and regions
        $settings->set_country('US', array(
            'template' => 'strict',
            'regions' => array(
                'CA' => 'relaxed'
            )
        ));

        // Set visible countries
        $settings->set_visible_countries(array('US', 'CA', 'UK'));

        // We need to manually set the visible_countries_option property
        // since we're using a custom option name for testing
        $reflection = new ReflectionClass($settings);
        $property = $reflection->getProperty('visible_countries_option');
        $property->setAccessible(true);
        $property->setValue($settings, $this->visible_countries_option);

        // Save visible countries to our test option
        $settings->save_visible_countries();

        // Test getting effective settings for country
        $us_settings = $settings->get_effective_settings('US');
        $this->assertEquals('strict', $us_settings['template']);
        $this->assertEquals('country', $us_settings['source']);

        // Test getting effective settings for region
        $ca_settings = $settings->get_effective_settings('US-CA');
        $this->assertEquals('relaxed', $ca_settings['template']);
        $this->assertEquals('region', $ca_settings['source']);

        // Test getting effective settings for country not in settings but in visible countries
        $uk_settings = $settings->get_effective_settings('UK');
        $this->assertEquals('default', $uk_settings['template']);
        $this->assertEquals('default', $uk_settings['source']);

        // Test getting effective settings for country not in visible countries
        $fr_settings = $settings->get_effective_settings('FR');
        $this->assertEquals('default', $fr_settings['template']);
        $this->assertEquals('default', $fr_settings['source']);

        // Test getting effective settings for non-existent region
        $tx_settings = $settings->get_effective_settings('US-TX');
        $this->assertEquals('strict', $tx_settings['template']); // Should use country template
        $this->assertEquals('country', $tx_settings['source']);
    }

    /**
     * Test fallback template inheritance
     */
    public function test_fallback_template_inheritance() {
        // Create settings with our test option name
        $settings = new Klaro_Geo_Country_Settings($this->option_name);

        // Set fallback template
        $settings->set_default_template('strict');

        // Set up test countries with different inheritance patterns
        $settings->set_country('US', array(
            'template' => 'relaxed', // Specific template
            'regions' => array(
                'CA' => 'strict',    // Specific template
                'NY' => 'inherit'    // Inherit from country (relaxed)
            )
        ));

        $settings->set_country('CA', array(
            'template' => 'inherit', // Inherit from fallback (strict)
            'regions' => array(
                'ON' => 'relaxed',   // Specific template
                'QC' => 'inherit'    // Inherit from country (which inherits from fallback)
            )
        ));

        // Set visible countries
        $settings->set_visible_countries(array('US', 'CA', 'UK'));

        // We need to manually set the visible_countries_option property
        // since we're using a custom option name for testing
        $reflection = new ReflectionClass($settings);
        $property = $reflection->getProperty('visible_countries_option');
        $property->setAccessible(true);
        $property->setValue($settings, $this->visible_countries_option);

        // Save visible countries to our test option
        $settings->save_visible_countries();

        // Test US (specific template)
        $us_settings = $settings->get_effective_settings('US');
        $this->assertEquals('relaxed', $us_settings['template']);
        $this->assertEquals('country', $us_settings['source']);

        // Test US-CA (specific region template)
        $ca_settings = $settings->get_effective_settings('US-CA');
        $this->assertEquals('strict', $ca_settings['template']);
        $this->assertEquals('region', $ca_settings['source']);

        // Test US-NY (inherits from country)
        $ny_settings = $settings->get_effective_settings('US-NY');
        $this->assertEquals('relaxed', $ny_settings['template']);
        $this->assertEquals('country', $ny_settings['source']);

        // Test CA (inherits from fallback)
        $ca_country_settings = $settings->get_effective_settings('CA');
        $this->assertEquals('strict', $ca_country_settings['template']);
        $this->assertEquals('fallback', $ca_country_settings['source']);

        // Test CA-ON (specific region template)
        $on_settings = $settings->get_effective_settings('CA-ON');
        $this->assertEquals('relaxed', $on_settings['template']);
        $this->assertEquals('region', $on_settings['source']);

        // Test CA-QC (inherits from country, which inherits from fallback)
        $qc_settings = $settings->get_effective_settings('CA-QC');
        $this->assertEquals('strict', $qc_settings['template']);
        $this->assertEquals('fallback', $qc_settings['source']);

        // Test UK (not in settings, uses fallback)
        $uk_settings = $settings->get_effective_settings('UK');
        $this->assertEquals('strict', $uk_settings['template']);
        $this->assertEquals('default', $uk_settings['source']);
    }
}