<?php
/**
 * Block Manager
 * 
 * Handles WordPress block registration and management.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Block_Manager {

    /**
     * Instagram Analyzer API instance.
     */
    private $instagram_api;

    /**
     * Initialize the block manager.
     */
    public function init() {
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'enqueue_block_assets', array( $this, 'enqueue_frontend_assets' ) );
        
        // Initialize Instagram Analyzer API
        $this->instagram_api = new RWP_Creator_Suite_Instagram_Analyzer_API();
        $this->instagram_api->init();
    }

    /**
     * Register all blocks.
     */
    public function register_blocks() {
        // Register Instagram Analyzer block from build directory
        register_block_type( RWP_CREATOR_SUITE_PLUGIN_DIR . 'build/blocks/instagram-analyzer' );
        
        // Register Instagram Banner block from build directory
        register_block_type( RWP_CREATOR_SUITE_PLUGIN_DIR . 'build/blocks/instagram-banner' );
    }

    /**
     * Enqueue frontend assets for blocks.
     */
    public function enqueue_frontend_assets() {
        global $post;

        // Only enqueue if Instagram Analyzer block is present
        if ( has_block( 'rwp-creator-suite/instagram-analyzer', $post ) ) {
            $this->enqueue_instagram_analyzer_assets();
        }
        
        // Only enqueue if Instagram Banner block is present
        if ( has_block( 'rwp-creator-suite/instagram-banner', $post ) ) {
            $this->enqueue_instagram_banner_assets();
        }
    }

    /**
     * Enqueue Instagram Analyzer specific assets.
     */
    private function enqueue_instagram_analyzer_assets() {
        // Enqueue JSZip library
        wp_enqueue_script(
            'jszip',
            'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
            array(),
            '3.10.1',
            true
        );

        // Enqueue State Manager
        wp_enqueue_script(
            'rwp-state-manager',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/state-manager.js',
            array(),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        // Enqueue Instagram Analyzer app
        wp_enqueue_script(
            'rwp-instagram-analyzer-app',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/instagram-analyzer.js',
            array( 'jszip', 'rwp-state-manager' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        // Localize script with WordPress data
        wp_localize_script(
            'rwp-instagram-analyzer-app',
            'rwpInstagramAnalyzer',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'rwp_instagram_analyzer_nonce' ),
                'isLoggedIn' => is_user_logged_in(),
                'currentUserId' => get_current_user_id(),
                'strings' => array(
                    'uploadPrompt' => __( 'Upload your Instagram data export ZIP file', 'rwp-creator-suite' ),
                    'processing' => __( 'Processing...', 'rwp-creator-suite' ),
                    'analysisComplete' => __( 'Analysis Complete', 'rwp-creator-suite' ),
                    'loginRequired' => __( 'Login required to see full results', 'rwp-creator-suite' ),
                )
            )
        );
    }

    /**
     * Enqueue Instagram Banner specific assets.
     */
    private function enqueue_instagram_banner_assets() {
        // Enqueue State Manager (shared dependency)
        wp_enqueue_script(
            'rwp-state-manager',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/state-manager.js',
            array(),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        // Enqueue Instagram Banner app
        wp_enqueue_script(
            'rwp-instagram-banner-app',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/instagram-banner.js',
            array( 'rwp-state-manager' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        // Localize script with WordPress data
        wp_localize_script(
            'rwp-instagram-banner-app',
            'rwpInstagramBanner',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'rwp_instagram_banner_nonce' ),
                'isLoggedIn' => is_user_logged_in(),
                'currentUserId' => get_current_user_id(),
                'strings' => array(
                    'uploadPrompt' => __( 'Upload an image to create Instagram banner', 'rwp-creator-suite' ),
                    'processing' => __( 'Processing...', 'rwp-creator-suite' ),
                    'cropPrompt' => __( 'Crop your image to 3248x1440 aspect ratio', 'rwp-creator-suite' ),
                    'loginRequired' => __( 'Login required to download images', 'rwp-creator-suite' ),
                    'download' => __( 'Download Images', 'rwp-creator-suite' ),
                    'preview' => __( 'Preview', 'rwp-creator-suite' ),
                )
            )
        );
    }
}