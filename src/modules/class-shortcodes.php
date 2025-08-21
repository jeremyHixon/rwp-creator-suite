<?php
/**
 * Class RWP_Creator_Suite_Shortcodes
 * Handles custom shortcodes for the plugin.
 *
 * @package    RWP_Creator_Suite
 * @subpackage RWP_Creator_Suite/modules
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Shortcodes {
    /**
     * Registers all shortcodes.
     *
     * @since 1.0.0
     */
    public static function register_shortcodes() {
        add_shortcode( 'rwp-info', array( self::class, 'rwp_info_shortcode' ) );
    }

    /**
     * Placeholder shortcode callback.
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes.
     * @return string Shortcode output.
     */
    public static function rwp_info_shortcode( $atts = array() ) {
        $atts = shortcode_atts( array(
            'key' => 'name'
        ), $atts );

        switch ( $atts['key'] ) {
            case 'name':
                return get_bloginfo( 'name' );
            case 'year':
                return date( 'Y' );
            default:
                return '';
        }
    }
}

// Register shortcodes on init
add_action( 'init', array( 'RWP_Creator_Suite_Shortcodes', 'register_shortcodes' ) );
