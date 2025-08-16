<?php
/**
 * API Validation Trait
 * 
 * Provides common validation methods for REST API endpoints.
 * Eliminates code duplication across Caption Writer and Content Repurposer APIs.
 */

defined( 'ABSPATH' ) || exit;

trait RWP_Creator_Suite_API_Validation_Trait {

    /**
     * Verify nonce permission for API requests.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function verify_nonce_permission( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        
        if ( empty( $nonce ) ) {
            return new WP_Error(
                'missing_nonce',
                __( 'Missing security token.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }

        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid security token.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check if user is logged in.
     *
     * @return bool|WP_Error
     */
    public function check_user_logged_in() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'not_logged_in',
                __( 'You must be logged in to access this resource.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Validate and sanitize description input.
     *
     * @param string $description Description text.
     * @return string|WP_Error
     */
    public function sanitize_description( $description ) {
        if ( empty( $description ) ) {
            return new WP_Error(
                'empty_description',
                __( 'Description cannot be empty.', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }

        // Remove HTML tags and sanitize
        $sanitized = wp_strip_all_tags( $description );
        $sanitized = sanitize_textarea_field( $sanitized );

        return $sanitized;
    }

    /**
     * Validate description length and content.
     *
     * @param string $description Description text.
     * @return bool|WP_Error
     */
    public function validate_description( $description ) {
        if ( empty( $description ) ) {
            return new WP_Error(
                'empty_description',
                __( 'Description is required.', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }

        $min_length = apply_filters( 'rwp_creator_suite_description_min_length', 10 );
        $max_length = apply_filters( 'rwp_creator_suite_description_max_length', 2000 );

        if ( mb_strlen( $description ) < $min_length ) {
            return new WP_Error(
                'description_too_short',
                sprintf(
                    __( 'Description must be at least %d characters long.', 'rwp-creator-suite' ),
                    $min_length
                ),
                array( 'status' => 400 )
            );
        }

        if ( mb_strlen( $description ) > $max_length ) {
            return new WP_Error(
                'description_too_long',
                sprintf(
                    __( 'Description must be no more than %d characters long.', 'rwp-creator-suite' ),
                    $max_length
                ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Sanitize platforms array.
     *
     * @param array $platforms Array of platform names.
     * @return array
     */
    public function sanitize_platforms( $platforms ) {
        if ( ! is_array( $platforms ) ) {
            return array();
        }

        $valid_platforms = array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' );
        $sanitized = array();

        foreach ( $platforms as $platform ) {
            $platform = sanitize_text_field( $platform );
            if ( in_array( $platform, $valid_platforms, true ) ) {
                $sanitized[] = $platform;
            }
        }

        return array_unique( $sanitized );
    }

    /**
     * Validate platforms array.
     *
     * @param array $platforms Array of platform names.
     * @return bool|WP_Error
     */
    public function validate_platforms( $platforms ) {
        if ( ! is_array( $platforms ) ) {
            return new WP_Error(
                'invalid_platforms',
                __( 'Platforms must be an array.', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }

        if ( empty( $platforms ) ) {
            return new WP_Error(
                'no_platforms',
                __( 'At least one platform must be specified.', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }

        $max_platforms = apply_filters( 'rwp_creator_suite_max_platforms', 5 );
        if ( count( $platforms ) > $max_platforms ) {
            return new WP_Error(
                'too_many_platforms',
                sprintf(
                    __( 'Maximum of %d platforms allowed.', 'rwp-creator-suite' ),
                    $max_platforms
                ),
                array( 'status' => 400 )
            );
        }

        $valid_platforms = array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' );
        foreach ( $platforms as $platform ) {
            if ( ! in_array( $platform, $valid_platforms, true ) ) {
                return new WP_Error(
                    'invalid_platform',
                    sprintf(
                        __( 'Invalid platform: %s. Valid platforms are: %s', 'rwp-creator-suite' ),
                        $platform,
                        implode( ', ', $valid_platforms )
                    ),
                    array( 'status' => 400 )
                );
            }
        }

        return true;
    }

    /**
     * Validate content input for repurposing.
     *
     * @param string $content Content to repurpose.
     * @return bool|WP_Error
     */
    public function validate_content( $content ) {
        if ( empty( $content ) ) {
            return new WP_Error(
                'empty_content',
                __( 'Content is required.', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }

        $min_length = apply_filters( 'rwp_creator_suite_content_min_length', 20 );
        $max_length = apply_filters( 'rwp_creator_suite_content_max_length', 10000 );

        if ( mb_strlen( $content ) < $min_length ) {
            return new WP_Error(
                'content_too_short',
                sprintf(
                    __( 'Content must be at least %d characters long.', 'rwp-creator-suite' ),
                    $min_length
                ),
                array( 'status' => 400 )
            );
        }

        if ( mb_strlen( $content ) > $max_length ) {
            return new WP_Error(
                'content_too_long',
                sprintf(
                    __( 'Content must be no more than %d characters long.', 'rwp-creator-suite' ),
                    $max_length
                ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Sanitize content for repurposing.
     *
     * @param string $content Content to sanitize.
     * @return string
     */
    public function sanitize_content( $content ) {
        // Allow some HTML tags but sanitize
        $allowed_tags = array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'em' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'a' => array( 'href' => array() ),
        );

        return wp_kses( $content, $allowed_tags );
    }

    /**
     * Validate tone parameter.
     *
     * @param string $tone Tone value.
     * @return bool|WP_Error
     */
    public function validate_tone( $tone ) {
        $valid_tones = apply_filters( 'rwp_creator_suite_valid_tones', array(
            'professional', 'casual', 'witty', 'inspirational', 'question', 'engaging', 'informative'
        ) );

        if ( ! in_array( $tone, $valid_tones, true ) ) {
            return new WP_Error(
                'invalid_tone',
                sprintf(
                    __( 'Invalid tone: %s. Valid tones are: %s', 'rwp-creator-suite' ),
                    $tone,
                    implode( ', ', $valid_tones )
                ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Create standardized success response.
     *
     * @param mixed  $data Response data.
     * @param string $message Optional success message.
     * @param array  $meta Optional metadata.
     * @return WP_REST_Response
     */
    public function success_response( $data, $message = '', $meta = array() ) {
        $response = array(
            'success' => true,
            'data' => $data,
        );

        if ( ! empty( $message ) ) {
            $response['message'] = $message;
        }

        if ( ! empty( $meta ) ) {
            $response['meta'] = $meta;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Create standardized error response.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @param mixed  $data Optional error data.
     * @param int    $status HTTP status code.
     * @return WP_Error
     */
    public function error_response( $code, $message, $data = null, $status = 400 ) {
        $error_data = array( 'status' => $status );
        
        if ( null !== $data ) {
            $error_data['details'] = $data;
        }

        return new WP_Error( $code, $message, $error_data );
    }

    /**
     * Validate API request size limits.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function validate_request_size( $request ) {
        $max_size = apply_filters( 'rwp_creator_suite_max_request_size', 1048576 ); // 1MB default
        
        $content_length = $request->get_header( 'content-length' );
        if ( $content_length && $content_length > $max_size ) {
            return new WP_Error(
                'request_too_large',
                sprintf(
                    __( 'Request size exceeds maximum allowed size of %s.', 'rwp-creator-suite' ),
                    size_format( $max_size )
                ),
                array( 'status' => 413 )
            );
        }

        return true;
    }

    /**
     * Sanitize and validate user input array.
     *
     * @param array  $input Input array.
     * @param array  $allowed_keys Allowed keys.
     * @param string $context Context for error messages.
     * @return array|WP_Error
     */
    public function sanitize_input_array( $input, $allowed_keys, $context = 'input' ) {
        if ( ! is_array( $input ) ) {
            return new WP_Error(
                'invalid_input',
                sprintf( __( '%s must be an array.', 'rwp-creator-suite' ), ucfirst( $context ) ),
                array( 'status' => 400 )
            );
        }

        $sanitized = array();
        foreach ( $input as $key => $value ) {
            $key = sanitize_key( $key );
            
            if ( ! in_array( $key, $allowed_keys, true ) ) {
                continue;
            }

            if ( is_string( $value ) ) {
                $sanitized[ $key ] = sanitize_text_field( $value );
            } elseif ( is_array( $value ) ) {
                $sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
            } else {
                $sanitized[ $key ] = $value;
            }
        }

        return $sanitized;
    }
}