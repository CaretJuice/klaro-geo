<?php
/**
 * Klaro Consent Button functionality
 */

// Exit if accessed directly
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Klaro Consent Button to WordPress menu
 */
class Klaro_Consent_Menu_Button {
    /**
     * Initialize the class
     */
    public static function init() {
        // Register settings for the floating button
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        // Add frontend script to handle the open-klaro-modal class
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_scripts'));

        // Add admin notice about how to add the consent button to menus
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('klaro_geo_settings_group', 'klaro_geo_enable_floating_button', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));

        register_setting('klaro_geo_settings_group', 'klaro_geo_button_text', array(
            'type' => 'string',
            'default' => 'Manage Consent Settings',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('klaro_geo_settings_group', 'klaro_geo_button_theme', array(
            'type' => 'string',
            'default' => 'light',
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    /**
     * Enqueue frontend scripts
     */
    public static function enqueue_frontend_scripts() {
        // Enqueue the script
        wp_enqueue_script(
            'klaro-consent-button-js',
            plugins_url('/js/klaro-consent-button.js', dirname(__FILE__)),
            array('jquery'),
            '1.0',
            true
        );
    }

    /**
     * Display admin notice on the Menus page
     */
    public static function admin_notice() {
        $screen = get_current_screen();

        // Only show on the nav-menus.php page
        if (!$screen || $screen->id !== 'nav-menus') {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible">
            <h3>Adding a Klaro Consent Button to Your Menu</h3>
            <p>You can add a custom link to your WordPress menu that triggers the Klaro consent modal:</p>
            <ol>
                <li>In the "Custom Links" section to the left, add a link with:
                    <ul>
                        <li><strong>URL:</strong> # (just a hash symbol)</li>
                        <li><strong>Link Text:</strong> "Manage Cookies" or whatever text you prefer</li>
                    </ul>
                </li>
                <li>Click "Add to Menu"</li>
                <li>Expand the newly added menu item</li>
                <li>In the "CSS Classes (optional)" field, add the class <code>open-klaro-modal</code></li>
                <li>Save the menu</li>
            </ol>
            <p>The link will now open the Klaro consent modal when clicked.</p>
        </div>
        <?php
    }
}

// Initialize the class
Klaro_Consent_Menu_Button::init();