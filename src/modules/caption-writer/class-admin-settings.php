<?php
/**
 * Caption Writer Admin Settings
 * 
 * Handles the WordPress admin settings page for Caption Writer AI configuration.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Caption_Admin_Settings {

    private $settings_page = 'rwp-caption-writer-settings';
    private $settings_group = 'rwp_caption_writer_settings';
    private $menu_slug = 'rwp-caption-writer';
    private $key_manager;
    
    /**
     * Initialize admin settings.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'init_key_manager' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }
    
    /**
     * Initialize key manager on admin_init.
     */
    public function init_key_manager() {
        // Initialize secure key manager
        $this->key_manager = new RWP_Creator_Suite_Key_Manager();
        
        // Migrate existing keys on admin init
        $this->key_manager->migrate_existing_keys();
    }
    
    /**
     * Get key manager instance.
     */
    private function get_key_manager() {
        if ( ! $this->key_manager ) {
            $this->key_manager = new RWP_Creator_Suite_Key_Manager();
        }
        return $this->key_manager;
    }
    
    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            __( 'Caption Writer AI Settings', 'rwp-creator-suite' ),
            __( 'Caption Writer', 'rwp-creator-suite' ),
            'manage_options',
            $this->menu_slug,
            array( $this, 'render_settings_page' )
        );
    }
    
    /**
     * Register settings and fields.
     */
    public function register_settings() {
        // Register setting groups
        register_setting( $this->settings_group, 'rwp_creator_suite_ai_provider', array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize_ai_provider' ),
            'default' => 'mock',
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_openai_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize_api_key' ),
            'default' => '',
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_claude_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize_api_key' ),
            'default' => '',
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_ai_model', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-3.5-turbo',
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_rate_limit_free', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10,
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_rate_limit_premium', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 50,
        ) );
        
        // Add settings sections
        add_settings_section(
            'rwp_caption_ai_section',
            __( 'AI Service Configuration', 'rwp-creator-suite' ),
            array( $this, 'render_ai_section_description' ),
            $this->settings_page
        );
        
        add_settings_section(
            'rwp_caption_rate_limit_section',
            __( 'Rate Limiting', 'rwp-creator-suite' ),
            array( $this, 'render_rate_limit_section_description' ),
            $this->settings_page
        );
        
        // Add settings fields
        add_settings_field(
            'ai_provider',
            __( 'AI Provider', 'rwp-creator-suite' ),
            array( $this, 'render_ai_provider_field' ),
            $this->settings_page,
            'rwp_caption_ai_section'
        );
        
        add_settings_field(
            'openai_api_key',
            __( 'OpenAI API Key', 'rwp-creator-suite' ),
            array( $this, 'render_openai_api_key_field' ),
            $this->settings_page,
            'rwp_caption_ai_section'
        );
        
        add_settings_field(
            'claude_api_key',
            __( 'Claude API Key', 'rwp-creator-suite' ),
            array( $this, 'render_claude_api_key_field' ),
            $this->settings_page,
            'rwp_caption_ai_section'
        );
        
        add_settings_field(
            'ai_model',
            __( 'AI Model', 'rwp-creator-suite' ),
            array( $this, 'render_ai_model_field' ),
            $this->settings_page,
            'rwp_caption_ai_section'
        );
        
        add_settings_field(
            'rate_limit_free',
            __( 'Free User Rate Limit (per hour)', 'rwp-creator-suite' ),
            array( $this, 'render_rate_limit_free_field' ),
            $this->settings_page,
            'rwp_caption_rate_limit_section'
        );
        
        add_settings_field(
            'rate_limit_premium',
            __( 'Premium User Rate Limit (per hour)', 'rwp-creator-suite' ),
            array( $this, 'render_rate_limit_premium_field' ),
            $this->settings_page,
            'rwp_caption_rate_limit_section'
        );
    }
    
    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Handle form submission
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error( 
                $this->settings_group, 
                'settings_updated', 
                __( 'Settings saved successfully!', 'rwp-creator-suite' ), 
                'updated' 
            );
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="rwp-caption-settings">
                <div class="rwp-settings-main">
                    <?php settings_errors( $this->settings_group ); ?>
                    
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( $this->settings_group );
                        do_settings_sections( $this->settings_page );
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="rwp-settings-sidebar">
                    <div class="rwp-settings-card">
                        <h3><?php esc_html_e( 'Getting Started', 'rwp-creator-suite' ); ?></h3>
                        <p><?php esc_html_e( 'To use AI caption generation, you need to:', 'rwp-creator-suite' ); ?></p>
                        <ol>
                            <li><?php esc_html_e( 'Choose an AI provider', 'rwp-creator-suite' ); ?></li>
                            <li><?php esc_html_e( 'Enter your API key', 'rwp-creator-suite' ); ?></li>
                            <li><?php esc_html_e( 'Test the connection', 'rwp-creator-suite' ); ?></li>
                        </ol>
                    </div>
                    
                    <div class="rwp-settings-card">
                        <h3><?php esc_html_e( 'API Key Links', 'rwp-creator-suite' ); ?></h3>
                        <p>
                            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">
                                <?php esc_html_e( 'Get OpenAI API Key', 'rwp-creator-suite' ); ?>
                            </a>
                        </p>
                        <p>
                            <a href="https://console.anthropic.com/" target="_blank" rel="noopener">
                                <?php esc_html_e( 'Get Claude API Key', 'rwp-creator-suite' ); ?>
                            </a>
                        </p>
                    </div>
                    
                    <div class="rwp-settings-card">
                        <h3><?php esc_html_e( 'Connection Status', 'rwp-creator-suite' ); ?></h3>
                        <div id="rwp-connection-status">
                            <p><?php esc_html_e( 'Click "Test Connection" to verify your settings.', 'rwp-creator-suite' ); ?></p>
                        </div>
                        <button type="button" id="rwp-test-connection" class="button">
                            <?php esc_html_e( 'Test Connection', 'rwp-creator-suite' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render AI section description.
     */
    public function render_ai_section_description() {
        echo '<p>' . esc_html__( 'Configure your AI service provider and API credentials.', 'rwp-creator-suite' ) . '</p>';
    }
    
    /**
     * Render rate limit section description.
     */
    public function render_rate_limit_section_description() {
        echo '<p>' . esc_html__( 'Set rate limits to control API usage and costs.', 'rwp-creator-suite' ) . '</p>';
    }
    
    /**
     * Render AI provider field.
     */
    public function render_ai_provider_field() {
        $value = get_option( 'rwp_creator_suite_ai_provider', 'mock' );
        ?>
        <select name="rwp_creator_suite_ai_provider" id="rwp_creator_suite_ai_provider">
            <option value="mock" <?php selected( $value, 'mock' ); ?>>
                <?php esc_html_e( 'Mock/Demo (No API required)', 'rwp-creator-suite' ); ?>
            </option>
            <option value="openai" <?php selected( $value, 'openai' ); ?>>
                <?php esc_html_e( 'OpenAI (GPT-3.5/GPT-4)', 'rwp-creator-suite' ); ?>
            </option>
            <option value="claude" <?php selected( $value, 'claude' ); ?>>
                <?php esc_html_e( 'Anthropic Claude', 'rwp-creator-suite' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select your preferred AI service provider.', 'rwp-creator-suite' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render OpenAI API key field.
     */
    public function render_openai_api_key_field() {
        $value = $this->get_key_manager()->get_api_key( 'openai' );
        ?>
        <input type="password" 
               name="rwp_creator_suite_openai_api_key" 
               id="rwp_creator_suite_openai_api_key"
               value="<?php echo esc_attr( $value ); ?>" 
               class="regular-text"
               placeholder="sk-...">
        <button type="button" class="button toggle-password" data-target="rwp_creator_suite_openai_api_key">
            <?php esc_html_e( 'Show', 'rwp-creator-suite' ); ?>
        </button>
        <p class="description">
            <?php esc_html_e( 'Your OpenAI API key. Required when using OpenAI as provider.', 'rwp-creator-suite' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render Claude API key field.
     */
    public function render_claude_api_key_field() {
        $value = $this->get_key_manager()->get_api_key( 'claude' );
        ?>
        <input type="password" 
               name="rwp_creator_suite_claude_api_key" 
               id="rwp_creator_suite_claude_api_key"
               value="<?php echo esc_attr( $value ); ?>" 
               class="regular-text"
               placeholder="sk-ant-...">
        <button type="button" class="button toggle-password" data-target="rwp_creator_suite_claude_api_key">
            <?php esc_html_e( 'Show', 'rwp-creator-suite' ); ?>
        </button>
        <p class="description">
            <?php esc_html_e( 'Your Anthropic Claude API key. Required when using Claude as provider.', 'rwp-creator-suite' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render AI model field.
     */
    public function render_ai_model_field() {
        $value = get_option( 'rwp_creator_suite_ai_model', 'gpt-3.5-turbo' );
        ?>
        <select name="rwp_creator_suite_ai_model" id="rwp_creator_suite_ai_model">
            <optgroup label="<?php esc_attr_e( 'OpenAI Models', 'rwp-creator-suite' ); ?>">
                <option value="gpt-3.5-turbo" <?php selected( $value, 'gpt-3.5-turbo' ); ?>>
                    GPT-3.5 Turbo (Recommended)
                </option>
                <option value="gpt-4" <?php selected( $value, 'gpt-4' ); ?>>
                    GPT-4 (Premium)
                </option>
                <option value="gpt-4-turbo" <?php selected( $value, 'gpt-4-turbo' ); ?>>
                    GPT-4 Turbo (Latest)
                </option>
            </optgroup>
            <optgroup label="<?php esc_attr_e( 'Claude Models', 'rwp-creator-suite' ); ?>">
                <option value="claude-3-sonnet-20240229" <?php selected( $value, 'claude-3-sonnet-20240229' ); ?>>
                    Claude 3 Sonnet (Recommended)
                </option>
                <option value="claude-3-opus-20240229" <?php selected( $value, 'claude-3-opus-20240229' ); ?>>
                    Claude 3 Opus (Premium)
                </option>
            </optgroup>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the AI model to use for caption generation.', 'rwp-creator-suite' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render rate limit free field.
     */
    public function render_rate_limit_free_field() {
        $value = get_option( 'rwp_creator_suite_rate_limit_free', 10 );
        ?>
        <input type="number" 
               name="rwp_creator_suite_rate_limit_free" 
               id="rwp_creator_suite_rate_limit_free"
               value="<?php echo esc_attr( $value ); ?>" 
               min="1" 
               max="1000"
               class="small-text">
        <p class="description">
            <?php esc_html_e( 'Maximum AI generations per hour for free users.', 'rwp-creator-suite' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render rate limit premium field.
     */
    public function render_rate_limit_premium_field() {
        $value = get_option( 'rwp_creator_suite_rate_limit_premium', 50 );
        ?>
        <input type="number" 
               name="rwp_creator_suite_rate_limit_premium" 
               id="rwp_creator_suite_rate_limit_premium"
               value="<?php echo esc_attr( $value ); ?>" 
               min="1" 
               max="1000"
               class="small-text">
        <p class="description">
            <?php esc_html_e( 'Maximum AI generations per hour for premium users.', 'rwp-creator-suite' ); ?>
        </p>
        <?php
    }
    
    /**
     * Enqueue admin scripts.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, $this->menu_slug ) === false ) {
            return;
        }
        
        wp_enqueue_style(
            'rwp-caption-admin',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/css/caption-admin.css',
            array(),
            RWP_CREATOR_SUITE_VERSION
        );
        
        wp_enqueue_script(
            'rwp-caption-admin',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/caption-admin.js',
            array( 'jquery' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );
        
        wp_localize_script(
            'rwp-caption-admin',
            'rwpCaptionAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'restUrl' => rest_url( 'rwp-creator-suite/v1/' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'strings' => array(
                    'testing' => __( 'Testing connection...', 'rwp-creator-suite' ),
                    'testSuccess' => __( 'Connection successful!', 'rwp-creator-suite' ),
                    'testFailed' => __( 'Connection failed. Please check your settings.', 'rwp-creator-suite' ),
                ),
            )
        );
    }
    
    /**
     * Sanitize AI provider.
     */
    public function sanitize_ai_provider( $value ) {
        $allowed = array( 'mock', 'openai', 'claude', 'local' );
        return in_array( $value, $allowed, true ) ? $value : 'mock';
    }
    
    /**
     * Sanitize and securely store API key.
     */
    public function sanitize_api_key( $value ) {
        $value = sanitize_text_field( trim( $value ) );
        
        // If empty, don't process further
        if ( empty( $value ) ) {
            return '';
        }
        
        // Determine provider based on current context
        $provider = 'openai'; // Default
        if ( isset( $_POST['option_page'] ) && $_POST['option_page'] === $this->settings_group ) {
            // Check which field is being updated
            if ( isset( $_POST['rwp_creator_suite_claude_api_key'] ) && $_POST['rwp_creator_suite_claude_api_key'] === $value ) {
                $provider = 'claude';
            }
        }
        
        // Use secure key manager to save
        $result = $this->get_key_manager()->save_api_key( $value, $provider );
        
        if ( is_wp_error( $result ) ) {
            // Add admin notice for error
            add_settings_error(
                'rwp_caption_writer_settings',
                'api_key_error',
                $result->get_error_message(),
                'error'
            );
            return '';
        }
        
        // Return empty string as we store encrypted separately
        return '';
    }
}