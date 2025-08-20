<?php
/**
 * Monthly Report Email Template
 * 
 * @var WP_User $user
 * @var array   $report_data
 * @var string  $month_year
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( sprintf( __( 'Creator Report - %s', 'rwp-creator-suite' ), $month_year ) ); ?></title>
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
            border-bottom: 2px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1f2937;
            margin: 0;
            font-size: 28px;
        }
        .celebration {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .celebration h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #3b82f6;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #1f2937;
            display: block;
        }
        .stat-label {
            color: #6b7280;
            font-size: 14px;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .benchmark-section {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .benchmark-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .benchmark-item:last-child {
            border-bottom: none;
        }
        .benchmark-metric {
            font-weight: 500;
            color: #374151;
        }
        .benchmark-score {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .score-excellent { color: #10b981; }
        .score-good { color: #3b82f6; }
        .score-average { color: #f59e0b; }
        .score-needs-improvement { color: #ef4444; }
        .recommendations {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .recommendations h3 {
            color: #92400e;
            margin-top: 0;
        }
        .recommendation-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #f59e0b;
        }
        .achievement-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin: 5px;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
            background: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
        }
        .cta-button {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
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
        <div class="header">
            <h1>📊 Your Creator Report</h1>
            <p><?php echo esc_html( $month_year ); ?></p>
        </div>

        <div class="celebration">
            <h2>🎉 Amazing Work, <?php echo esc_html( $user->display_name ); ?>!</h2>
            <p><?php esc_html_e( 'Here\'s how your creative journey unfolded this month.', 'rwp-creator-suite' ); ?></p>
        </div>

        <?php if ( ! empty( $report_data['summary'] ) ) : $summary = $report_data['summary']; ?>
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html( $summary['total_content_pieces'] ?? 0 ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Content Pieces', 'rwp-creator-suite' ); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html( $summary['platforms_active'] ?? 0 ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Active Platforms', 'rwp-creator-suite' ); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html( $summary['hashtags_used'] ?? 0 ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Hashtags Used', 'rwp-creator-suite' ); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html( $summary['consistency_score'] ?? 0 ); ?>%</span>
                <span class="stat-label"><?php esc_html_e( 'Consistency Score', 'rwp-creator-suite' ); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $report_data['performance_comparison'] ) ) : ?>
        <div class="benchmark-section">
            <h3>📈 How You Compare</h3>
            <p><?php esc_html_e( 'Your performance vs. the creator community:', 'rwp-creator-suite' ); ?></p>
            
            <?php foreach ( array_slice( $report_data['performance_comparison'], 0, 4 ) as $metric => $comparison ) : 
                $score_class = 'score-average';
                if ( isset( $comparison['vs_community'] ) ) {
                    if ( $comparison['vs_community'] >= 10 ) $score_class = 'score-excellent';
                    elseif ( $comparison['vs_community'] >= 0 ) $score_class = 'score-good';
                    elseif ( $comparison['vs_community'] < -10 ) $score_class = 'score-needs-improvement';
                }
            ?>
            <div class="benchmark-item">
                <span class="benchmark-metric">
                    <?php echo esc_html( ucwords( str_replace( '_', ' ', $metric ) ) ); ?>
                </span>
                <div class="benchmark-score">
                    <span class="<?php echo esc_attr( $score_class ); ?>">
                        <?php if ( isset( $comparison['vs_community'] ) ) : ?>
                            <?php echo esc_html( $comparison['vs_community'] > 0 ? '+' . $comparison['vs_community'] : $comparison['vs_community'] ); ?>%
                        <?php else : ?>
                            <?php esc_html_e( 'Good', 'rwp-creator-suite' ); ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $report_data['achievements_earned'] ) ) : ?>
        <div style="margin: 20px 0;">
            <h3>🏆 Achievements Earned</h3>
            <div>
                <?php foreach ( $report_data['achievements_earned'] as $achievement ) : ?>
                    <span class="achievement-badge">
                        <?php echo esc_html( $achievement['icon'] ?? '🏆' ); ?> 
                        <?php echo esc_html( $achievement['title'] ?? $achievement['name'] ); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $report_data['recommendations'] ) ) : ?>
        <div class="recommendations">
            <h3>💡 Your Action Plan for Next Month</h3>
            
            <?php foreach ( array_slice( $report_data['recommendations'], 0, 3 ) as $recommendation ) : ?>
            <div class="recommendation-item">
                <strong><?php echo esc_html( $recommendation['title'] ); ?></strong>
                <div style="color: #6b7280; margin-top: 5px;">
                    <?php echo esc_html( $recommendation['description'] ); ?>
                </div>
                <?php if ( isset( $recommendation['impact'] ) ) : ?>
                <div style="font-size: 12px; color: #059669; margin-top: 8px;">
                    <?php printf( esc_html__( 'Impact: %s | Effort: %s', 'rwp-creator-suite' ), 
                        esc_html( $recommendation['impact'] ),
                        esc_html( $recommendation['effort'] ?? 'Medium' )
                    ); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="cta-section">
            <h3><?php esc_html_e( 'Keep the momentum going!', 'rwp-creator-suite' ); ?></h3>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=insights' ) ); ?>" class="cta-button">
                <?php esc_html_e( 'View Detailed Analytics', 'rwp-creator-suite' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools' ) ); ?>" class="cta-button">
                <?php esc_html_e( 'Create More Content', 'rwp-creator-suite' ); ?>
            </a>
        </div>

        <div class="footer">
            <p>
                <?php esc_html_e( 'This personalized report is generated from your anonymous usage data and community insights.', 'rwp-creator-suite' ); ?>
            </p>
            <p style="margin-top: 15px;">
                <strong><?php esc_html_e( 'Thank you for being part of our creator community! 🚀', 'rwp-creator-suite' ); ?></strong>
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