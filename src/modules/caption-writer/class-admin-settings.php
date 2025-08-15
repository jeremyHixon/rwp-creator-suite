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
        
        register_setting( $this->settings_group, 'rwp_creator_suite_rate_limit_guest', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 5,
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_allow_guest_repurpose', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_custom_roles', array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize_roles_config' ),
            'default' => '',
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_ai_prompts', array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize_prompts_config' ),
            'default' => '',
        ) );
        
        register_setting( $this->settings_group, 'rwp_creator_suite_custom_platforms', array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize_platforms_config' ),
            'default' => '',
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
        
        add_settings_section(
            'rwp_content_repurposer_section',
            __( 'Content Repurposer Settings', 'rwp-creator-suite' ),
            array( $this, 'render_content_repurposer_section_description' ),
            $this->settings_page
        );
        
        add_settings_section(
            'rwp_roles_configuration_section',
            __( 'Role Configuration', 'rwp-creator-suite' ),
            array( $this, 'render_roles_section_description' ),
            $this->settings_page
        );
        
        add_settings_section(
            'rwp_ai_prompts_section',
            __( 'AI Prompt Configuration', 'rwp-creator-suite' ),
            array( $this, 'render_ai_prompts_section_description' ),
            $this->settings_page
        );
        
        add_settings_section(
            'rwp_platforms_configuration_section',
            __( 'Platform Configuration', 'rwp-creator-suite' ),
            array( $this, 'render_platforms_section_description' ),
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
        
        add_settings_field(
            'rate_limit_guest',
            __( 'Guest User Rate Limit (per hour)', 'rwp-creator-suite' ),
            array( $this, 'render_rate_limit_guest_field' ),
            $this->settings_page,
            'rwp_caption_rate_limit_section'
        );
        
        add_settings_field(
            'allow_guest_repurpose',
            __( 'Allow Guest Content Repurposing', 'rwp-creator-suite' ),
            array( $this, 'render_allow_guest_repurpose_field' ),
            $this->settings_page,
            'rwp_content_repurposer_section'
        );
        
        add_settings_field(
            'custom_roles',
            __( 'Custom Roles/Tones', 'rwp-creator-suite' ),
            array( $this, 'render_custom_roles_field' ),
            $this->settings_page,
            'rwp_roles_configuration_section'
        );
        
        add_settings_field(
            'ai_prompts',
            __( 'AI Prompt Templates', 'rwp-creator-suite' ),
            array( $this, 'render_ai_prompts_field' ),
            $this->settings_page,
            'rwp_ai_prompts_section'
        );
        
        add_settings_field(
            'custom_platforms',
            __( 'Social Media Platforms', 'rwp-creator-suite' ),
            array( $this, 'render_custom_platforms_field' ),
            $this->settings_page,
            'rwp_platforms_configuration_section'
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
     * Render content repurposer section description.
     */
    public function render_content_repurposer_section_description() {
        echo '<p>' . esc_html__( 'Configure settings for the Content Repurposer feature.', 'rwp-creator-suite' ) . '</p>';
    }
    
    /**
     * Render roles section description.
     */
    public function render_roles_section_description() {
        echo '<p>' . esc_html__( 'Configure custom roles/tones for both Caption Writer and Content Repurposer blocks.', 'rwp-creator-suite' ) . '</p>';
    }
    
    /**
     * Render AI prompts section description.
     */
    public function render_ai_prompts_section_description() {
        echo '<p>' . esc_html__( 'Customize AI prompt templates for system messages, tone descriptions, platform guidance, and prompt templates.', 'rwp-creator-suite' ) . '</p>';
    }
    
    /**
     * Render platforms section description.
     */
    public function render_platforms_section_description() {
        echo '<p>' . esc_html__( 'Configure available social media platforms for both Caption Writer and Content Repurposer blocks.', 'rwp-creator-suite' ) . '</p>';
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
     * Render rate limit guest field.
     */
    public function render_rate_limit_guest_field() {
        $value = get_option( 'rwp_creator_suite_rate_limit_guest', 5 );
        ?>
        <input type="number" 
               name="rwp_creator_suite_rate_limit_guest" 
               id="rwp_creator_suite_rate_limit_guest"
               value="<?php echo esc_attr( $value ); ?>" 
               min="1" 
               max="100"
               class="small-text">
        <p class="description">
            <?php esc_html_e( 'Maximum AI generations per hour for guest users.', 'rwp-creator-suite' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render allow guest repurpose field.
     */
    public function render_allow_guest_repurpose_field() {
        $value = get_option( 'rwp_creator_suite_allow_guest_repurpose', false );
        ?>
        <label for="rwp_creator_suite_allow_guest_repurpose">
            <input type="checkbox" 
                   name="rwp_creator_suite_allow_guest_repurpose" 
                   id="rwp_creator_suite_allow_guest_repurpose"
                   value="1" 
                   <?php checked( $value ); ?>>
            <?php esc_html_e( 'Allow non-logged-in users to use content repurposing (with rate limits)', 'rwp-creator-suite' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, guests can use the content repurposer with stricter rate limits.', 'rwp-creator-suite' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render custom roles field.
     */
    public function render_custom_roles_field() {
        $value = get_option( 'rwp_creator_suite_custom_roles', '' );
        $default_roles = $this->get_default_roles();
        ?>
        <div class="rwp-roles-configuration">
            <textarea 
                name="rwp_creator_suite_custom_roles" 
                id="rwp_creator_suite_custom_roles"
                rows="10" 
                cols="80" 
                class="large-text code"
                placeholder="<?php echo esc_attr( $default_roles ); ?>"
            ><?php echo esc_textarea( $value ? $value : $default_roles ); ?></textarea>
            <p class="description">
                <?php esc_html_e( 'Configure custom roles/tones in JSON format. Each role should have a "value" (used internally) and "label" (displayed to users).', 'rwp-creator-suite' ); ?>
            </p>
            <div class="rwp-roles-help">
                <details>
                    <summary><?php esc_html_e( 'Click here for format examples', 'rwp-creator-suite' ); ?></summary>
                    <div class="rwp-help-content">
                        <p><strong><?php esc_html_e( 'Default format:', 'rwp-creator-suite' ); ?></strong></p>
                        <pre><?php echo esc_html( $default_roles ); ?></pre>
                        <p><strong><?php esc_html_e( 'Field descriptions:', 'rwp-creator-suite' ); ?></strong></p>
                        <ul>
                            <li><code>value</code>: <?php esc_html_e( 'Internal identifier (lowercase, no spaces)', 'rwp-creator-suite' ); ?></li>
                            <li><code>label</code>: <?php esc_html_e( 'Display name shown to users', 'rwp-creator-suite' ); ?></li>
                            <li><code>description</code>: <?php esc_html_e( 'Optional tooltip or help text', 'rwp-creator-suite' ); ?></li>
                        </ul>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render AI prompts field.
     */
    public function render_ai_prompts_field() {
        $value = get_option( 'rwp_creator_suite_ai_prompts', '' );
        $default_prompts = $this->get_default_prompts();
        ?>
        <div class="rwp-prompts-configuration">
            <textarea 
                name="rwp_creator_suite_ai_prompts" 
                id="rwp_creator_suite_ai_prompts"
                rows="20" 
                cols="80" 
                class="large-text code"
                placeholder="<?php echo esc_attr( $default_prompts ); ?>"
            ><?php echo esc_textarea( $value ? $value : $default_prompts ); ?></textarea>
            <p class="description">
                <?php esc_html_e( 'Configure AI prompt templates in JSON format. Structure includes system messages, tone descriptions, platform guidance, and prompt templates.', 'rwp-creator-suite' ); ?>
            </p>
            <div class="rwp-prompts-help">
                <details>
                    <summary><?php esc_html_e( 'Click here for format examples and documentation', 'rwp-creator-suite' ); ?></summary>
                    <div class="rwp-help-content">
                        <p><strong><?php esc_html_e( 'Configuration sections:', 'rwp-creator-suite' ); ?></strong></p>
                        <ul>
                            <li><code>system_messages</code>: <?php esc_html_e( 'Base system messages for different contexts (captions, repurpose, general)', 'rwp-creator-suite' ); ?></li>
                            <li><code>tone_descriptions</code>: <?php esc_html_e( 'Descriptions for each tone/role (casual, professional, etc.)', 'rwp-creator-suite' ); ?></li>
                            <li><code>platform_guidance</code>: <?php esc_html_e( 'Platform-specific guidance for content generation', 'rwp-creator-suite' ); ?></li>
                            <li><code>prompt_templates</code>: <?php esc_html_e( 'Main prompt templates for caption and repurpose functions', 'rwp-creator-suite' ); ?></li>
                        </ul>
                        <p><strong><?php esc_html_e( 'Default configuration:', 'rwp-creator-suite' ); ?></strong></p>
                        <pre style="max-height: 300px; overflow-y: auto; white-space: pre-wrap; background: #f0f0f1; padding: 10px; border: 1px solid #ddd;"><?php echo esc_html( $default_prompts ); ?></pre>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render custom platforms field.
     */
    public function render_custom_platforms_field() {
        $value = get_option( 'rwp_creator_suite_custom_platforms', '' );
        $default_platforms = $this->get_default_platforms();
        ?>
        <div class="rwp-platforms-configuration">
            <textarea 
                name="rwp_creator_suite_custom_platforms" 
                id="rwp_creator_suite_custom_platforms"
                rows="15" 
                cols="80" 
                class="large-text code"
                placeholder="<?php echo esc_attr( $default_platforms ); ?>"
            ><?php echo esc_textarea( $value ? $value : $default_platforms ); ?></textarea>
            <p class="description">
                <?php esc_html_e( 'Configure available social media platforms in JSON format. Each platform should have a "key" (used internally), "label" (displayed to users), and "character_limit" for optimization.', 'rwp-creator-suite' ); ?>
            </p>
            <div class="rwp-platforms-help">
                <details>
                    <summary><?php esc_html_e( 'Click here for format examples', 'rwp-creator-suite' ); ?></summary>
                    <div class="rwp-help-content">
                        <p><strong><?php esc_html_e( 'Default format:', 'rwp-creator-suite' ); ?></strong></p>
                        <pre><?php echo esc_html( $default_platforms ); ?></pre>
                        <p><strong><?php esc_html_e( 'Field descriptions:', 'rwp-creator-suite' ); ?></strong></p>
                        <ul>
                            <li><code>key</code>: <?php esc_html_e( 'Internal identifier (lowercase, no spaces)', 'rwp-creator-suite' ); ?></li>
                            <li><code>label</code>: <?php esc_html_e( 'Display name shown to users', 'rwp-creator-suite' ); ?></li>
                            <li><code>character_limit</code>: <?php esc_html_e( 'Maximum character count for this platform', 'rwp-creator-suite' ); ?></li>
                            <li><code>icon_class</code>: <?php esc_html_e( 'Optional CSS class for platform icon', 'rwp-creator-suite' ); ?></li>
                            <li><code>description</code>: <?php esc_html_e( 'Optional tooltip or help text', 'rwp-creator-suite' ); ?></li>
                        </ul>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get default prompts configuration.
     */
    private function get_default_prompts() {
        $default_prompts = array(
            'system_messages' => array(
                'captions' => 'You are a social media caption expert who creates engaging, platform-optimized content. Always follow formatting instructions exactly as specified. Use only plain text without markdown formatting unless explicitly requested.',
                'repurpose' => 'You are a content strategist who specializes in adapting content for different social media platforms. You MUST follow formatting instructions precisely. Always respond with exactly the number of items requested, using simple numbered format (1., 2., 3.) with no markdown formatting, sub-bullets, or complex structure. Each numbered item should be complete and standalone.',
                'general' => 'You are a helpful AI assistant focused on creating high-quality content. Follow all formatting instructions exactly as provided.'
            ),
            'tone_descriptions' => array(
                'casual' => 'friendly, conversational, approachable',
                'professional' => 'polished, authoritative, business-appropriate',
                'witty' => 'clever, humorous, engaging with wordplay',
                'inspirational' => 'motivational, uplifting, encouraging',
                'engaging' => 'compelling, interactive, encourages responses',
                'informative' => 'educational, fact-focused, clear and concise',
                'question' => 'engaging with questions that encourage comments'
            ),
            'platform_guidance' => array(
                'instagram' => 'Include relevant emoji and hashtag placeholder. Use line breaks for readability.',
                'tiktok' => 'Keep it punchy and trend-aware. Include emoji and hashtag placeholder.',
                'twitter' => 'Be concise due to character limit. Use trending topics when relevant.',
                'linkedin' => 'More professional tone. Focus on industry insights or career growth.',
                'facebook' => 'Can be longer and more conversational. Include call-to-action questions.'
            ),
            'prompt_templates' => array(
                'caption_generation' => "Create 3 different {tone_desc} captions for {platform} based on this content description: \"{description}\"\n\nCRITICAL FORMATTING REQUIREMENTS:\n- You MUST respond with exactly 3 numbered items\n- Use ONLY this format: \"1. [caption]\n\n2. [caption]\n\n3. [caption]\"\n- Do NOT use markdown formatting (no **, __, or other markup)\n- Each numbered item must be complete on its own\n- Each caption should be under {character_limit} characters (leaving room for hashtags)\n\nCONTENT REQUIREMENTS:\n- {platform_guidance}\n- End each caption with {hashtags} as a placeholder for hashtag insertion\n- Make each caption distinctly different in approach and style\n- Focus on engagement and authenticity\n- The tone should be: {tone_desc}\n\nEXAMPLE FORMAT:\n1. First caption text here {hashtags}\n\n2. Second caption text here {hashtags}\n\n3. Third caption text here {hashtags}",
                'single_repurpose' => "Repurpose the following content for {platform}:\n\n\"{content}\"\n\nCRITICAL FORMATTING REQUIREMENTS:\n- You MUST respond with exactly 3 numbered items\n- Use ONLY this format: \"1. [content]\n\n2. [content]\n\n3. [content]\"\n- Do NOT use markdown formatting (no **, __, or other markup)\n- Do NOT include sub-bullets or nested content\n- Each numbered item must be complete on its own\n- Keep each version under {character_limit} characters\n\nCONTENT REQUIREMENTS:\n- Create 3 different versions optimized for {platform}\n- {platform_guidance}\n- Maintain the core message while adapting the style and format\n- Use a {tone_desc} tone\n- Extract and highlight the most important points\n- Make each version distinctly different in approach\n\nEXAMPLE FORMAT:\n1. First version of the repurposed content here.\n\n2. Second version of the repurposed content here.\n\n3. Third version of the repurposed content here.",
                'multi_repurpose' => "Repurpose the following content for multiple social media platforms ({platform_list}):\n\n\"{content}\"\n\nCRITICAL FORMATTING REQUIREMENTS:\n- You MUST create content for each platform in this EXACT order: {platform_order}\n- For each platform, provide exactly 3 numbered versions\n- Use this format: \"PLATFORM_NAME:\n1. [content]\n\n2. [content]\n\n3. [content]\n\n\"\n- Do NOT use markdown formatting (no **, __, or other markup)\n- Each numbered item must be complete and standalone\n- Separate each platform section with a blank line\n\nPLATFORM REQUIREMENTS:\n{platform_guidance_text}\n\nCONTENT REQUIREMENTS:\n- Maintain the core message while adapting style for each platform\n- Use a {tone_desc} tone throughout\n- Extract and highlight the most important points\n- Make each version within a platform distinctly different\n\nEXAMPLE FORMAT:\nTWITTER:\n1. First Twitter version here.\n\n2. Second Twitter version here.\n\n3. Third Twitter version here.\n\nLINKEDIN:\n1. First LinkedIn version here.\n\n2. Second LinkedIn version here.\n\n3. Third LinkedIn version here."
            )
        );
        
        return wp_json_encode( $default_prompts, JSON_PRETTY_PRINT );
    }
    
    /**
     * Get default roles configuration.
     */
    private function get_default_roles() {
        $default_roles = array(
            array(
                'value' => 'casual',
                'label' => __( 'Casual', 'rwp-creator-suite' ),
                'description' => __( 'Relaxed and conversational tone', 'rwp-creator-suite' )
            ),
            array(
                'value' => 'professional',
                'label' => __( 'Professional', 'rwp-creator-suite' ),
                'description' => __( 'Business-appropriate and formal tone', 'rwp-creator-suite' )
            ),
            array(
                'value' => 'witty',
                'label' => __( 'Witty', 'rwp-creator-suite' ),
                'description' => __( 'Clever and humorous tone', 'rwp-creator-suite' )
            ),
            array(
                'value' => 'inspirational',
                'label' => __( 'Inspirational', 'rwp-creator-suite' ),
                'description' => __( 'Motivating and uplifting tone', 'rwp-creator-suite' )
            ),
            array(
                'value' => 'engaging',
                'label' => __( 'Engaging', 'rwp-creator-suite' ),
                'description' => __( 'Interactive and conversation-starting tone', 'rwp-creator-suite' )
            ),
            array(
                'value' => 'informative',
                'label' => __( 'Informative', 'rwp-creator-suite' ),
                'description' => __( 'Educational and fact-focused tone', 'rwp-creator-suite' )
            ),
            array(
                'value' => 'question',
                'label' => __( 'Question-based', 'rwp-creator-suite' ),
                'description' => __( 'Encourages interaction through questions', 'rwp-creator-suite' )
            )
        );
        
        return wp_json_encode( $default_roles, JSON_PRETTY_PRINT );
    }
    
    /**
     * Get default platforms configuration.
     */
    private function get_default_platforms() {
        $default_platforms = array(
            array(
                'key' => 'instagram',
                'label' => __( 'Instagram', 'rwp-creator-suite' ),
                'character_limit' => 2200,
                'icon_class' => 'instagram',
                'description' => __( 'Visual content platform with engaging captions', 'rwp-creator-suite' )
            ),
            array(
                'key' => 'tiktok',
                'label' => __( 'TikTok/Reels', 'rwp-creator-suite' ),
                'character_limit' => 2200,
                'icon_class' => 'tiktok',
                'description' => __( 'Short-form video content platform', 'rwp-creator-suite' )
            ),
            array(
                'key' => 'twitter',
                'label' => __( 'Twitter/X', 'rwp-creator-suite' ),
                'character_limit' => 280,
                'icon_class' => 'twitter',
                'description' => __( 'Microblogging platform with character limits', 'rwp-creator-suite' )
            ),
            array(
                'key' => 'linkedin',
                'label' => __( 'LinkedIn', 'rwp-creator-suite' ),
                'character_limit' => 3000,
                'icon_class' => 'linkedin',
                'description' => __( 'Professional networking platform', 'rwp-creator-suite' )
            ),
            array(
                'key' => 'facebook',
                'label' => __( 'Facebook', 'rwp-creator-suite' ),
                'character_limit' => 63206,
                'icon_class' => 'facebook',
                'description' => __( 'Social networking platform with longer content support', 'rwp-creator-suite' )
            )
        );
        
        return wp_json_encode( $default_platforms, JSON_PRETTY_PRINT );
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
    
    /**
     * Sanitize roles configuration.
     */
    public function sanitize_roles_config( $value ) {
        $value = sanitize_textarea_field( trim( $value ) );
        
        // If empty, return empty string to use defaults
        if ( empty( $value ) ) {
            return '';
        }
        
        // Try to decode JSON
        $decoded = json_decode( $value, true );
        
        // If invalid JSON, add error and return empty
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            add_settings_error(
                'rwp_caption_writer_settings',
                'roles_config_error',
                __( 'Invalid JSON format in roles configuration. Please check the syntax.', 'rwp-creator-suite' ),
                'error'
            );
            return '';
        }
        
        // Validate structure
        if ( ! is_array( $decoded ) ) {
            add_settings_error(
                'rwp_caption_writer_settings',
                'roles_config_error',
                __( 'Roles configuration must be an array of role objects.', 'rwp-creator-suite' ),
                'error'
            );
            return '';
        }
        
        // Validate each role
        $sanitized_roles = array();
        foreach ( $decoded as $role ) {
            if ( ! is_array( $role ) || ! isset( $role['value'] ) || ! isset( $role['label'] ) ) {
                add_settings_error(
                    'rwp_caption_writer_settings',
                    'roles_config_error',
                    __( 'Each role must have at least "value" and "label" fields.', 'rwp-creator-suite' ),
                    'error'
                );
                return '';
            }
            
            $sanitized_role = array(
                'value' => sanitize_key( $role['value'] ),
                'label' => sanitize_text_field( $role['label'] )
            );
            
            // Add optional description
            if ( isset( $role['description'] ) ) {
                $sanitized_role['description'] = sanitize_text_field( $role['description'] );
            }
            
            $sanitized_roles[] = $sanitized_role;
        }
        
        // Check for duplicate values
        $values = array_column( $sanitized_roles, 'value' );
        if ( count( $values ) !== count( array_unique( $values ) ) ) {
            add_settings_error(
                'rwp_caption_writer_settings',
                'roles_config_error',
                __( 'Duplicate role values found. Each role must have a unique value.', 'rwp-creator-suite' ),
                'error'
            );
            return '';
        }
        
        return wp_json_encode( $sanitized_roles, JSON_PRETTY_PRINT );
    }
    
    /**
     * Sanitize prompts configuration.
     */
    public function sanitize_prompts_config( $value ) {
        $value = sanitize_textarea_field( trim( $value ) );
        
        // If empty, return empty string to use defaults
        if ( empty( $value ) ) {
            return '';
        }
        
        // Try to decode JSON
        $decoded = json_decode( $value, true );
        
        // If invalid JSON, add error and return empty
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            add_settings_error(
                'rwp_caption_writer_settings',
                'prompts_config_error',
                __( 'Invalid JSON format in AI prompts configuration. Please check the syntax.', 'rwp-creator-suite' ),
                'error'
            );
            return '';
        }
        
        // Validate structure
        if ( ! is_array( $decoded ) ) {
            add_settings_error(
                'rwp_caption_writer_settings',
                'prompts_config_error',
                __( 'AI prompts configuration must be an object with system_messages, tone_descriptions, platform_guidance, and prompt_templates.', 'rwp-creator-suite' ),
                'error'
            );
            return '';
        }
        
        // Required sections
        $required_sections = array( 'system_messages', 'tone_descriptions', 'platform_guidance', 'prompt_templates' );
        foreach ( $required_sections as $section ) {
            if ( ! isset( $decoded[ $section ] ) || ! is_array( $decoded[ $section ] ) ) {
                add_settings_error(
                    'rwp_caption_writer_settings',
                    'prompts_config_error',
                    sprintf( __( 'Missing or invalid "%s" section in AI prompts configuration.', 'rwp-creator-suite' ), $section ),
                    'error'
                );
                return '';
            }
        }
        
        // Sanitize the configuration
        $sanitized_config = array(
            'system_messages' => array(),
            'tone_descriptions' => array(),
            'platform_guidance' => array(),
            'prompt_templates' => array()
        );
        
        // Sanitize system messages
        foreach ( $decoded['system_messages'] as $context => $message ) {
            $sanitized_config['system_messages'][ sanitize_key( $context ) ] = sanitize_textarea_field( $message );
        }
        
        // Sanitize tone descriptions
        foreach ( $decoded['tone_descriptions'] as $tone => $description ) {
            $sanitized_config['tone_descriptions'][ sanitize_key( $tone ) ] = sanitize_text_field( $description );
        }
        
        // Sanitize platform guidance
        foreach ( $decoded['platform_guidance'] as $platform => $guidance ) {
            $sanitized_config['platform_guidance'][ sanitize_key( $platform ) ] = sanitize_text_field( $guidance );
        }
        
        // Sanitize prompt templates
        foreach ( $decoded['prompt_templates'] as $template => $content ) {
            $sanitized_config['prompt_templates'][ sanitize_key( $template ) ] = sanitize_textarea_field( $content );
        }
        
        return wp_json_encode( $sanitized_config, JSON_PRETTY_PRINT );
    }
    
    /**
     * Sanitize platforms configuration.
     */
    public function sanitize_platforms_config( $value ) {
        $value = sanitize_textarea_field( trim( $value ) );
        
        // If empty, return empty string to use defaults
        if ( empty( $value ) ) {
            return '';
        }
        
        // Try to decode JSON
        $decoded = json_decode( $value, true );
        
        // If invalid JSON, add error and return empty
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            add_settings_error(
                'rwp_caption_writer_settings',
                'platforms_config_error',
                __( 'Invalid JSON format in platforms configuration. Please check the syntax.', 'rwp-creator-suite' ),
                'error'
            );
            return '';
        }
        
        // Validate structure
        if ( ! is_array( $decoded ) ) {
            add_settings_error(
                'rwp_caption_writer_settings',
                'platforms_config_error',
                __( 'Platforms configuration must be an array of platform objects.', 'rwp-creator-suite' ),
                'error'
            );
            return '';
        }
        
        // Validate each platform
        $sanitized_platforms = array();
        foreach ( $decoded as $platform ) {
            if ( ! is_array( $platform ) || ! isset( $platform['key'] ) || ! isset( $platform['label'] ) || ! isset( $platform['character_limit'] ) ) {
                add_settings_error(
                    'rwp_caption_writer_settings',
                    'platforms_config_error',
                    __( 'Each platform must have at least "key", "label", and "character_limit" fields.', 'rwp-creator-suite' ),
                    'error'
                );
                return '';
            }
            
            $sanitized_platform = array(
                'key' => sanitize_key( $platform['key'] ),
                'label' => sanitize_text_field( $platform['label'] ),
                'character_limit' => absint( $platform['character_limit'] )
            );
            
            // Add optional fields
            if ( isset( $platform['icon_class'] ) ) {
                $sanitized_platform['icon_class'] = sanitize_html_class( $platform['icon_class'] );
            }
            
            if ( isset( $platform['description'] ) ) {
                $sanitized_platform['description'] = sanitize_text_field( $platform['description'] );
            }
            
            $sanitized_platforms[] = $sanitized_platform;
        }
        
        // Check for duplicate keys
        $keys = array_column( $sanitized_platforms, 'key' );
        if ( count( $keys ) !== count( array_unique( $keys ) ) ) {
            add_settings_error(
                'rwp_caption_writer_settings',
                'platforms_config_error',
                __( 'Duplicate platform keys found. Each platform must have a unique key.', 'rwp-creator-suite' ),
                'error'
            );
            return '';
        }
        
        return wp_json_encode( $sanitized_platforms, JSON_PRETTY_PRINT );
    }
    
    /**
     * Get roles configuration (with fallback to defaults).
     */
    public static function get_roles_config() {
        $custom_roles = get_option( 'rwp_creator_suite_custom_roles', '' );
        
        if ( empty( $custom_roles ) ) {
            // Return default roles
            $instance = new self();
            $default_json = $instance->get_default_roles();
            return json_decode( $default_json, true );
        }
        
        $decoded = json_decode( $custom_roles, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
            // Fallback to defaults if invalid
            $instance = new self();
            $default_json = $instance->get_default_roles();
            return json_decode( $default_json, true );
        }
        
        return $decoded;
    }
    
    /**
     * Get prompts configuration (with fallback to defaults).
     */
    public static function get_prompts_config() {
        $custom_prompts = get_option( 'rwp_creator_suite_ai_prompts', '' );
        
        if ( empty( $custom_prompts ) ) {
            // Return default prompts
            $instance = new self();
            $default_json = $instance->get_default_prompts();
            return json_decode( $default_json, true );
        }
        
        $decoded = json_decode( $custom_prompts, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
            // Fallback to defaults if invalid
            $instance = new self();
            $default_json = $instance->get_default_prompts();
            return json_decode( $default_json, true );
        }
        
        return $decoded;
    }
    
    /**
     * Get platforms configuration (with fallback to defaults).
     */
    public static function get_platforms_config() {
        $custom_platforms = get_option( 'rwp_creator_suite_custom_platforms', '' );
        
        if ( empty( $custom_platforms ) ) {
            // Return default platforms
            $instance = new self();
            $default_json = $instance->get_default_platforms();
            return json_decode( $default_json, true );
        }
        
        $decoded = json_decode( $custom_platforms, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
            // Fallback to defaults if invalid
            $instance = new self();
            $default_json = $instance->get_default_platforms();
            return json_decode( $default_json, true );
        }
        
        return $decoded;
    }
}