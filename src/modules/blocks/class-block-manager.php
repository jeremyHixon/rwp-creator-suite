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
        
        // Register Caption Writer block from build directory
        register_block_type( 
            RWP_CREATOR_SUITE_PLUGIN_DIR . 'build/blocks/caption-writer',
            array(
                'render_callback' => array( $this, 'render_caption_writer_block' ),
            )
        );
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
        
        // Only enqueue if Caption Writer block is present
        if ( has_block( 'rwp-creator-suite/caption-writer', $post ) ) {
            $this->enqueue_caption_writer_assets();
        }
    }

    /**
     * Enqueue Instagram Analyzer specific assets.
     */
    private function enqueue_instagram_analyzer_assets() {
        // Enhanced JSZip library loading with better fallback handling
        $jszip_local_path = RWP_CREATOR_SUITE_PLUGIN_DIR . 'assets/vendor/jszip.min.js';
        $jszip_version = '3.10.1';
        
        // Check if local file exists and get its version
        if ( file_exists( $jszip_local_path ) ) {
            $jszip_url = RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/vendor/jszip.min.js';
            $jszip_version = filemtime( $jszip_local_path ); // Use file time for cache busting
        } else {
            $jszip_url = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
            RWP_Creator_Suite_Error_Logger::log_performance( 
                'JSZip CDN Fallback', 
                0, 
                array( 'reason' => 'Local file not found', 'path' => $jszip_local_path )
            );
        }
        
        wp_enqueue_script(
            'jszip',
            $jszip_url,
            array(),
            $jszip_version,
            true
        );
        
        // Enhanced error handling with better user feedback
        $jszip_error_handler = "
            (function() {
                var checkJSZip = function() {
                    if (typeof JSZip === 'undefined') {
                        console.error('JSZip library failed to load');
                        
                        // Try to load fallback if primary fails
                        if (!window.jszip_fallback_attempted) {
                            window.jszip_fallback_attempted = true;
                            var fallbackScript = document.createElement('script');
                            fallbackScript.src = 'https://unpkg.com/jszip@3.10.1/dist/jszip.min.js';
                            fallbackScript.onload = function() {
                                console.log('JSZip loaded from fallback CDN');
                            };
                            fallbackScript.onerror = function() {
                                console.error('All JSZip sources failed to load');
                                if (window.rwpInstagramAnalyzer && window.rwpInstagramAnalyzer.strings) {
                                    var notice = document.createElement('div');
                                    notice.className = 'notice notice-error';
                                    notice.innerHTML = '<p>Required library failed to load. Some features may not work properly.</p>';
                                    var container = document.querySelector('.wp-block-rwp-creator-suite-instagram-analyzer');
                                    if (container) container.prepend(notice);
                                }
                            };
                            document.head.appendChild(fallbackScript);
                        }
                    }
                };
                
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', checkJSZip);
                } else {
                    checkJSZip();
                }
            })();
        ";
        wp_add_inline_script( 'jszip', $jszip_error_handler, 'after' );

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
    
    /**
     * Render Caption Writer block.
     */
    public function render_caption_writer_block( $attributes, $content ) {
        // Include the render template
        ob_start();
        include RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/blocks/caption-writer/render.php';
        return ob_get_clean();
    }
    
    /**
     * Enqueue Caption Writer specific assets.
     */
    private function enqueue_caption_writer_assets() {
        // Enqueue State Manager (shared dependency)
        wp_enqueue_script(
            'rwp-state-manager',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/state-manager.js',
            array(),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        // Enqueue Caption Writer app
        wp_enqueue_script(
            'rwp-caption-writer-app',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/caption-writer.js',
            array( 'rwp-state-manager' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        // Localize script with WordPress data
        wp_localize_script(
            'rwp-caption-writer-app',
            'rwpCaptionWriter',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'restUrl' => rest_url( 'rwp-creator-suite/v1/' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'isLoggedIn' => is_user_logged_in(),
                'currentUserId' => get_current_user_id(),
                'strings' => array(
                    'generatePrompt' => __( 'Describe your content to generate captions', 'rwp-creator-suite' ),
                    'processing' => __( 'Generating captions...', 'rwp-creator-suite' ),
                    'generatedCaptions' => __( 'Generated Captions', 'rwp-creator-suite' ),
                    'loginRequired' => __( 'Login required to save favorites and templates', 'rwp-creator-suite' ),
                    'copySuccess' => __( 'Caption copied to clipboard!', 'rwp-creator-suite' ),
                    'saveSuccess' => __( 'Saved to favorites!', 'rwp-creator-suite' ),
                    'templateSuccess' => __( 'Saved as template!', 'rwp-creator-suite' ),
                    'errorGeneral' => __( 'Something went wrong. Please try again.', 'rwp-creator-suite' ),
                    'errorDescription' => __( 'Please enter a description for your content', 'rwp-creator-suite' ),
                ),
                'characterLimits' => array(
                    'instagram' => 2200,
                    'tiktok' => 2200,
                    'twitter' => 280,
                    'linkedin' => 3000,
                    'facebook' => 63206,
                ),
            )
        );
    }
}