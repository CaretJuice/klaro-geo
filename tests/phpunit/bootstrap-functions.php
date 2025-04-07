<?php
/**
 * Bootstrap functions for tests
 */

// Mock function for tests
if (!function_exists('klaro_geo_enqueue_scripts_for_test')) {
    function klaro_geo_enqueue_scripts_for_test() {
        global $wp_scripts;

        // Make sure klaro-js is registered first (dependency)
        if (!wp_script_is('klaro-js', 'registered')) {
            wp_register_script(
                'klaro-js',
                'https://cdn.kiprotect.com/klaro/v0.7/klaro.js',
                array(),
                '0.7',
                true
            );
        }

        // Get the plugin directory URL
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        // Register the script for testing
        wp_register_script(
            'klaro-geo-consent-mode-js',
            $plugin_url . 'js/klaro-geo-consent-mode.js',
            array('klaro-js'),
            '1.0',
            true
        );

        // Enqueue the script
        wp_enqueue_script('klaro-geo-consent-mode-js');

        // Localize the script with test data
        $template_data = array(
            'consent_mode_settings' => array(
                'initialize_consent_mode' => true,
                'ad_storage_service' => 'google-ads',
                'ad_user_data' => true,
                'ad_personalization' => true,
            )
        );

        wp_localize_script('klaro-geo-consent-mode-js', 'klaroConsentData', array(
            'templateSettings' => $template_data
        ));

        // Debug output
        error_log('Script registered: ' . (wp_script_is('klaro-geo-consent-mode-js', 'registered') ? 'Yes' : 'No'));
        error_log('Script enqueued: ' . (wp_script_is('klaro-geo-consent-mode-js', 'enqueued') ? 'Yes' : 'No'));

        // Force the script to be in the queue for testing
        if (isset($wp_scripts->registered['klaro-geo-consent-mode-js'])) {
            if (!in_array('klaro-geo-consent-mode-js', $wp_scripts->queue)) {
                $wp_scripts->queue[] = 'klaro-geo-consent-mode-js';
            }
        }
    }
}