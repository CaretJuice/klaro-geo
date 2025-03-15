<?php
/**
 * Klaro Geo Consent Button
 *
 * @package Klaro_Geo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Klaro_Geo_Consent_Button
 *
 * Handles the floating consent button functionality
 */
class Klaro_Geo_Consent_Button {

    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode( 'klaro_consent_button', array( __CLASS__, 'consent_button_shortcode' ) );
    }

    /**
     * Shortcode for adding a consent button anywhere
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function consent_button_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'text'  => get_option( 'klaro_geo_floating_button_text', __( 'Manage Consent', 'klaro-geo' ) ),
                'class' => '',
                'style' => 'button', // button or link
            ),
            $atts,
            'klaro_consent_button'
        );

        $classes = 'open-klaro-modal';
        if ( ! empty( $atts['class'] ) ) {
            $classes .= ' ' . esc_attr( $atts['class'] );
        }

        if ( $atts['style'] === 'link' ) {
            return sprintf(
                '<a href="#" class="%s">%s</a>',
                esc_attr( $classes ),
                esc_html( $atts['text'] )
            );
        } else {
            return sprintf(
                '<button type="button" class="%s">%s</button>',
                esc_attr( $classes ),
                esc_html( $atts['text'] )
            );
        }
    }
}

// Initialize the class
Klaro_Geo_Consent_Button::init();