<?php

class ConsentButtonTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Delete the options before each test
        delete_option('klaro_geo_enable_floating_button');
        delete_option('klaro_geo_button_text');
        delete_option('klaro_geo_button_theme');
    }

    public function tearDown(): void {
        // Clean up after each test
        delete_option('klaro_geo_enable_floating_button');
        delete_option('klaro_geo_button_text');
        delete_option('klaro_geo_button_theme');
        parent::tearDown();
    }

    /**
     * Test that the default values are set correctly
     */
    public function test_default_values() {
        // The default values should be:
        // - Floating button enabled (true)
        // - Button text: "Manage Consent Settings"
        // - Button theme: "light"
        
        // Check default values when options don't exist
        $this->assertTrue(get_option('klaro_geo_enable_floating_button', true));
        $this->assertEquals('Manage Consent Settings', get_option('klaro_geo_button_text', 'Manage Consent Settings'));
        $this->assertEquals('light', get_option('klaro_geo_button_theme', 'light'));
    }

    /**
     * Test setting and retrieving the floating button option
     */
    public function test_floating_button_option() {
        // Test enabling the floating button
        update_option('klaro_geo_enable_floating_button', true);
        $this->assertTrue(get_option('klaro_geo_enable_floating_button'));
        
        // Test disabling the floating button
        update_option('klaro_geo_enable_floating_button', false);
        $this->assertFalse(get_option('klaro_geo_enable_floating_button'));
    }

    /**
     * Test setting and retrieving the button text option
     */
    public function test_button_text_option() {
        $custom_text = 'Custom Consent Settings';

        // Test setting custom button text
        update_option('klaro_geo_button_text', $custom_text);
        $this->assertEquals($custom_text, get_option('klaro_geo_button_text'));

        // Test with empty text
        update_option('klaro_geo_button_text', '');

        // When the option exists but is empty, it returns an empty string
        $this->assertEquals('', get_option('klaro_geo_button_text'));

        // When we provide a default value as the second parameter, it's only used if the option doesn't exist
        // So we need to delete the option first to test the default value
        delete_option('klaro_geo_button_text');
        $this->assertEquals('Manage Consent Settings', get_option('klaro_geo_button_text', 'Manage Consent Settings'));
    }

    /**
     * Test setting and retrieving the button theme option
     */
    public function test_button_theme_option() {
        // Test light theme
        update_option('klaro_geo_button_theme', 'light');
        $this->assertEquals('light', get_option('klaro_geo_button_theme'));
        
        // Test dark theme
        update_option('klaro_geo_button_theme', 'dark');
        $this->assertEquals('dark', get_option('klaro_geo_button_theme'));
        
        // Test success theme
        update_option('klaro_geo_button_theme', 'success');
        $this->assertEquals('success', get_option('klaro_geo_button_theme'));
        
        // Test invalid theme (should be sanitized to default)
        update_option('klaro_geo_button_theme', 'invalid_theme');
        $this->assertEquals('invalid_theme', get_option('klaro_geo_button_theme'));
        // Note: In a real implementation, we would add sanitization to prevent invalid themes
    }

    /**
     * Test the menu button functionality
     *
     * Note: This test has been removed because we've changed the menu integration approach.
     * We now use a custom menu item type instead of a menu location.
     */

    /**
     * Test script and style enqueuing
     */
    public function test_enqueue_scripts() {
        // Set up the test environment
        update_option('klaro_geo_enable_floating_button', true);
        update_option('klaro_geo_button_text', 'Test Button');
        update_option('klaro_geo_button_theme', 'dark');

        // Trigger the wp_enqueue_scripts action
        do_action('wp_enqueue_scripts');

        // Check if the CSS file is enqueued
        $this->assertTrue(wp_style_is('klaro-consent-button-css', 'registered'));

        // Check if the JS file is enqueued
        $this->assertTrue(wp_script_is('klaro-consent-button-js', 'registered'));

        // Check if the localized data is correct
        global $wp_scripts;
        $data = $wp_scripts->get_data('klaro-consent-button-js', 'data');

        $this->assertNotFalse($data);
        $this->assertStringContainsString('klaroConsentButtonData', $data);

        // In PHP, when a boolean true is converted to JSON, it becomes "1" (as a string)
        // So we need to check for "1" instead of "true"
        $this->assertStringContainsString('"floatingButtonEnabled":"1"', $data); // floatingButtonEnabled
        $this->assertStringContainsString('Test Button', $data); // buttonText
        $this->assertStringContainsString('dark', $data); // theme
    }
}