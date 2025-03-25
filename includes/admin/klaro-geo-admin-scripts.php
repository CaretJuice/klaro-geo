<?php
/**
 * Admin scripts for Klaro Geo
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Register and enqueue all admin scripts and styles
 */
function klaro_geo_admin_scripts($hook) {
    // Only load on Klaro Geo admin pages
    if (strpos($hook, 'klaro-geo') === false) {
        return;
    }

    // Debug log
    klaro_geo_debug_log('Loading admin scripts for hook: ' . $hook);

    // 1. Enqueue common admin styles
    wp_enqueue_style(
        'klaro-geo-admin',
        KLARO_GEO_URL . 'css/klaro-geo-admin.css',
        array(),
        KLARO_GEO_VERSION
    );

    // Add jQuery UI styles for tabs
    wp_enqueue_style(
        'jquery-ui-style',
        'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
        array(),
        '1.12.1'
    );

    // Add translations CSS
    wp_enqueue_style(
        'klaro-geo-translations',
        KLARO_GEO_URL . 'css/klaro-geo-translations.css',
        array('jquery-ui-style'),
        KLARO_GEO_VERSION
    );

    // 2. Register and enqueue the main admin script
    wp_register_script(
        'klaro-geo-admin-js',
        KLARO_GEO_URL . 'js/klaro-geo-admin.js',
        array('jquery', 'jquery-ui-tabs'),
        KLARO_GEO_VERSION,
        true
    );

    // Get templates and countries for admin script
    $templates = get_option('klaro_geo_templates', array());
    $visible_countries = get_option('klaro_geo_visible_countries', array());

    // Localize the script with necessary data
    wp_localize_script('klaro-geo-admin-js', 'klaroGeoAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('klaro_geo_nonce'),
        'templates' => $templates,
        'visibleCountries' => $visible_countries,
        'version' => KLARO_GEO_VERSION
    ));

    // Make templates available globally for backward compatibility
    wp_add_inline_script('klaro-geo-admin-js', 'window.klaroTemplates = ' . wp_json_encode($templates) . ';', 'before');

    wp_enqueue_script('klaro-geo-admin-js');

    // 3. Page-specific scripts

    // Services page
    if (strpos($hook, 'klaro-geo-services') !== false) {
        // Get available purposes from settings
        $purposes = explode(',', get_option('klaro_geo_purposes', 'functional,analytics,advertising'));
        $services = klaro_geo_validate_services();

        // Register and enqueue the service translations script
        wp_register_script(
            'klaro-geo-service-translations-js',
            KLARO_GEO_URL . 'js/klaro-geo-admin-services.js',
            array('jquery', 'jquery-ui-tabs', 'klaro-geo-admin-js'),
            KLARO_GEO_VERSION,
            true
        );

        // Localize the services script with all the data needed
        wp_localize_script(
            'klaro-geo-service-translations-js',
            'klaroGeoServices',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('klaro_geo_nonce'),
                'purposes' => $purposes,
                'services' => $services ? $services : [],
                'templates' => $templates,
                'version' => KLARO_GEO_VERSION
            )
        );

        // Make sure we have a global ajaxurl variable for backward compatibility
        wp_add_inline_script('klaro-geo-service-translations-js', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');

        wp_enqueue_script('klaro-geo-service-translations-js');
        klaro_geo_debug_log('Service translations script loaded');
    }

    // Templates page
    if (strpos($hook, 'klaro-geo-templates') !== false) {
        // Register and enqueue the templates script
        wp_register_script(
            'klaro-geo-templates-js',
            KLARO_GEO_URL . 'js/klaro-geo-admin-templates.js',
            array('jquery', 'jquery-ui-tabs', 'klaro-geo-admin-js'),
            KLARO_GEO_VERSION,
            true
        );

        wp_enqueue_script('klaro-geo-templates-js');
        klaro_geo_debug_log('Templates script loaded');
    }
}
add_action('admin_enqueue_scripts', 'klaro_geo_admin_scripts');