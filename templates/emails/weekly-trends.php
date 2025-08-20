<?php
/**
 * Weekly Trends Email Template
 * 
 * @var WP_User $user
 * @var array   $trend_data
 * @var array   $user_profile
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( sprintf( __( 'Weekly Creator Trends - %s', 'rwp-creator-suite' ), date( 'F j, Y' ) ) ); ?></title>
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
        .header {
            text-align: center;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1f2937;
            margin: 0;
            font-size: 28px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #374151;
        }
        .trend-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .trend-section h2 {
            color: #1f2937;
            margin-top: 0;
            font-size: 20px;
        }
        .trend-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #10b981;
        }
        .trend-title {
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .trend-description {
            color: #6b7280;
            font-size: 14px;
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
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        .platform-insight {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #8b5cf6;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        .cta-button {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
        .unsubscribe {
            color: #9ca3af;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔥 Weekly Creator Trends</h1>
            <p><?php echo esc_html( date( 'F j, Y' ) ); ?></p>
        </div>

        <div class="greeting">
            <?php printf( 
                esc_html__( 'Hi %s! Here\'s what\'s trending this week in the creator world.', 'rwp-creator-suite' ),
                esc_html( $user->display_name )
            ); ?>
        </div>

        <?php if ( ! empty( $trend_data['trending_hashtags'] ) ) : ?>
        <div class="trend-section">
            <h2>📈 Trending Hashtags</h2>
            <p><?php esc_html_e( 'These hashtags are gaining momentum this week:', 'rwp-creator-suite' ); ?></p>
            
            <div class="hashtag-list">
                <?php foreach ( array_slice( $trend_data['trending_hashtags'], 0, 8 ) as $hashtag ) : ?>
                    <span class="hashtag">
                        <?php echo esc_html( '#' . ( $hashtag['display_name'] ?? 'Trending' ) ); ?>
                    </span>
                <?php endforeach; ?>
            </div>
            
            <div class="trend-item">
                <div class="trend-title"><?php esc_html_e( 'Pro Tip:', 'rwp-creator-suite' ); ?></div>
                <div class="trend-description">
                    <?php esc_html_e( 'Mix 3-5 trending hashtags with your niche-specific tags for optimal reach.', 'rwp-creator-suite' ); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $trend_data['platform_insights'] ) ) : ?>
        <div class="trend-section">
            <h2>🚀 Platform Insights</h2>
            
            <?php foreach ( array_slice( $trend_data['platform_insights'], 0, 3 ) as $platform => $insight ) : ?>
            <div class="platform-insight">
                <div class="trend-title">
                    <?php echo esc_html( ucfirst( $platform ) ); ?>
                    <?php if ( isset( $insight['engagement_trend']['direction'] ) && $insight['engagement_trend']['direction'] === 'up' ) : ?>
                        <span style="color: #10b981;">↗️ +<?php echo esc_html( $insight['engagement_trend']['percentage'] ); ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="trend-description">
                    <?php echo esc_html( $insight['recommendation'] ?? __( 'Keep up the great work on this platform!', 'rwp-creator-suite' ) ); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $trend_data['content_opportunities'] ) ) : ?>
        <div class="trend-section">
            <h2>💡 Content Opportunities</h2>
            
            <?php foreach ( array_slice( $trend_data['content_opportunities'], 0, 2 ) as $opportunity ) : ?>
            <div class="trend-item">
                <div class="trend-title"><?php echo esc_html( $opportunity['title'] ); ?></div>
                <div class="trend-description">
                    <?php echo esc_html( $opportunity['description'] ); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $trend_data['recommended_templates'] ) ) : ?>
        <div class="trend-section">
            <h2>📝 Templates Worth Trying</h2>
            
            <?php foreach ( array_slice( $trend_data['recommended_templates'], 0, 2 ) as $template ) : ?>
            <div class="trend-item">
                <div class="trend-title"><?php echo esc_html( $template['title'] ); ?></div>
                <div class="trend-description">
                    <?php echo esc_html( $template['description'] ); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="cta-section">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=insights' ) ); ?>" class="cta-button">
                <?php esc_html_e( 'View Full Insights Dashboard', 'rwp-creator-suite' ); ?>
            </a>
        </div>

        <div class="footer">
            <p>
                <?php esc_html_e( 'This report was generated from anonymous community data to help you stay ahead of trends.', 'rwp-creator-suite' ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=notifications' ) ); ?>" class="unsubscribe">
                    <?php esc_html_e( 'Manage notification preferences', 'rwp-creator-suite' ); ?>
                </a>
            </p>
        </div>
    </div>
</body>
</html>