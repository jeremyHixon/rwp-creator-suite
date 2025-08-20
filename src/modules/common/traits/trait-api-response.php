<?php
/**
 * API Response Trait
 * 
 * Provides standardized response methods for REST API endpoints.
 * Ensures consistent response formats across all Creator Suite APIs.
 */

defined( 'ABSPATH' ) || exit;

trait RWP_Creator_Suite_API_Response_Trait {

    /**
     * Create standardized success response.
     *
     * @param mixed  $data Response data.
     * @param string $message Optional success message.
     * @param array  $meta Optional metadata.
     * @return WP_REST_Response
     */
    protected function success_response( $data, $message = '', $meta = array() ) {
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
     * @param int    $status HTTP status code.
     * @param mixed  $data Optional error data.
     * @return WP_Error
     */
    protected function error_response( $code, $message, $status = 400, $data = null ) {
        $error_data = array( 'status' => $status );
        
        if ( null !== $data ) {
            $error_data['details'] = $data;
        }

        return new WP_Error( $code, $message, $error_data );
    }

    /**
     * Handle WP_Error objects consistently.
     *
     * @param WP_Error $error The error object.
     * @return WP_REST_Response
     */
    protected function handle_error_response( $error ) {
        $status = $error->get_error_data();
        if ( is_array( $status ) && isset( $status['status'] ) ) {
            $status_code = $status['status'];
        } else {
            $status_code = 400;
        }

        return new WP_REST_Response(
            array(
                'success' => false,
                'error' => array(
                    'code' => $error->get_error_code(),
                    'message' => $error->get_error_message(),
                    'data' => $error->get_error_data(),
                ),
            ),
            $status_code
        );
    }

    /**
     * Validate and format API response data.
     *
     * @param mixed $data The data to validate.
     * @return mixed Validated data.
     */
    protected function validate_response_data( $data ) {
        // Ensure data is JSON serializable
        if ( is_resource( $data ) ) {
            return null;
        }

        // Convert objects to arrays for consistency
        if ( is_object( $data ) && ! ( $data instanceof stdClass ) ) {
            return (array) $data;
        }

        return $data;
    }
}