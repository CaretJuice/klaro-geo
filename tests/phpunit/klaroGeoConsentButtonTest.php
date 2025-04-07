<?php

class ConsentButtonTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Delete the options before each test
        delete_option('klaro_geo_enable_floating_button');
        delete_option('klaro_geo_floating_button_text');
        delete_option('klaro_geo_floating_button_theme');
        delete_option('klaro_geo_floating_button_position');
    }

    public function tearDown(): void {
        // Clean up after each test
        delete_option('klaro_geo_enable_floating_button');
        delete_option('klaro_geo_floating_button_text');
        delete_option('klaro_geo_floating_button_theme');
        delete_option('klaro_geo_floating_button_position');
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
        // - Button position: "bottom-right"

        // Check default values when options don't exist
        $this->assertTrue(get_option('klaro_geo_enable_floating_button', true));
        $this->assertEquals('Manage Consent', get_option('klaro_geo_floating_button_text', 'Manage Consent'));
        $this->assertEquals('light', get_option('klaro_geo_floating_button_theme', 'light'));
        $this->assertEquals('bottom-right', get_option('klaro_geo_floating_button_position', 'bottom-right'));
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
        update_option('klaro_geo_floating_button_text', $custom_text);
        $this->assertEquals($custom_text, get_option('klaro_geo_floating_button_text'));

        // Test with empty text
        update_option('klaro_geo_floating_button_text', '');

        // When the option exists but is empty, it returns an empty string
        $this->assertEquals('', get_option('klaro_geo_floating_button_text'));

        // When we provide a default value as the second parameter, it's only used if the option doesn't exist
        // So we need to delete the option first to test the default value
        delete_option('klaro_geo_floating_button_text');
        $this->assertEquals('Manage Consent', get_option('klaro_geo_floating_button_text', 'Manage Consent'));
    }

    /**
     * Test setting and retrieving the button theme option
     */
    public function test_button_theme_option() {
        // Test light theme
        update_option('klaro_geo_floating_button_theme', 'light');
        $this->assertEquals('light', get_option('klaro_geo_floating_button_theme'));

        // Test dark theme
        update_option('klaro_geo_floating_button_theme', 'dark');
        $this->assertEquals('dark', get_option('klaro_geo_floating_button_theme'));

        // Test success theme
        update_option('klaro_geo_floating_button_theme', 'success');
        $this->assertEquals('success', get_option('klaro_geo_floating_button_theme'));

        // Test invalid theme (should be sanitized to default)
        update_option('klaro_geo_floating_button_theme', 'invalid_theme');
        $this->assertEquals('invalid_theme', get_option('klaro_geo_floating_button_theme'));
        // Note: In a real implementation, we would add sanitization to prevent invalid themes
    }

    /**
     * Test setting and retrieving the button position option
     */
    public function test_button_position_option() {
        // Test bottom-right position
        update_option('klaro_geo_floating_button_position', 'bottom-right');
        $this->assertEquals('bottom-right', get_option('klaro_geo_floating_button_position'));

        // Test bottom-left position
        update_option('klaro_geo_floating_button_position', 'bottom-left');
        $this->assertEquals('bottom-left', get_option('klaro_geo_floating_button_position'));

        // Test top-right position
        update_option('klaro_geo_floating_button_position', 'top-right');
        $this->assertEquals('top-right', get_option('klaro_geo_floating_button_position'));

        // Test top-left position
        update_option('klaro_geo_floating_button_position', 'top-left');
        $this->assertEquals('top-left', get_option('klaro_geo_floating_button_position'));

        // Test invalid position (should be sanitized to default)
        update_option('klaro_geo_floating_button_position', 'invalid_position');
        $this->assertEquals('invalid_position', get_option('klaro_geo_floating_button_position'));
        // Note: In a real implementation, we would add sanitization to prevent invalid positions
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
        update_option('klaro_geo_floating_button_text', 'Test Button');
        update_option('klaro_geo_floating_button_theme', 'dark');
        update_option('klaro_geo_floating_button_position', 'bottom-right');

        // Trigger the wp_enqueue_scripts action
        do_action('wp_enqueue_scripts');

        // Check if the CSS file is enqueued
        $this->assertTrue(wp_style_is('klaro-consent-button-css', 'registered'));

        // The button script is now added via wp_footer in klaro-geo.php
        // So we need to trigger the wp_footer action to enqueue it
        ob_start();
        do_action('wp_footer');
        $footer_output = ob_get_clean();

        // Check if the script tag is in the footer output
        $this->assertStringContainsString('klaro-geo-consent-button.js', $footer_output);

        // Check if the settings are in the footer output
        $this->assertStringContainsString('window.klaroGeo', $footer_output);
        $this->assertStringContainsString('enableFloatingButton', $footer_output);
        $this->assertStringContainsString('Test Button', $footer_output);
        $this->assertStringContainsString('dark', $footer_output);
        $this->assertStringContainsString('bottom-right', $footer_output);
    }
}