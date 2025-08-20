<?php
/**
 * Opportunity Alert Email Template
 * 
 * @var WP_User $user
 * @var array   $opportunity
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( sprintf( __( 'Creator Opportunity: %s', 'rwp-creator-suite' ), $opportunity['title'] ) ); ?></title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        .container { 
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .opportunity-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 30px;
            position: relative;
        }
        .opportunity-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }
        .opportunity-title {
            font-size: 28px;
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        .opportunity-tagline {
            font-size: 16px;
            opacity: 0.9;
        }
        .urgency-banner {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            margin: 20px 0;
            font-weight: 500;
        }
        .opportunity-details {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
        }
        .opportunity-description {
            font-size: 18px;
            color: #374151;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        .impact-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
        }
        .metric-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        .metric-high { color: #10b981; }
        .metric-medium { color: #3b82f6; }
        .metric-low { color: #6b7280; }
        .metric-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
        }
        .action-section {
            background: #f9fafb;
            padding: 25px;
            border-radius: 8px;
            margin: 25px 0;
        }
        .action-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        .action-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #3b82f6;
            display: flex;
            align-items: center;
        }
        .action-number {
            background: #3b82f6;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .trending-data {
            background: #ecfccb;
            border: 1px solid #84cc16;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .hashtag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 15px 0;
        }
        .hashtag {
            background: #dbeafe;
            color: #1e40af;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
        }
        .time-sensitive {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #dc2626;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            margin: 20px 0;
            font-weight: 500;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
            background: #f3f4f6;
            padding: 25px;
            border-radius: 8px;
        }
        .cta-primary {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 16px;
            margin: 5px;
        }
        .cta-secondary {
            display: inline-block;
            background: #6b7280;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 16px;
            margin: 5px;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="opportunity-header">
            <span class="opportunity-icon">💡</span>
            <h1 class="opportunity-title"><?php echo esc_html( $opportunity['title'] ); ?></h1>
            <div class="opportunity-tagline"><?php esc_html_e( 'A personalized growth opportunity just for you', 'rwp-creator-suite' ); ?></div>
        </div>

        <div style="font-size: 18px; margin-bottom: 20px; color: #374151;">
            <?php printf( esc_html__( 'Hi %s! We\'ve spotted an opportunity that could significantly boost your content performance.', 'rwp-creator-suite' ), esc_html( $user->display_name ) ); ?>
        </div>

        <?php if ( isset( $opportunity['impact'] ) && $opportunity['impact'] === 'High' ) : ?>
        <div class="urgency-banner">
            ⚡ <?php esc_html_e( 'High-Impact Opportunity - Act within 48 hours for maximum benefit!', 'rwp-creator-suite' ); ?>
        </div>
        <?php endif; ?>

        <div class="opportunity-details">
            <div class="opportunity-description">
                <?php echo esc_html( $opportunity['description'] ); ?>
            </div>

            <div class="impact-metrics">
                <div class="metric-card">
                    <span class="metric-value metric-<?php echo esc_attr( strtolower( $opportunity['impact'] ?? 'medium' ) ); ?>">
                        <?php echo esc_html( $opportunity['impact'] ?? 'Medium' ); ?>
                    </span>
                    <span class="metric-label"><?php esc_html_e( 'Impact', 'rwp-creator-suite' ); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-value metric-<?php echo esc_attr( strtolower( $opportunity['effort'] ?? 'medium' ) ); ?>">
                        <?php echo esc_html( $opportunity['effort'] ?? 'Medium' ); ?>
                    </span>
                    <span class="metric-label"><?php esc_html_e( 'Effort Required', 'rwp-creator-suite' ); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-value metric-high">
                        <?php echo esc_html( $opportunity['confidence'] ?? '85%' ); ?>
                    </span>
                    <span class="metric-label"><?php esc_html_e( 'Confidence', 'rwp-creator-suite' ); ?></span>
                </div>
            </div>
        </div>

        <?php if ( $opportunity['type'] === 'hashtag_opportunity' && ! empty( $opportunity['data'] ) ) : ?>
        <div class="trending-data">
            <h3 style="margin-top: 0; color: #365314;"><?php esc_html_e( '🔥 Trending Hashtags for You', 'rwp-creator-suite' ); ?></h3>
            <p style="color: #4d7c0f; margin-bottom: 15px;">
                <?php esc_html_e( 'These hashtags are gaining momentum and align with your content style:', 'rwp-creator-suite' ); ?>
            </p>
            <div class="hashtag-list">
                <?php foreach ( array_slice( $opportunity['data'], 0, 6 ) as $hashtag ) : ?>
                    <span class="hashtag">
                        <?php echo esc_html( is_string( $hashtag ) ? '#' . $hashtag : '#trending' . ( array_search( $hashtag, $opportunity['data'] ) + 1 ) ); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $opportunity['type'] === 'platform_opportunity' && ! empty( $opportunity['data']['platform'] ) ) : ?>
        <div class="trending-data">
            <h3 style="margin-top: 0; color: #365314;">
                🚀 <?php echo esc_html( ucfirst( $opportunity['data']['platform'] ) ); ?> <?php esc_html_e( 'Expansion', 'rwp-creator-suite' ); ?>
            </h3>
            <p style="color: #4d7c0f;">
                <?php printf( 
                    esc_html__( 'Based on your content style, %s could be your next big platform. Here\'s why:', 'rwp-creator-suite' ),
                    esc_html( ucfirst( $opportunity['data']['platform'] ) )
                ); ?>
            </p>
            <ul style="color: #4d7c0f; margin: 15px 0;">
                <li><?php esc_html_e( 'Your content format aligns perfectly with platform trends', 'rwp-creator-suite' ); ?></li>
                <li><?php esc_html_e( 'Similar creators are seeing 40% more engagement', 'rwp-creator-suite' ); ?></li>
                <li><?php esc_html_e( 'Growing audience interested in your content niche', 'rwp-creator-suite' ); ?></li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="action-section">
            <h3 style="margin-top: 0; color: #1f2937;"><?php esc_html_e( 'Your Action Plan', 'rwp-creator-suite' ); ?></h3>
            
            <ul class="action-list">
                <?php if ( $opportunity['type'] === 'hashtag_opportunity' ) : ?>
                <li class="action-item">
                    <span class="action-number">1</span>
                    <div><?php esc_html_e( 'Research 2-3 of these trending hashtags in detail to understand the content style', 'rwp-creator-suite' ); ?></div>
                </li>
                <li class="action-item">
                    <span class="action-number">2</span>
                    <div><?php esc_html_e( 'Create content that naturally incorporates these hashtags', 'rwp-creator-suite' ); ?></div>
                </li>
                <li class="action-item">
                    <span class="action-number">3</span>
                    <div><?php esc_html_e( 'Monitor performance for 1 week and adjust strategy based on results', 'rwp-creator-suite' ); ?></div>
                </li>
                <?php elseif ( $opportunity['type'] === 'platform_opportunity' ) : ?>
                <li class="action-item">
                    <span class="action-number">1</span>
                    <div><?php printf( esc_html__( 'Set up your %s profile with optimized bio and branding', 'rwp-creator-suite' ), esc_html( $opportunity['data']['platform'] ) ); ?></div>
                </li>
                <li class="action-item">
                    <span class="action-number">2</span>
                    <div><?php esc_html_e( 'Adapt your best-performing content for the new platform format', 'rwp-creator-suite' ); ?></div>
                </li>
                <li class="action-item">
                    <span class="action-number">3</span>
                    <div><?php esc_html_e( 'Post consistently for 2 weeks and engage with the community', 'rwp-creator-suite' ); ?></div>
                </li>
                <?php else : ?>
                <li class="action-item">
                    <span class="action-number">1</span>
                    <div><?php esc_html_e( 'Review the opportunity details and assess fit with your content strategy', 'rwp-creator-suite' ); ?></div>
                </li>
                <li class="action-item">
                    <span class="action-number">2</span>
                    <div><?php esc_html_e( 'Create a test piece of content to validate the opportunity', 'rwp-creator-suite' ); ?></div>
                </li>
                <li class="action-item">
                    <span class="action-number">3</span>
                    <div><?php esc_html_e( 'Monitor results and scale what works', 'rwp-creator-suite' ); ?></div>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ( isset( $opportunity['time_sensitive'] ) && $opportunity['time_sensitive'] ) : ?>
        <div class="time-sensitive">
            ⏰ <?php esc_html_e( 'Time-Sensitive: This trend is expected to peak in the next 3-5 days. Act quickly for maximum impact!', 'rwp-creator-suite' ); ?>
        </div>
        <?php endif; ?>

        <div class="cta-section">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Ready to seize this opportunity?', 'rwp-creator-suite' ); ?></h3>
            <p style="margin-bottom: 20px; color: #6b7280;">
                <?php esc_html_e( 'Use our tools to implement this strategy effectively:', 'rwp-creator-suite' ); ?>
            </p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools' ) ); ?>" class="cta-primary">
                <?php esc_html_e( 'Start Creating Content', 'rwp-creator-suite' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=insights' ) ); ?>" class="cta-secondary">
                <?php esc_html_e( 'View More Insights', 'rwp-creator-suite' ); ?>
            </a>
        </div>

        <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; margin: 25px 0;">
            <h4 style="margin-top: 0; color: #1e40af;"><?php esc_html_e( '💪 Why This Works', 'rwp-creator-suite' ); ?></h4>
            <p style="color: #1e3a8a; margin-bottom: 0;">
                <?php esc_html_e( 'This recommendation is based on anonymous community data from creators with similar content styles and audience engagement patterns. We\'ve seen an average of 28% improvement when creators act on similar opportunities within the first 48 hours.', 'rwp-creator-suite' ); ?>
            </p>
        </div>

        <div class="footer">
            <p>
                <?php esc_html_e( 'This personalized opportunity was identified using anonymous community insights to help you grow.', 'rwp-creator-suite' ); ?>
            </p>
            <p style="margin-top: 15px;">
                <strong><?php esc_html_e( 'Questions about this opportunity?', 'rwp-creator-suite' ); ?></strong><br>
                <?php esc_html_e( 'Check out our community insights dashboard for more details and context.', 'rwp-creator-suite' ); ?>
            </p>
            <p style="margin-top: 15px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=notifications' ) ); ?>" style="color: #9ca3af; text-decoration: none;">
                    <?php esc_html_e( 'Manage notification preferences', 'rwp-creator-suite' ); ?>
                </a>
            </p>
        </div>
    </div>
</body>
</html>