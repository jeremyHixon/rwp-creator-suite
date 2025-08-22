<?php
/**
 * Enhanced Content Repurposer Block Frontend Rendering
 * 
 * Uses Phase 2 enhanced components with floating inputs, smart textarea,
 * modern result cards, and enhanced guest teaser.
 * 
 * @var array $attributes Block attributes
 * @var string $content Block content
 * @var WP_Block $block Block instance
 */

defined( 'ABSPATH' ) || exit;

// Get block attributes with defaults
$platforms = $attributes['platforms'] ?? array( 'twitter', 'linkedin' );
$tone = $attributes['tone'] ?? 'professional';
$show_usage_stats = $attributes['showUsageStats'] ?? true;
$align = $attributes['align'] ?? '';

// Build CSS classes
$wrapper_classes = array( 'wp-block-rwp-creator-suite-content-repurposer', 'rwp-enhanced' );
if ( ! empty( $align ) ) {
    $wrapper_classes[] = "align{$align}";
}

// Generate unique ID for this block instance
$block_id = uniqid( 'rwp-content-repurposer-' );

?>
<div 
    class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" 
    id="<?php echo esc_attr( $block_id ); ?>"
    data-platforms="<?php echo esc_attr( wp_json_encode( $platforms ) ); ?>"
    data-tone="<?php echo esc_attr( $tone ); ?>"
    data-show-usage="<?php echo esc_attr( $show_usage_stats ? '1' : '0' ); ?>"
