<?php
/**
 * PHP Compatibility Functions
 * 
 * Provides polyfills for PHP 8.0+ functions to maintain compatibility with older PHP versions.
 * 
 * @package RWP_Creator_Suite
 * @since   1.6.0
 */

defined( 'ABSPATH' ) || exit;

// Polyfill for str_contains (PHP 8.0+)
if ( ! function_exists( 'str_contains' ) ) {
    /**
     * Determine if a string contains a given substring.
     *
     * @param string $haystack The string to search in.
     * @param string $needle The string to search for.
     * @return bool
     */
    function str_contains( $haystack, $needle ) {
        if ( $needle === '' ) {
            return true;
        }
        
        if ( $haystack === null || $needle === null ) {
            return false;
        }
        
        return strpos( $haystack, $needle ) !== false;
    }
}

// Polyfill for str_starts_with (PHP 8.0+)
if ( ! function_exists( 'str_starts_with' ) ) {
    /**
     * Checks if a string starts with a given substring.
     *
     * @param string $haystack The string to search in.
     * @param string $needle The string to search for.
     * @return bool
     */
    function str_starts_with( $haystack, $needle ) {
        if ( $needle === '' ) {
            return true;
        }
        
        if ( $haystack === null || $needle === null ) {
            return false;
        }
        
        return strpos( $haystack, $needle ) === 0;
    }
}

// Polyfill for str_ends_with (PHP 8.0+)
if ( ! function_exists( 'str_ends_with' ) ) {
    /**
     * Checks if a string ends with a given substring.
     *
     * @param string $haystack The string to search in.
     * @param string $needle The string to search for.
     * @return bool
     */
    function str_ends_with( $haystack, $needle ) {
        if ( $needle === '' ) {
            return true;
        }
        
        if ( $haystack === null || $needle === null ) {
            return false;
        }
        
        $length = strlen( $needle );
        
        return substr( $haystack, -$length ) === $needle;
    }
}