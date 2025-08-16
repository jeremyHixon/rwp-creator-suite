<?php
/**
 * Network Utilities
 * 
 * Provides shared network-related utility functions for the RWP Creator Suite plugin.
 * 
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Network_Utils {

    /**
     * Get client IP address with support for various proxy configurations.
     * 
     * This method checks multiple headers that might contain the real client IP
     * address, prioritizing public IP addresses over private/reserved ones.
     * 
     * @return string The client IP address or fallback IP if not found.
     */
    public static function get_client_ip() {
        // Check for various headers that might contain the real IP
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ( $ip_headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                // Handle comma-separated list of IPs (first one is usually the real client)
                $ip_list = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ip_list[0] );
                
                // Validate IP and exclude private/reserved ranges
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR even if it's private/reserved
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
        
        // Final fallback
        return '127.0.0.1';
    }
    
    /**
     * Get a hash of the client IP for anonymous tracking purposes.
     * 
     * @param string $salt Optional salt for the hash.
     * @return string SHA256 hash of the client IP.
     */
    public static function get_client_ip_hash( $salt = '' ) {
        $ip = self::get_client_ip();
        $salt = $salt ?: wp_salt( 'secure_auth' );
        return hash( 'sha256', $ip . $salt );
    }
    
    /**
     * Validate if an IP address is from a trusted proxy.
     * 
     * @param string $ip The IP address to check.
     * @return bool True if the IP is from a trusted proxy.
     */
    public static function is_trusted_proxy( $ip ) {
        // Define trusted proxy IP ranges (can be configured via filter)
        $trusted_ranges = apply_filters( 'rwp_creator_suite_trusted_proxy_ranges', array(
            // Cloudflare IP ranges (example - should be updated with current ranges)
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '104.16.0.0/13',
            '104.24.0.0/14',
        ) );
        
        foreach ( $trusted_ranges as $range ) {
            if ( self::ip_in_range( $ip, $range ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an IP address is within a CIDR range.
     * 
     * @param string $ip The IP address to check.
     * @param string $range The CIDR range (e.g., '192.168.1.0/24').
     * @return bool True if the IP is within the range.
     */
    public static function ip_in_range( $ip, $range ) {
        if ( strpos( $range, '/' ) === false ) {
            return $ip === $range;
        }
        
        list( $subnet, $bits ) = explode( '/', $range );
        
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            // IPv4
            $ip_long = ip2long( $ip );
            $subnet_long = ip2long( $subnet );
            $mask = -1 << ( 32 - (int) $bits );
            
            return ( $ip_long & $mask ) === ( $subnet_long & $mask );
        } elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            // IPv6 - Basic implementation
            $ip_bin = inet_pton( $ip );
            $subnet_bin = inet_pton( $subnet );
            
            if ( $ip_bin === false || $subnet_bin === false ) {
                return false;
            }
            
            $bytes = (int) $bits >> 3; // Number of full bytes
            $remaining_bits = (int) $bits & 7; // Remaining bits
            
            // Compare full bytes
            if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
                return false;
            }
            
            // Compare remaining bits
            if ( $remaining_bits > 0 ) {
                $mask = 0xFF << ( 8 - $remaining_bits );
                $ip_byte = ord( $ip_bin[ $bytes ] ?? "\0" );
                $subnet_byte = ord( $subnet_bin[ $bytes ] ?? "\0" );
                
                return ( $ip_byte & $mask ) === ( $subnet_byte & $mask );
            }
            
            return true;
        }
        
        return false;
    }
}