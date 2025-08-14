<?php
/**
 * Content Repurposer Block Frontend Rendering
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
$wrapper_classes = array( 'wp-block-rwp-creator-suite-content-repurposer' );
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
        <div class="rwp-repurposer-form">
            <div class="rwp-form-group">
                <label for="<?php echo esc_attr( $block_id ); ?>-content" class="rwp-form-label">
                    <?php esc_html_e( 'Your Content', 'rwp-creator-suite' ); ?>
                </label>
                <textarea 
                    id="<?php echo esc_attr( $block_id ); ?>-content"
                    class="rwp-content-input"
                    placeholder="<?php esc_attr_e( 'Paste your long-form content here (blog post, article, etc.)...', 'rwp-creator-suite' ); ?>"
                    maxlength="10000"
                    rows="8"
                ></textarea>
                <div class="rwp-character-count">
                    <span class="rwp-count-current">0</span> / 10,000 <?php esc_html_e( 'characters', 'rwp-creator-suite' ); ?>
                </div>
            </div>

            <div class="rwp-form-group rwp-platform-selection">
                <label class="rwp-form-label">
                    <?php esc_html_e( 'Target Platforms', 'rwp-creator-suite' ); ?>
                </label>
                <div class="rwp-platform-checkboxes">
                    <?php
                    $platform_labels = array(
                        'twitter' => __( 'Twitter', 'rwp-creator-suite' ),
                        'linkedin' => __( 'LinkedIn', 'rwp-creator-suite' ),
                        'facebook' => __( 'Facebook', 'rwp-creator-suite' ),
                        'instagram' => __( 'Instagram', 'rwp-creator-suite' ),
                    );
                    
                    foreach ( $platform_labels as $platform => $label ) :
                        $checked = in_array( $platform, $platforms, true );
                    ?>
                        <label class="rwp-platform-option">
                            <input 
                                type="checkbox" 
                                name="platforms[]" 
                                value="<?php echo esc_attr( $platform ); ?>"
                                <?php checked( $checked ); ?>
                                class="rwp-platform-checkbox"
                            >
                            <span class="rwp-platform-label"><?php echo esc_html( $label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rwp-form-group">
                <label for="<?php echo esc_attr( $block_id ); ?>-tone" class="rwp-form-label">
                    <?php esc_html_e( 'Tone', 'rwp-creator-suite' ); ?>
                </label>
                <select id="<?php echo esc_attr( $block_id ); ?>-tone" class="rwp-tone-select">
                    <option value="professional" <?php selected( $tone, 'professional' ); ?>>
                        <?php esc_html_e( 'Professional', 'rwp-creator-suite' ); ?>
                    </option>
                    <option value="casual" <?php selected( $tone, 'casual' ); ?>>
                        <?php esc_html_e( 'Casual', 'rwp-creator-suite' ); ?>
                    </option>
                    <option value="engaging" <?php selected( $tone, 'engaging' ); ?>>
                        <?php esc_html_e( 'Engaging', 'rwp-creator-suite' ); ?>
                    </option>
                    <option value="informative" <?php selected( $tone, 'informative' ); ?>>
                        <?php esc_html_e( 'Informative', 'rwp-creator-suite' ); ?>
                    </option>
                </select>
            </div>

            <div class="rwp-form-actions">
                <button type="button" class="rwp-repurpose-button rwp-button-primary">
                    <span class="rwp-button-text">
                        <?php esc_html_e( 'Repurpose Content', 'rwp-creator-suite' ); ?>
                    </span>
                    <span class="rwp-button-loading" style="display: none;">
                        <?php esc_html_e( 'Repurposing...', 'rwp-creator-suite' ); ?>
                    </span>
                </button>
            </div>
        </div>

        <?php if ( $show_usage_stats ) : ?>
            <div class="rwp-usage-stats" style="display: none;">
                <h4><?php esc_html_e( 'Usage Statistics', 'rwp-creator-suite' ); ?></h4>
                <div class="rwp-stats-content">
                    <!-- Usage stats will be populated by JavaScript -->
                </div>
            </div>
        <?php endif; ?>

        <div class="rwp-results-container" style="display: none;">
            <h4 class="rwp-results-title">
                <?php esc_html_e( 'Repurposed Content', 'rwp-creator-suite' ); ?>
            </h4>
            <div class="rwp-results-content">
                <!-- Results will be populated by JavaScript -->
            </div>
        </div>

        <div class="rwp-error-message" style="display: none;">
            <div class="rwp-error-content">
                <!-- Error messages will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>