>
    <div class="rwp-content-repurposer-container">
        <!-- Enhanced Logged-in user form -->
        <div class="rwp-repurposer-form rwp-repurposer-logged-in" style="display: none;">
            <div class="rwp-enhanced-form">
                <!-- Smart Textarea with floating label -->
                <div class="rwp-form-group">
                    <div class="smart-textarea-container" data-component="SmartTextarea">
                        <textarea 
                            id="<?php echo esc_attr( $block_id ); ?>-content"
                            class="smart-textarea rwp-content-input"
                            placeholder=" "
                            maxlength="10000"
                            rows="6"
                            data-show-word-count="true"
                            data-show-character-count="true"
                        ></textarea>
                        <label for="<?php echo esc_attr( $block_id ); ?>-content" class="floating-label">
                            <?php esc_html_e( 'Your Content', 'rwp-creator-suite' ); ?>
                            <span class="floating-label-required">*</span>
                        </label>
                        <div class="textarea-footer">
                            <div class="textarea-counts">
                                <span class="word-count">0 words</span>
                                <span class="character-count">0 / 10,000 characters</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Platform Selection -->
                <div class="rwp-form-group rwp-platform-selection">
                    <label class="rwp-section-label">
                        <strong><?php esc_html_e( 'Target Platforms', 'rwp-creator-suite' ); ?></strong>
                        <span class="rwp-section-description">
                            <?php esc_html_e( 'Select the platforms you want to optimize content for', 'rwp-creator-suite' ); ?>
                        </span>
                    </label>
                    <div class="rwp-platform-grid">
                        <?php
                        $available_platforms = RWP_Creator_Suite_Caption_Admin_Settings::get_platforms_config();
                        foreach ( $available_platforms as $platform_config ) :
                            $platform_key = $platform_config['key'];
                            $platform_label = $platform_config['label'];
                            $checked = in_array( $platform_key, $platforms, true );
                            $platform_icon = $this->get_platform_icon( $platform_key );
                        ?>
                            <label class="rwp-platform-card <?php echo $checked ? 'rwp-platform-card--selected' : ''; ?>">
                                <input 
                                    type="checkbox" 
                                    name="platforms[]" 
                                    value="<?php echo esc_attr( $platform_key ); ?>"
                                    <?php checked( $checked ); ?>
                                    class="rwp-platform-checkbox"
                                >
                                <div class="rwp-platform-card-content">
                                    <span class="rwp-platform-icon"><?php echo esc_html( $platform_icon ); ?></span>
                                    <span class="rwp-platform-name"><?php echo esc_html( $platform_label ); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Enhanced Tone Selection -->
                <div class="rwp-form-group">
                    <div class="floating-input-container" data-component="FloatingInput">
                        <select id="<?php echo esc_attr( $block_id ); ?>-tone" class="floating-input rwp-tone-select">
                            <?php
                            $roles_config = RWP_Creator_Suite_Caption_Admin_Settings::get_roles_config();
                            foreach ( $roles_config as $role ) :
                                $selected = selected( $tone, $role['value'], false );
                                $title = isset( $role['description'] ) ? ' title="' . esc_attr( $role['description'] ) . '"' : '';
                            ?>
                                <option value="<?php echo esc_attr( $role['value'] ); ?>"<?php echo wp_kses_post( $selected ); ?><?php echo wp_kses_post( $title ); ?>>
                                    <?php echo esc_html( $role['label'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="<?php echo esc_attr( $block_id ); ?>-tone" class="floating-label">
                            <?php esc_html_e( 'Content Tone', 'rwp-creator-suite' ); ?>
                        </label>
                    </div>
                </div>

                <!-- Enhanced Form Actions -->
                <div class="rwp-form-actions">
                    <div class="rwp-action-buttons">
                        <button 
                            type="button" 
                            class="loading-button loading-button--primary rwp-repurpose-button"
                            data-component="LoadingButton"
                        >
                            <span class="button-text">
                                <?php esc_html_e( 'Repurpose Content', 'rwp-creator-suite' ); ?>
                            </span>
                        </button>
                    </div>
                    
                    <?php if ( $show_usage_stats ) : ?>
                        <div class="rwp-usage-stats" data-quota-display style="display: none;">
                            <span class="quota-text"></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Enhanced Guest user form -->
        <div class="rwp-repurposer-form rwp-repurposer-guest" style="display: none;">
            <div class="rwp-guest-attempts-banner">
                <div class="rwp-attempts-info">
                    <span class="rwp-attempts-icon">✨</span>
                    <div class="rwp-attempts-text">
                        <span class="rwp-attempts-count">3</span> <?php esc_html_e( 'free attempts remaining', 'rwp-creator-suite' ); ?>
                    </div>
                </div>
            </div>
            
            <div class="rwp-enhanced-form">
                <!-- Smart Textarea for guests -->
                <div class="rwp-form-group">
                    <div class="smart-textarea-container" data-component="SmartTextarea">
                        <textarea 
                            id="<?php echo esc_attr( $block_id ); ?>-guest-content"
                            class="smart-textarea rwp-content-input rwp-guest-content-input"
                            placeholder=" "
                            maxlength="10000"
                            rows="6"
                            data-show-word-count="true"
                            data-show-character-count="true"
                        ></textarea>
                        <label for="<?php echo esc_attr( $block_id ); ?>-guest-content" class="floating-label">
                            <?php esc_html_e( 'Your Content', 'rwp-creator-suite' ); ?>
                            <span class="floating-label-required">*</span>
                        </label>
                        <div class="textarea-footer">
                            <div class="textarea-counts">
                                <span class="word-count">0 words</span>
                                <span class="character-count">0 / 10,000 characters</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Tone Selection for guests -->
                <div class="rwp-form-group">
                    <div class="floating-input-container" data-component="FloatingInput">
                        <select id="<?php echo esc_attr( $block_id ); ?>-guest-tone" class="floating-input rwp-tone-select rwp-guest-tone-select">
                            <?php
                            foreach ( $roles_config as $role ) :
                                $selected = selected( $tone, $role['value'], false );
                                $title = isset( $role['description'] ) ? ' title="' . esc_attr( $role['description'] ) . '"' : '';
                            ?>
                                <option value="<?php echo esc_attr( $role['value'] ); ?>"<?php echo wp_kses_post( $selected ); ?><?php echo wp_kses_post( $title ); ?>>
                                    <?php echo esc_html( $role['label'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="<?php echo esc_attr( $block_id ); ?>-guest-tone" class="floating-label">
                            <?php esc_html_e( 'Content Tone', 'rwp-creator-suite' ); ?>
                        </label>
                    </div>
                </div>

                <!-- Enhanced Guest Form Actions -->
                <div class="rwp-form-actions">
                    <div class="rwp-action-buttons">
                        <button 
                            type="button" 
                            class="loading-button loading-button--primary rwp-repurpose-button rwp-guest-repurpose-button"
                            data-component="LoadingButton"
                        >
                            <span class="button-text">
                                <?php esc_html_e( 'Try Free Repurposing', 'rwp-creator-suite' ); ?>
                            </span>
                        </button>
                    </div>

                    <div class="rwp-guest-upgrade-hint">
                        <div class="rwp-hint-content">
                            <span class="rwp-hint-icon">💡</span>
                            <p><?php esc_html_e( 'This free trial includes full Twitter generation and previews of other platforms. Sign up for unlimited access to all features!', 'rwp-creator-suite' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Guest Teaser -->
        <div class="rwp-repurposer-guest-teaser">
            <div class="guest-teaser-enhanced" data-component="EnhancedGuestTeaser">
                <div class="teaser-content">
                    <div class="teaser-icon">
                        <svg width="32" height="32" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    
                    <h2 class="teaser-title">
                        <?php esc_html_e( 'Unlock Professional Creator Tools', 'rwp-creator-suite' ); ?>
                    </h2>
                    
                    <p class="teaser-subtitle">
                        <?php esc_html_e( 'Transform your long-form content into engaging posts for multiple social media platforms. Let AI optimize your content for maximum reach and engagement.', 'rwp-creator-suite' ); ?>
                    </p>
                    
                    <div class="feature-grid">
                        <div class="feature-item">
                            <div class="feature-icon">🔄</div>
                            <h3 class="feature-title"><?php esc_html_e( 'Multi-Platform Content', 'rwp-creator-suite' ); ?></h3>
                            <p class="feature-description"><?php esc_html_e( 'Repurpose content for Twitter, LinkedIn, Facebook, and Instagram', 'rwp-creator-suite' ); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🎯</div>
                            <h3 class="feature-title"><?php esc_html_e( 'Smart Tone Control', 'rwp-creator-suite' ); ?></h3>
                            <p class="feature-description"><?php esc_html_e( 'Choose from professional, casual, engaging, or informative tones', 'rwp-creator-suite' ); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">⚡</div>
                            <h3 class="feature-title"><?php esc_html_e( 'Multiple Variations', 'rwp-creator-suite' ); ?></h3>
                            <p class="feature-description"><?php esc_html_e( 'Get multiple variations optimized for each platform\'s audience', 'rwp-creator-suite' ); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">📊</div>
                            <h3 class="feature-title"><?php esc_html_e( 'Character Optimization', 'rwp-creator-suite' ); ?></h3>
                            <p class="feature-description"><?php esc_html_e( 'Character count optimization for maximum platform compatibility', 'rwp-creator-suite' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="teaser-cta">
                        <button class="cta-button" onclick="window.location.href='<?php echo esc_url( wp_registration_url() ); ?>'">
                            <span class="cta-button-text"><?php esc_html_e( 'Get Started Free', 'rwp-creator-suite' ); ?></span>
                        </button>
                        
                        <p class="login-prompt">
                            <?php esc_html_e( 'Already have an account?', 'rwp-creator-suite' ); ?>{' '}
                            <button class="login-link" onclick="window.location.href='<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>'">
                                <?php esc_html_e( 'Sign in', 'rwp-creator-suite' ); ?>
                            </button>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Guest Exhausted Message -->
        <div class="rwp-guest-exhausted-message" style="display: none;">
            <div class="guest-teaser-enhanced guest-teaser-enhanced--exhausted" data-component="EnhancedGuestTeaser">
                <div class="teaser-content">
                    <div class="teaser-icon">
                        <span style="font-size: 32px;">🚀</span>
                    </div>
                    
                    <h2 class="teaser-title">
                        <?php esc_html_e( 'Free Attempts Used', 'rwp-creator-suite' ); ?>
                    </h2>
                    
                    <p class="teaser-subtitle">
                        <?php esc_html_e( 'You\'ve used all 3 free attempts! Ready to unlock unlimited content repurposing?', 'rwp-creator-suite' ); ?>
                    </p>
                    
                    <div class="feature-grid">
                        <div class="feature-item">
                            <div class="feature-icon">♾️</div>
                            <h3 class="feature-title"><?php esc_html_e( 'Unlimited Attempts', 'rwp-creator-suite' ); ?></h3>
                            <p class="feature-description"><?php esc_html_e( 'No limits on content generation', 'rwp-creator-suite' ); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">📱</div>
                            <h3 class="feature-title"><?php esc_html_e( 'Full Platform Access', 'rwp-creator-suite' ); ?></h3>
                            <p class="feature-description"><?php esc_html_e( 'Complete LinkedIn, Facebook & Instagram content', 'rwp-creator-suite' ); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">💾</div>
                            <h3 class="feature-title"><?php esc_html_e( 'Save & Manage', 'rwp-creator-suite' ); ?></h3>
                            <p class="feature-description"><?php esc_html_e( 'Save and manage your content library', 'rwp-creator-suite' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="teaser-cta">
                        <button class="cta-button" onclick="window.location.href='<?php echo esc_url( wp_registration_url() ); ?>'">
                            <span class="cta-button-text"><?php esc_html_e( 'Create Free Account', 'rwp-creator-suite' ); ?></span>
                        </button>
                        
                        <p class="login-prompt">
                            <?php esc_html_e( 'Already have an account?', 'rwp-creator-suite' ); ?>{' '}
                            <button class="login-link" onclick="window.location.href='<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>'">
                                <?php esc_html_e( 'Sign in', 'rwp-creator-suite' ); ?>
                            </button>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Results Container -->
        <div class="rwp-results-container" style="display: none;">
            <div class="rwp-results-header">
                <h4 class="rwp-results-title">
                    <?php esc_html_e( 'Repurposed Content', 'rwp-creator-suite' ); ?>
                </h4>
            </div>
            
            <!-- Enhanced Results Grid -->
            <div class="results-grid" data-component="ResultsGrid">
                <!-- Results will be populated by JavaScript using ResultCard components -->
            </div>
            
            <!-- Guest upgrade prompts in results -->
            <div id="rwp-upgrade-cta" class="rwp-guest-results-upgrade" style="display: none;">
                <div class="rwp-upgrade-card">
                    <div class="rwp-upgrade-icon">✨</div>
                    <h5><?php esc_html_e( 'Want full content for all platforms?', 'rwp-creator-suite' ); ?></h5>
                    <p><?php esc_html_e( 'Sign up for free to get complete LinkedIn, Facebook, and Instagram versions of your content!', 'rwp-creator-suite' ); ?></p>
                    <div class="rwp-upgrade-actions">
                        <button 
                            class="loading-button loading-button--primary"
                            onclick="window.location.href='<?php echo esc_url( wp_registration_url() ); ?>'"
                        >
                            <span class="button-text"><?php esc_html_e( 'Sign Up Free', 'rwp-creator-suite' ); ?></span>
                        </button>
                        <button 
                            class="loading-button loading-button--ghost"
                            onclick="window.location.href='<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>'"
                        >
                            <span class="button-text"><?php esc_html_e( 'Login', 'rwp-creator-suite' ); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Loading State -->
        <div class="rwp-loading-container" style="display: none;">
            <div class="enhanced-loading-states enhanced-loading-states--card" data-component="EnhancedLoadingStates">
                <!-- Loading content will be managed by JavaScript -->
            </div>
        </div>

        <!-- Enhanced Error Display -->
        <div class="rwp-error-message" style="display: none;">
            <div class="rwp-error-card">
                <div class="rwp-error-icon">⚠️</div>
                <div class="rwp-error-content">
                    <!-- Error messages will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to get platform icons
function get_platform_icon( $platform ) {
    $icons = [
        'twitter' => '𝕏',
        'linkedin' => '💼',
        'facebook' => '📘',
        'instagram' => '📷',
        'tiktok' => '🎵',
        'youtube' => '📺',
        'pinterest' => '📌',
    ];
    
    return $icons[ $platform ] ?? '📱';
}
?>