<?php
/**
 * Klaro Consent Button functionality
 */

// Exit if accessed directly
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Klaro Consent Button to WordPress menu and provide shortcode
 */
class Klaro_Consent_Menu_Button {
    /**
     * Initialize the class
     */
    public static function init() {
        // Register settings for the floating button
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        // Add admin notice about how to add the consent button to menus
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));

        // Register shortcode for adding consent button anywhere
        add_shortcode('klaro_consent_button', array(__CLASS__, 'consent_button_shortcode'));
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

        register_setting('klaro_geo_settings_group', 'klaro_geo_floating_button_text', array(
            'type' => 'string',
            'default' => 'Manage Consent Settings',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('klaro_geo_settings_group', 'klaro_geo_floating_button_theme', array(
            'type' => 'string',
            'default' => 'light',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('klaro_geo_settings_group', 'klaro_geo_floating_button_position', array(
            'type' => 'string',
            'default' => 'bottom-right',
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    // Script enqueuing is now handled in the main plugin file

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

    /**
     * Shortcode for adding a consent button anywhere
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function consent_button_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'text' => get_option('klaro_geo_floating_button_text', 'Manage Consent Settings'),
            'class' => '',
            'style' => 'button', // 'button' or 'link'
        ), $atts);

        // Sanitize attributes
        $text = esc_html($atts['text']);
        $class = sanitize_html_class($atts['class']);
        $style = in_array($atts['style'], array('button', 'link')) ? $atts['style'] : 'button';

        // Build the HTML
        if ($style === 'link') {
            $html = sprintf(
                '<a href="#" class="open-klaro-modal klaro-consent-link %s">%s</a>',
                $class,
                $text
            );
        } else {
            $html = sprintf(
                '<button type="button" class="open-klaro-modal klaro-consent-button %s">%s</button>',
                $class,
                $text
            );
        }

        return $html;
    }
}

// Initialize the class
Klaro_Consent_Menu_Button::init();