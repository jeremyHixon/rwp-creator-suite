<?php
/**
 * Class Shortcodes
 * Handles custom shortcodes for the plugin.
 */
class Shortcodes {
    /**
     * Registers all shortcodes.
     */
    public static function register_shortcodes() {
        add_shortcode('rwp-info', [self::class, 'rwp_info_shortcode']);
    }

    /**
     * Placeholder shortcode callback.
     *
     * @param array $atts Shortcode attributes.
     * @return string Shortcode output.
     */
    public static function rwp_info_shortcode($atts = []) {
        $atts = shortcode_atts([
            'key' => 'name'
        ], $atts);

        switch ($atts['key']) {
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
add_action('init', ['Shortcodes', 'register_shortcodes']);
