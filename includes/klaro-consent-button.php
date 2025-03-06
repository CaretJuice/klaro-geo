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
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        // Add custom menu item
        add_action('admin_head-nav-menus.php', array(__CLASS__, 'add_meta_box'));
        add_filter('wp_setup_nav_menu_item', array(__CLASS__, 'setup_consent_button_item'));
        add_filter('wp_nav_menu_objects', array(__CLASS__, 'filter_menu_items'));
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
     * Add meta box to the menu editor
     */
    public static function add_meta_box() {
        add_meta_box(
            'klaro-consent-button-meta-box',
            __('Klaro Consent Button', 'klaro-geo'),
            array(__CLASS__, 'display_meta_box'),
            'nav-menus',
            'side',
            'low'
        );
    }

    /**
     * Display the meta box content
     */
    public static function display_meta_box() {
        ?>
        <div class="klaro-consent-button-wrap">
            <p><?php _e('Add a button to open the Klaro consent management popup.', 'klaro-geo'); ?></p>
            <p>
                <input type="submit" class="button-secondary" name="add-klaro-consent-button-menu-item"
                       value="<?php _e('Add Consent Button', 'klaro-geo'); ?>" />
                <span class="spinner"></span>
            </p>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.klaro-consent-button-wrap').on('click', '.button-secondary', function(e) {
                    e.preventDefault();

                    // Show spinner
                    $(this).siblings('.spinner').addClass('is-active');

                    // Add menu item
                    wpNavMenu.addLinkToMenu('<?php echo esc_js(get_option('klaro_geo_button_text', 'Manage Consent Settings')); ?>',
                                           '#klaro-consent',
                                           'klaro-consent-button',
                                           $('#menu').val());

                    // Hide spinner
                    $(this).siblings('.spinner').removeClass('is-active');
                });
            });
        </script>
        <?php
    }

    /**
     * Setup the consent button menu item
     */
    public static function setup_consent_button_item($menu_item) {
        if (isset($menu_item->url) && $menu_item->url === '#klaro-consent') {
            $menu_item->type = 'klaro_consent';
            $menu_item->object = 'klaro_consent';
            $menu_item->classes[] = 'klaro-menu-item';
        }
        return $menu_item;
    }

    /**
     * Filter menu items to add the consent button class
     */
    public static function filter_menu_items($menu_items) {
        foreach ($menu_items as $key => $menu_item) {
            if ($menu_item->object === 'klaro_consent') {
                // Add the consent button class to the link
                $menu_items[$key]->classes[] = 'klaro-menu-item';

                // Replace the URL with a hash
                $menu_items[$key]->url = '#';

                // Add the consent button class to the link
                add_filter('nav_menu_link_attributes', function($atts, $item) use ($menu_item) {
                    if ($item->ID == $menu_item->ID) {
                        $atts['class'] = isset($atts['class']) ? $atts['class'] . ' klaro-menu-consent-button' : 'klaro-menu-consent-button';
                    }
                    return $atts;
                }, 10, 2);
            }
        }
        return $menu_items;
    }
}

// Initialize the class
Klaro_Consent_Menu_Button::init();