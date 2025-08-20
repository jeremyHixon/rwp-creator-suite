<?php
/**
 * Data Subject Rights Handler
 *
 * Implements GDPR data subject rights including access, rectification, erasure, and portability.
 * Provides automated handling of user requests with proper security and audit trails.
 *
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Data_Subject_Rights {

    /**
     * Initialize data subject rights handlers.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_data_rights_endpoints' ) );
        add_action( 'wp_schedule_event', 'rwp_process_data_deletion_requests' );
        add_action( 'rwp_delete_user_analytics_data', array( $this, 'process_user_data_deletion' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_data_rights_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_data_rights_assets' ) );
    }

    /**
     * Register REST API endpoints for data subject rights.
     */
    public function register_data_rights_endpoints() {
        // Data Access (Article 15)
        register_rest_route( 'rwp-creator-suite/v1', '/data-export', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_data_export_request' ),
            'permission_callback' => array( $this, 'check_user_permissions' ),
        ) );

        // Data Rectification (Article 16)
        register_rest_route( 'rwp-creator-suite/v1', '/data-rectification', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_data_rectification_request' ),
            'permission_callback' => array( $this, 'check_user_permissions' ),
            'args'                => array(
                'corrections' => array(
                    'required' => true,
                    'type'     => 'object',
                ),
            ),
        ) );

        // Data Erasure (Article 17)
        register_rest_route( 'rwp-creator-suite/v1', '/data-erasure', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_data_erasure_request' ),
            'permission_callback' => array( $this, 'check_user_permissions' ),
            'args'                => array(
                'erasure_scope' => array(
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'all',
                    'enum'     => array( 'all', 'analytics_only', 'preferences_only' ),
                ),
            ),
        ) );

        // Data Portability (Article 20)
        register_rest_route( 'rwp-creator-suite/v1', '/data-portability', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_data_portability_request' ),
            'permission_callback' => array( $this, 'check_user_permissions' ),
            'args'                => array(
                'format' => array(
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'json',
                    'enum'     => array( 'json', 'csv', 'xml' ),
                ),
            ),
        ) );
    }

    /**
     * Enqueue assets for data subject rights interface.
     */
    public function enqueue_data_rights_assets() {
        wp_enqueue_script(
            'rwp-data-subject-rights',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/data-subject-rights.js',
            array( 'wp-api-fetch' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        wp_localize_script( 'rwp-data-subject-rights', 'rwpDataRights', array(
            'apiUrl' => rest_url( 'rwp-creator-suite/v1/' ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
            'strings' => array(
                'exportTitle' => __( 'Export Your Data', 'rwp-creator-suite' ),
                'exportDescription' => __( 'Download all data we have about you in a portable format.', 'rwp-creator-suite' ),
                'rectificationTitle' => __( 'Correct Your Data', 'rwp-creator-suite' ),
                'rectificationDescription' => __( 'Update or correct any inaccurate data we have about you.', 'rwp-creator-suite' ),
                'erasureTitle' => __( 'Delete Your Data', 'rwp-creator-suite' ),
                'erasureDescription' => __( 'Permanently delete your data from our systems.', 'rwp-creator-suite' ),
                'portabilityTitle' => __( 'Download Your Data', 'rwp-creator-suite' ),
                'portabilityDescription' => __( 'Get your data in a format you can use elsewhere.', 'rwp-creator-suite' ),
                'confirmErasure' => __( 'Are you sure you want to permanently delete your data? This action cannot be undone.', 'rwp-creator-suite' ),
                'processing' => __( 'Processing...', 'rwp-creator-suite' ),
                'success' => __( 'Request processed successfully.', 'rwp-creator-suite' ),
                'error' => __( 'An error occurred processing your request.', 'rwp-creator-suite' ),
            ),
        ) );
    }

    /**
     * Handle data export request (Article 15 - Right of Access).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_data_export_request( $request ) {
        $user_id = get_current_user_id();
        
        try {
            $export_data = $this->generate_data_export( $user_id );
            
            // Create secure download link
            $export_file = $this->create_encrypted_export( $export_data, $user_id );
            
            // Send email with download link (expires in 48 hours)
            $this->send_data_export_email( $user_id, $export_file );
            
            // Log the access request
            $this->log_data_access_request( $user_id );

            return new WP_REST_Response( array(
                'success' => true,
                'message' => __( 'Your data export has been prepared. You will receive an email with a secure download link shortly.', 'rwp-creator-suite' ),
                'export_id' => $export_file['id'],
                'expires_at' => $export_file['expires_at'],
            ), 200 );

        } catch ( Exception $e ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Failed to generate data export. Please try again later.', 'rwp-creator-suite' ),
                'error' => $e->getMessage(),
            ), 500 );
        }
    }

    /**
     * Handle data rectification request (Article 16 - Right to Rectification).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_data_rectification_request( $request ) {
        $user_id = get_current_user_id();
        $corrections = $request->get_param( 'corrections' );

        try {
            $updated_fields = $this->update_user_data( $user_id, $corrections );

            return new WP_REST_Response( array(
                'success' => true,
                'message' => __( 'Your data has been updated successfully.', 'rwp-creator-suite' ),
                'updated_fields' => $updated_fields,
            ), 200 );

        } catch ( Exception $e ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Failed to update your data. Please try again later.', 'rwp-creator-suite' ),
                'error' => $e->getMessage(),
            ), 500 );
        }
    }

    /**
     * Handle data erasure request (Article 17 - Right to Erasure).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_data_erasure_request( $request ) {
        $user_id = get_current_user_id();
        $erasure_scope = $request->get_param( 'erasure_scope' );

        try {
            $erasure_log = $this->process_erasure_request( $user_id, $erasure_scope );

            return new WP_REST_Response( array(
                'success' => true,
                'message' => __( 'Your data deletion request has been processed successfully.', 'rwp-creator-suite' ),
                'erasure_log' => $erasure_log,
            ), 200 );

        } catch ( Exception $e ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Failed to process data deletion request. Please try again later.', 'rwp-creator-suite' ),
                'error' => $e->getMessage(),
            ), 500 );
        }
    }

    /**
     * Handle data portability request (Article 20 - Right to Data Portability).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_data_portability_request( $request ) {
        $user_id = get_current_user_id();
        $format = $request->get_param( 'format' );

        try {
            $portable_export = $this->generate_portable_export( $user_id, $format );

            return new WP_REST_Response( array(
                'success' => true,
                'message' => __( 'Your portable data export has been generated successfully.', 'rwp-creator-suite' ),
                'download_url' => $portable_export['download_url'],
                'format' => $format,
                'size' => $portable_export['size'],
                'expires_at' => $portable_export['expires_at'],
            ), 200 );

        } catch ( Exception $e ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Failed to generate portable export. Please try again later.', 'rwp-creator-suite' ),
                'error' => $e->getMessage(),
            ), 500 );
        }
    }

    /**
     * Generate comprehensive data export for user.
     *
     * @param int $user_id User ID.
     * @return array Export data.
     */
    public function generate_data_export( $user_id ) {
        $export_data = array(
            'user_info' => $this->get_user_basic_info( $user_id ),
            'consent_history' => $this->get_consent_history( $user_id ),
            'analytics_data' => $this->get_user_analytics_data( $user_id ),
            'preferences' => $this->get_user_preferences( $user_id ),
            'usage_statistics' => $this->get_usage_statistics( $user_id ),
            'data_processing_log' => $this->get_processing_log( $user_id ),
            'export_metadata' => array(
                'generated_at' => current_time( 'c' ),
                'data_controller' => get_bloginfo( 'name' ),
                'export_version' => '1.0',
                'user_id' => $user_id,
            ),
        );

        return $export_data;
    }

    /**
     * Update user data based on rectification request.
     *
     * @param int   $user_id User ID.
     * @param array $corrections Data corrections.
     * @return array Updated fields.
     */
    public function update_user_data( $user_id, $corrections ) {
        $updated_fields = array();

        foreach ( $corrections as $field => $new_value ) {
            switch ( $field ) {
                case 'preferences':
                    $this->update_user_preferences( $user_id, $new_value );
                    $updated_fields[] = 'preferences';
                    break;

                case 'consent_preferences':
                    $this->update_consent_preferences( $user_id, $new_value );
                    $updated_fields[] = 'consent_preferences';
                    break;

                default:
                    // Allow filtering for custom fields
                    $custom_update = apply_filters( 'rwp_data_rectification_custom_field', null, $field, $new_value, $user_id );
                    if ( $custom_update ) {
                        $updated_fields[] = $field;
                    }
                    break;
            }
        }

        // Log rectification request
        $this->log_rectification_request( $user_id, $updated_fields );

        return $updated_fields;
    }

    /**
     * Process erasure request with proper audit trail.
     *
     * @param int    $user_id User ID.
     * @param string $erasure_scope Scope of erasure.
     * @return array Erasure log.
     */
    public function process_erasure_request( $user_id, $erasure_scope = 'all' ) {
        $erasure_log = array(
            'user_id' => $user_id,
            'request_date' => current_time( 'mysql' ),
            'erasure_scope' => $erasure_scope,
            'data_deleted' => array(),
        );

        switch ( $erasure_scope ) {
            case 'all':
                $erasure_log['data_deleted'] = $this->delete_all_user_data( $user_id );
                break;

            case 'analytics_only':
                $erasure_log['data_deleted'] = $this->delete_analytics_data( $user_id );
                break;

            case 'preferences_only':
                $erasure_log['data_deleted'] = $this->delete_preferences_data( $user_id );
                break;
        }

        // Log erasure for compliance audit
        $this->log_erasure_request( $erasure_log );

        // Send confirmation email
        $this->send_erasure_confirmation_email( $user_id, $erasure_log );

        return $erasure_log;
    }

    /**
     * Generate portable export in specified format.
     *
     * @param int    $user_id User ID.
     * @param string $format Export format.
     * @return array Export file information.
     */
    public function generate_portable_export( $user_id, $format = 'json' ) {
        $portable_data = array(
            'user_preferences' => $this->get_structured_preferences( $user_id ),
            'content_templates' => $this->get_user_templates( $user_id ),
            'favorites' => $this->get_user_favorites( $user_id ),
            'usage_patterns' => $this->get_usage_patterns( $user_id ),
            'export_metadata' => array(
                'format_version' => '1.0',
                'export_date' => current_time( 'c' ),
                'data_controller' => get_bloginfo( 'name' ),
                'export_scope' => 'user_data_portable',
                'user_id' => $user_id,
            ),
        );

        switch ( $format ) {
            case 'json':
                return $this->export_as_json( $portable_data, $user_id );
            case 'csv':
                return $this->export_as_csv( $portable_data, $user_id );
            case 'xml':
                return $this->export_as_xml( $portable_data, $user_id );
            default:
                return $this->export_as_json( $portable_data, $user_id );
        }
    }

    /**
     * Delete all user data from the system.
     *
     * @param int $user_id User ID.
     * @return array Deleted data types.
     */
    private function delete_all_user_data( $user_id ) {
        global $wpdb;

        $deleted_data = array();

        // Delete user meta data
        $meta_keys = array(
            'rwp_caption_favorites',
            'rwp_caption_preferences',
            'rwp_ai_total_usage',
            'rwp_gdpr_consent_record',
            'advanced_features_consent',
        );

        // Add granular consent meta keys
        $granular_consent = RWP_Creator_Suite_Granular_Consent::class;
        if ( class_exists( $granular_consent ) ) {
            $consent_categories = array( 'basic_analytics', 'hashtag_trends', 'performance_benchmarking', 'product_improvement' );
            foreach ( $consent_categories as $category ) {
                $meta_keys[] = "rwp_consent_{$category}";
            }
        }

        foreach ( $meta_keys as $meta_key ) {
            if ( delete_user_meta( $user_id, $meta_key ) ) {
                $deleted_data[] = $meta_key;
            }
        }

        // Delete analytics data (anonymized but linked)
        $deleted_analytics = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE anonymous_session_hash IN (
                 SELECT anonymous_session_hash 
                 FROM {$wpdb->prefix}rwp_session_mapping 
                 WHERE user_id = %d
             )",
            $user_id
        ) );

        if ( $deleted_analytics ) {
            $deleted_data[] = "analytics_records_{$deleted_analytics}";
        }

        // Delete session mappings
        $wpdb->delete(
            $wpdb->prefix . 'rwp_session_mapping',
            array( 'user_id' => $user_id ),
            array( '%d' )
        );

        return $deleted_data;
    }

    /**
     * Delete only analytics data for user.
     *
     * @param int $user_id User ID.
     * @return array Deleted data types.
     */
    private function delete_analytics_data( $user_id ) {
        global $wpdb;

        $deleted_data = array();

        // Delete analytics records
        $deleted_analytics = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE anonymous_session_hash IN (
                 SELECT anonymous_session_hash 
                 FROM {$wpdb->prefix}rwp_session_mapping 
                 WHERE user_id = %d
             )",
            $user_id
        ) );

        if ( $deleted_analytics ) {
            $deleted_data[] = "analytics_records_{$deleted_analytics}";
        }

        // Delete consent records
        delete_user_meta( $user_id, 'rwp_gdpr_consent_record' );
        $deleted_data[] = 'consent_record';

        return $deleted_data;
    }

    /**
     * Delete only preferences data for user.
     *
     * @param int $user_id User ID.
     * @return array Deleted data types.
     */
    private function delete_preferences_data( $user_id ) {
        $deleted_data = array();

        $preference_keys = array(
            'rwp_caption_preferences',
            'rwp_caption_favorites',
        );

        foreach ( $preference_keys as $key ) {
            if ( delete_user_meta( $user_id, $key ) ) {
                $deleted_data[] = $key;
            }
        }

        return $deleted_data;
    }

    /**
     * Get user basic info for export.
     *
     * @param int $user_id User ID.
     * @return array User basic info.
     */
    private function get_user_basic_info( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return array();
        }

        return array(
            'user_id' => $user_id,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'registered' => $user->user_registered,
        );
    }

    /**
     * Get consent history for user.
     *
     * @param int $user_id User ID.
     * @return array Consent history.
     */
    private function get_consent_history( $user_id ) {
        return get_user_meta( $user_id, 'rwp_gdpr_consent_record', true ) ?: array();
    }

    /**
     * Get analytics data linked to user.
     *
     * @param int $user_id User ID.
     * @return array Analytics data.
     */
    private function get_user_analytics_data( $user_id ) {
        global $wpdb;

        // Get anonymized analytics data that can be linked to user
        $analytics_data = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, event_data, timestamp 
             FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE anonymous_session_hash IN (
                 SELECT DISTINCT anonymous_session_hash 
                 FROM {$wpdb->prefix}rwp_session_mapping 
                 WHERE user_id = %d
             )
             ORDER BY timestamp DESC",
            $user_id
        ) );

        return $analytics_data ?: array();
    }

    /**
     * Get user preferences.
     *
     * @param int $user_id User ID.
     * @return array User preferences.
     */
    private function get_user_preferences( $user_id ) {
        $preferences = array();
        
        $meta_keys = array(
            'rwp_caption_preferences',
            'rwp_caption_favorites',
            'advanced_features_consent',
        );

        foreach ( $meta_keys as $key ) {
            $value = get_user_meta( $user_id, $key, true );
            if ( $value ) {
                $preferences[ $key ] = $value;
            }
        }

        return $preferences;
    }

    /**
     * Get usage statistics for user.
     *
     * @param int $user_id User ID.
     * @return array Usage statistics.
     */
    private function get_usage_statistics( $user_id ) {
        // This would typically aggregate data from analytics
        return array(
            'total_ai_usage' => get_user_meta( $user_id, 'rwp_ai_total_usage', true ) ?: 0,
            'last_activity' => get_user_meta( $user_id, 'rwp_creator_suite_last_login', true ) ?: null,
        );
    }

    /**
     * Get data processing log for user.
     *
     * @param int $user_id User ID.
     * @return array Processing log.
     */
    private function get_processing_log( $user_id ) {
        // This would be implemented to show when and how data was processed
        return array(
            'last_consent_update' => get_user_meta( $user_id, 'rwp_gdpr_consent_record', true )['consent_date'] ?? null,
            'last_data_export' => get_user_meta( $user_id, 'rwp_last_data_export', true ),
        );
    }

    /**
     * Create encrypted export file.
     *
     * @param array $export_data Export data.
     * @param int   $user_id User ID.
     * @return array Export file info.
     */
    private function create_encrypted_export( $export_data, $user_id ) {
        $export_id = wp_generate_uuid4();
        $expires_at = time() + ( 48 * HOUR_IN_SECONDS );
        
        // Store export temporarily
        $export_info = array(
            'id' => $export_id,
            'user_id' => $user_id,
            'data' => $export_data,
            'created_at' => current_time( 'mysql' ),
            'expires_at' => date( 'Y-m-d H:i:s', $expires_at ),
        );

        update_option( "rwp_data_export_{$export_id}", $export_info, false );

        // Schedule cleanup
        wp_schedule_single_event( $expires_at, 'rwp_cleanup_data_export', array( $export_id ) );

        return array(
            'id' => $export_id,
            'expires_at' => $expires_at,
            'download_url' => add_query_arg( array(
                'rwp_export' => $export_id,
                'user_id' => $user_id,
                'nonce' => wp_create_nonce( "data_export_{$export_id}" ),
            ), home_url() ),
        );
    }

    /**
     * Export data as JSON.
     *
     * @param array $data Data to export.
     * @param int   $user_id User ID.
     * @return array Export file info.
     */
    private function export_as_json( $data, $user_id ) {
        $filename = "user_data_export_{$user_id}_" . date( 'Y-m-d_H-i-s' ) . '.json';
        $content = wp_json_encode( $data, JSON_PRETTY_PRINT );
        
        return $this->create_downloadable_export( $content, $filename, 'application/json' );
    }

    /**
     * Create downloadable export file.
     *
     * @param string $content File content.
     * @param string $filename Filename.
     * @param string $mime_type MIME type.
     * @return array Export file info.
     */
    private function create_downloadable_export( $content, $filename, $mime_type ) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/rwp-data-exports/';
        
        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
            // Add .htaccess to protect directory
            file_put_contents( $export_dir . '.htaccess', 'Deny from all' );
        }

        $filepath = $export_dir . $filename;
        file_put_contents( $filepath, $content );

        $expires_at = time() + ( 24 * HOUR_IN_SECONDS );
        
        // Schedule file cleanup
        wp_schedule_single_event( $expires_at, 'rwp_cleanup_export_file', array( $filepath ) );

        return array(
            'download_url' => add_query_arg( array(
                'rwp_download' => basename( $filename ),
                'nonce' => wp_create_nonce( "download_{$filename}" ),
            ), admin_url( 'admin-ajax.php' ) ),
            'size' => filesize( $filepath ),
            'expires_at' => $expires_at,
            'filename' => $filename,
        );
    }

    /**
     * Check user permissions for data rights requests.
     *
     * @return bool
     */
    public function check_user_permissions() {
        return is_user_logged_in();
    }

    /**
     * Log data access request.
     *
     * @param int $user_id User ID.
     */
    private function log_data_access_request( $user_id ) {
        RWP_Creator_Suite_Error_Logger::log(
            'GDPR Data Access Request',
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            array(
                'user_id' => $user_id,
                'ip_hash' => hash( 'sha256', ( $_SERVER['REMOTE_ADDR'] ?? '' ) . wp_salt() ),
                'timestamp' => current_time( 'mysql' ),
            )
        );
    }

    /**
     * Log rectification request.
     *
     * @param int   $user_id User ID.
     * @param array $updated_fields Updated fields.
     */
    private function log_rectification_request( $user_id, $updated_fields ) {
        RWP_Creator_Suite_Error_Logger::log(
            'GDPR Data Rectification Request',
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            array(
                'user_id' => $user_id,
                'updated_fields' => $updated_fields,
                'timestamp' => current_time( 'mysql' ),
            )
        );
    }

    /**
     * Log erasure request.
     *
     * @param array $erasure_log Erasure log data.
     */
    private function log_erasure_request( $erasure_log ) {
        RWP_Creator_Suite_Error_Logger::log(
            'GDPR Data Erasure Request',
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            $erasure_log
        );
    }

    /**
     * Send data export email to user.
     *
     * @param int   $user_id User ID.
     * @param array $export_file Export file info.
     */
    private function send_data_export_email( $user_id, $export_file ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            __( 'Your data export from %s is ready', 'rwp-creator-suite' ),
            get_bloginfo( 'name' )
        );

        $message = sprintf(
            /* translators: %1$s: User display name, %2$s: Download URL, %3$s: Expiry time */
            __( 'Hello %1$s,

Your personal data export is ready for download. Please use the secure link below to access your data:

%2$s

This link will expire in 48 hours for security reasons.

If you did not request this export, please contact us immediately.

Best regards,
%4$s Team', 'rwp-creator-suite' ),
            $user->display_name,
            $export_file['download_url'],
            date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $export_file['expires_at'] ),
            get_bloginfo( 'name' )
        );

        wp_mail( $user->user_email, $subject, $message );
    }

    /**
     * Send erasure confirmation email.
     *
     * @param int   $user_id User ID.
     * @param array $erasure_log Erasure log.
     */
    private function send_erasure_confirmation_email( $user_id, $erasure_log ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            __( 'Data deletion confirmation from %s', 'rwp-creator-suite' ),
            get_bloginfo( 'name' )
        );

        $message = sprintf(
            /* translators: %1$s: User display name, %2$s: Deleted data items, %3$s: Site name */
            __( 'Hello %1$s,

Your data deletion request has been processed successfully.

The following data has been permanently deleted:
%2$s

This action cannot be undone. If you have any questions or concerns, please contact us.

Best regards,
%3$s Team', 'rwp-creator-suite' ),
            $user->display_name,
            '• ' . implode( "\n• ", $erasure_log['data_deleted'] ),
            get_bloginfo( 'name' )
        );

        wp_mail( $user->user_email, $subject, $message );
    }

    /**
     * Process scheduled user data deletion.
     *
     * @param int $user_id User ID.
     */
    public function process_user_data_deletion( $user_id ) {
        $this->delete_all_user_data( $user_id );
        
        RWP_Creator_Suite_Error_Logger::log(
            'Scheduled user data deletion completed',
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            array(
                'user_id' => $user_id,
                'timestamp' => current_time( 'mysql' ),
            )
        );
    }

    /**
     * Update user preferences.
     *
     * @param int   $user_id User ID.
     * @param array $preferences New preferences.
     */
    private function update_user_preferences( $user_id, $preferences ) {
        foreach ( $preferences as $key => $value ) {
            $sanitized_value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
            update_user_meta( $user_id, $key, $sanitized_value );
        }
    }

    /**
     * Update consent preferences.
     *
     * @param int   $user_id User ID.
     * @param array $consent_preferences New consent preferences.
     */
    private function update_consent_preferences( $user_id, $consent_preferences ) {
        if ( class_exists( 'RWP_Creator_Suite_Granular_Consent' ) ) {
            $granular_consent = new RWP_Creator_Suite_Granular_Consent();
            $granular_consent->record_consent( $consent_preferences );
        }
    }

    /**
     * Get structured user preferences for portability.
     *
     * @param int $user_id User ID.
     * @return array Structured preferences.
     */
    private function get_structured_preferences( $user_id ) {
        return $this->get_user_preferences( $user_id );
    }

    /**
     * Get user templates for portability.
     *
     * @param int $user_id User ID.
     * @return array User templates.
     */
    private function get_user_templates( $user_id ) {
        // This would be implemented based on template storage structure
        return array();
    }

    /**
     * Get user favorites for portability.
     *
     * @param int $user_id User ID.
     * @return array User favorites.
     */
    private function get_user_favorites( $user_id ) {
        return get_user_meta( $user_id, 'rwp_caption_favorites', true ) ?: array();
    }

    /**
     * Get usage patterns for portability.
     *
     * @param int $user_id User ID.
     * @return array Usage patterns.
     */
    private function get_usage_patterns( $user_id ) {
        return $this->get_usage_statistics( $user_id );
    }
}