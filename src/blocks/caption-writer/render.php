<?php
/**
 * Caption Writer block render template
 * 
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

// Extract attributes
$platforms = $attributes['platforms'] ?? ['instagram'];
$tone = $attributes['tone'] ?? 'casual';
$selected_template = $attributes['selectedTemplate'] ?? '';
$final_caption = $attributes['finalCaption'] ?? '';
$show_preview = $attributes['showPreview'] ?? false;

// Generate unique ID for this instance
$unique_id = wp_unique_id( 'rwp-caption-writer-' );

// Get block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes( array(
    'id' => $unique_id,
    'class' => 'rwp-caption-writer-container',
    'data-platforms' => esc_attr( implode( ',', $platforms ) ),
    'data-tone' => esc_attr( $tone ),
    'data-config' => esc_attr( wp_json_encode( array(
        'platforms' => array_map( 'sanitize_text_field', $platforms ),
        'tone' => sanitize_text_field( $tone ),
        'selectedTemplate' => sanitize_text_field( $selected_template ),
        'finalCaption' => sanitize_textarea_field( $final_caption ),
        'isLoggedIn' => (bool) is_user_logged_in(),
        'userId' => absint( get_current_user_id() ),
    ) ) ),
) );
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="caption-writer-app">
        <div class="caption-writer-header">
            <div class="platform-selection">
                <span class="platform-label"><?php esc_html_e( 'Target Platforms:', 'rwp-creator-suite' ); ?></span>
                <div class="platform-checkboxes">
                    <?php
                    $available_platforms = array(
                        'instagram' => 'Instagram',
                        'tiktok' => 'TikTok/Reels',
                        'twitter' => 'Twitter/X',
                        'linkedin' => 'LinkedIn',
                        'facebook' => 'Facebook'
                    );
                    foreach ( $available_platforms as $platform_key => $platform_label ) :
                        $checked = in_array( $platform_key, $platforms );
                    ?>
                        <label class="platform-checkbox">
                            <input 
                                type="checkbox" 
                                value="<?php echo esc_attr( $platform_key ); ?>"
                                <?php checked( $checked ); ?>
                                data-platform-checkbox="<?php echo esc_attr( $platform_key ); ?>"
                            >
                            <span class="platform-label-text"><?php echo esc_html( $platform_label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="caption-writer-tabs">
            <button class="tab-button<?php echo is_user_logged_in() ? ' active' : ''; ?>" data-tab="generator">
                <?php esc_html_e( 'AI Generator', 'rwp-creator-suite' ); ?>
            </button>
            <button class="tab-button<?php echo ! is_user_logged_in() ? ' active' : ''; ?>" data-tab="templates">
                <?php esc_html_e( 'Templates', 'rwp-creator-suite' ); ?>
            </button>
            <?php if ( is_user_logged_in() ) : ?>
                <button class="tab-button" data-tab="favorites">
                    <?php esc_html_e( 'Favorites', 'rwp-creator-suite' ); ?>
                </button>
            <?php endif; ?>
        </div>
        
        <!-- AI Generator Tab -->
        <div class="tab-content<?php echo is_user_logged_in() ? ' active' : ''; ?>" data-content="generator">
            <?php if ( is_user_logged_in() ) : ?>
                <div class="ai-generator-section">
                    <div class="input-section">
                        <label for="<?php echo esc_attr( $unique_id . '-description' ); ?>">
                            <?php esc_html_e( 'Describe your content:', 'rwp-creator-suite' ); ?>
                        </label>
                        <textarea 
                            id="<?php echo esc_attr( $unique_id . '-description' ); ?>"
                            class="content-description"
                            placeholder="<?php esc_attr_e( 'e.g., Photo of a golden retriever in a field of flowers', 'rwp-creator-suite' ); ?>"
                            rows="3"
                            data-description
                        ></textarea>
                        
                        <div class="tone-selector">
                            <label for="<?php echo esc_attr( $unique_id . '-tone' ); ?>">
                                <?php esc_html_e( 'Tone:', 'rwp-creator-suite' ); ?>
                            </label>
                            <select id="<?php echo esc_attr( $unique_id . '-tone' ); ?>" data-tone>
                                <option value="casual" <?php selected( $tone, 'casual' ); ?>>
                                    <?php esc_html_e( 'Casual', 'rwp-creator-suite' ); ?>
                                </option>
                                <option value="witty" <?php selected( $tone, 'witty' ); ?>>
                                    <?php esc_html_e( 'Witty', 'rwp-creator-suite' ); ?>
                                </option>
                                <option value="inspirational" <?php selected( $tone, 'inspirational' ); ?>>
                                    <?php esc_html_e( 'Inspirational', 'rwp-creator-suite' ); ?>
                                </option>
                                <option value="question" <?php selected( $tone, 'question' ); ?>>
                                    <?php esc_html_e( 'Question-based', 'rwp-creator-suite' ); ?>
                                </option>
                                <option value="professional" <?php selected( $tone, 'professional' ); ?>>
                                    <?php esc_html_e( 'Professional', 'rwp-creator-suite' ); ?>
                                </option>
                            </select>
                        </div>
                        
                        <button class="generate-btn btn-primary" data-generate>
                            <?php esc_html_e( 'Generate Captions', 'rwp-creator-suite' ); ?>
                        </button>
                        
                        <div class="quota-info" data-quota-display style="display: none;">
                            <span class="quota-text"></span>
                        </div>
                    </div>
                    
                    <div class="generated-captions-container" data-captions style="display: none;">
                        <div class="captions-list"></div>
                    </div>
                </div>
            <?php else : ?>
                <!-- Guest Teaser for AI Generator -->
                <div class="ai-generator-guest-teaser">
                    <div class="guest-teaser-content">
                        <div class="guest-teaser-icon">🤖</div>
                        <div class="guest-teaser-title"><?php esc_html_e( 'AI Caption Generator', 'rwp-creator-suite' ); ?></div>
                        <p><?php esc_html_e( 'Unlock the power of AI to generate engaging captions for your content. Get multiple caption options in different tones tailored to your platform.', 'rwp-creator-suite' ); ?></p>
                        
                        <div class="guest-teaser-benefits">
                            <div class="guest-benefit-item">
                                <div class="guest-benefit-icon">✨</div>
                                <?php esc_html_e( 'Generate multiple caption variations instantly', 'rwp-creator-suite' ); ?>
                            </div>
                            <div class="guest-benefit-item">
                                <div class="guest-benefit-icon">🎯</div>
                                <?php esc_html_e( 'Choose from different tones and styles', 'rwp-creator-suite' ); ?>
                            </div>
                            <div class="guest-benefit-item">
                                <div class="guest-benefit-icon">📱</div>
                                <?php esc_html_e( 'Optimized for each social media platform', 'rwp-creator-suite' ); ?>
                            </div>
                            <div class="guest-benefit-item">
                                <div class="guest-benefit-icon">💾</div>
                                <?php esc_html_e( 'Save favorites and create custom templates', 'rwp-creator-suite' ); ?>
                            </div>
                        </div>
                        
                        <div class="guest-teaser-cta">
                            <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="guest-cta-button">
                                <?php esc_html_e( 'Get Free Access', 'rwp-creator-suite' ); ?>
                            </a>
                            <p class="guest-login-note">
                                <?php esc_html_e( 'Already have an account?', 'rwp-creator-suite' ); ?> 
                                <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
                                    <?php esc_html_e( 'Login here', 'rwp-creator-suite' ); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Templates Tab -->
        <div class="tab-content<?php echo ! is_user_logged_in() ? ' active' : ''; ?>" data-content="templates">
            <div class="template-library-section">
                <div class="template-filters">
                    <select data-template-category>
                        <option value="all"><?php esc_html_e( 'All Categories', 'rwp-creator-suite' ); ?></option>
                        <option value="business"><?php esc_html_e( 'Business', 'rwp-creator-suite' ); ?></option>
                        <option value="personal"><?php esc_html_e( 'Personal', 'rwp-creator-suite' ); ?></option>
                        <option value="engagement"><?php esc_html_e( 'Engagement', 'rwp-creator-suite' ); ?></option>
                    </select>
                </div>
                
                <div class="templates-grid" data-templates-grid>
                    <!-- Templates will be loaded via JavaScript -->
                </div>
            </div>
        </div>
        
        <?php if ( is_user_logged_in() ) : ?>
        <!-- Favorites Tab -->
        <div class="tab-content" data-content="favorites">
            <div class="favorites-section">
                <div class="favorites-list" data-favorites>
                    <!-- Favorites will be loaded via JavaScript -->
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Caption Output -->
        <div class="caption-output-section">
            <label for="<?php echo esc_attr( $unique_id . '-final-caption' ); ?>">
                <?php esc_html_e( 'Final Caption:', 'rwp-creator-suite' ); ?>
            </label>
            <textarea 
                id="<?php echo esc_attr( $unique_id . '-final-caption' ); ?>"
                class="final-caption"
                placeholder="<?php esc_attr_e( 'Your caption will appear here...', 'rwp-creator-suite' ); ?>"
                rows="8"
                data-final-caption
            ><?php echo esc_textarea( $final_caption ); ?></textarea>
            
            <div class="character-counter" data-multi-platform-counter>
                <div class="current-count" data-current-count>0</div>
                <div class="platform-limits">
                    <?php 
                    $character_limits = array(
                        'instagram' => 2200,
                        'tiktok' => 2200,
                        'twitter' => 280,
                        'linkedin' => 3000,
                        'facebook' => 63206
                    );
                    foreach ( $platforms as $platform ) : 
                        $limit = $character_limits[ $platform ] ?? 2200;
                    ?>
                        <div class="platform-limit-item" data-platform="<?php echo esc_attr( $platform ); ?>" data-limit="<?php echo esc_attr( $limit ); ?>">
                            <span class="character-limit"><?php echo esc_html( $limit ); ?></span>
                            <span class="platform-name"><?php echo esc_html( ucfirst( $platform ) ); ?></span>
                            <span class="over-limit-badge" style="display: none;">Over limit!</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="output-actions">
                <button class="btn-secondary" data-copy>
                    <?php esc_html_e( 'Copy Caption', 'rwp-creator-suite' ); ?>
                </button>
                <?php if ( is_user_logged_in() ) : ?>
                    <button class="btn-secondary" data-save-favorite>
                        <?php esc_html_e( 'Save to Favorites', 'rwp-creator-suite' ); ?>
                    </button>
                <?php else : ?>
                    <p class="login-prompt">
                        <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
                            <?php esc_html_e( 'Login', 'rwp-creator-suite' ); ?>
                        </a>
                        <?php esc_html_e( ' or ', 'rwp-creator-suite' ); ?>
                        <a href="<?php echo esc_url( wp_registration_url() ); ?>">
                            <?php esc_html_e( 'register', 'rwp-creator-suite' ); ?>
                        </a>
                        <?php esc_html_e( 'to save favorites', 'rwp-creator-suite' ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Loading and error states -->
        <div class="caption-writer-loading" data-loading style="display: none;">
            <div class="loading-spinner"></div>
            <p><?php esc_html_e( 'Generating captions...', 'rwp-creator-suite' ); ?></p>
        </div>
        
        <div class="caption-writer-error" data-error style="display: none;">
            <div class="error-message"></div>
        </div>
    </div>
</div>