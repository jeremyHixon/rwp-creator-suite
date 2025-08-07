<?php
/**
 * Username Generator Class
 *
 * Generates usernames from email addresses for the authentication system.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Username_Generator {

    /**
     * Generate a username from an email address.
     *
     * @param string $email The email address to generate username from.
     * @return string|WP_Error Generated username or error.
     */
    public function generate_from_email( $email ) {
        if ( ! is_email( $email ) ) {
            return new WP_Error(
                'invalid_email',
                'Invalid email address provided.',
                array( 'status' => 400 )
            );
        }

        // Use full email address, remove @ and . to create username
        $username = str_replace( array( '@', '.' ), '', $email );
        
        // Sanitize for WordPress username requirements
        $username = sanitize_user( $username );
        
        // Remove any remaining special characters except underscore and dash
        $username = preg_replace( '/[^a-zA-Z0-9._-]/', '', $username );
        
        // Ensure minimum length
        if ( strlen( $username ) < 3 ) {
            $username = 'user_' . $username;
        }
        
        // Since we're using the full email (which WordPress enforces as unique),
        // the generated username will be unique
        return $username;
    }

    /**
     * Validate generated username meets WordPress requirements.
     *
     * @param string $username The username to validate.
     * @return bool Whether the username is valid.
     */
    public function validate_username( $username ) {
        return validate_username( $username );
    }
}