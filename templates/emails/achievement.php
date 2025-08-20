<?php
/**
 * Achievement Notification Email Template
 * 
 * @var WP_User $user
 * @var array   $achievement
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( sprintf( __( 'Achievement Unlocked: %s', 'rwp-creator-suite' ), $achievement['name'] ) ); ?></title>
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
        .celebration-header {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #d97706 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .celebration-header::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            animation: float 3s ease-in-out infinite;
        }
        .celebration-header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            right: -30px;
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            animation: float 4s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .achievement-icon {
            font-size: 60px;
            display: block;
            margin-bottom: 15px;
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        .achievement-title {
            font-size: 32px;
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        .achievement-level {
            background: rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }
        .achievement-details {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
        }
        .achievement-description {
            font-size: 18px;
            color: #374151;
            text-align: center;
            line-height: 1.8;
        }
        .progress-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .progress-bar {
            background: #e5e7eb;
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            background: linear-gradient(90deg, #10b981, #34d399);
            height: 100%;
            transition: width 0.3s ease;
        }
        .milestone-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #6b7280;
            font-size: 14px;
        }
        .stats-highlight {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
            margin: 15px 0;
        }
        .encouragement {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            margin: 25px 0;
        }
        .encouragement h3 {
            margin-top: 0;
            font-size: 22px;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        .cta-button {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 16px;
            transition: background 0.3s ease;
        }
        .share-section {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
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
        <div class="celebration-header">
            <span class="achievement-icon"><?php echo esc_html( $achievement['icon'] ?? '🏆' ); ?></span>
            <h1 class="achievement-title"><?php esc_html_e( 'Achievement Unlocked!', 'rwp-creator-suite' ); ?></h1>
            <span class="achievement-level">
                <?php if ( isset( $achievement['level'] ) ) : ?>
                    <?php printf( esc_html__( 'Level %d', 'rwp-creator-suite' ), $achievement['level'] ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'New Achievement', 'rwp-creator-suite' ); ?>
                <?php endif; ?>
            </span>
        </div>

        <div class="achievement-details">
            <h2 style="text-align: center; color: #1f2937; margin-bottom: 15px;">
                <?php echo esc_html( $achievement['name'] ); ?>
            </h2>
            <div class="achievement-description">
                <?php echo esc_html( $achievement['description'] ); ?>
            </div>
        </div>

        <?php if ( isset( $achievement['progress'] ) && isset( $achievement['next_milestone'] ) ) : ?>
        <div class="progress-section">
            <h3 style="margin-top: 0; color: #374151;"><?php esc_html_e( 'Your Progress', 'rwp-creator-suite' ); ?></h3>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo esc_attr( min( 100, ( $achievement['progress'] / $achievement['next_milestone'] ) * 100 ) ); ?>%;"></div>
            </div>
            
            <div class="milestone-info">
                <span><?php echo esc_html( $achievement['progress'] ); ?></span>
                <span><?php esc_html_e( 'Next milestone:', 'rwp-creator-suite' ); ?> <?php echo esc_html( $achievement['next_milestone'] ); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( isset( $achievement['stats'] ) ) : ?>
        <div class="stats-highlight">
            <h4 style="margin-top: 0; color: #1f2937;"><?php esc_html_e( 'Achievement Stats', 'rwp-creator-suite' ); ?></h4>
            <?php foreach ( $achievement['stats'] as $stat_name => $stat_value ) : ?>
            <div style="margin: 8px 0;">
                <strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $stat_name ) ) ); ?>:</strong> 
                <?php echo esc_html( $stat_value ); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="encouragement">
            <h3><?php esc_html_e( 'Congratulations, ', 'rwp-creator-suite' ); ?><?php echo esc_html( $user->display_name ); ?>!</h3>
            <p>
                <?php esc_html_e( 'Your dedication to creating amazing content is paying off. This achievement shows your growth as a creator and your commitment to engaging with your audience.', 'rwp-creator-suite' ); ?>
            </p>
            <p style="margin-bottom: 0;">
                <?php esc_html_e( 'Keep up the fantastic work! 🚀', 'rwp-creator-suite' ); ?>
            </p>
        </div>

        <div class="share-section">
            <h4 style="margin-top: 0; color: #92400e;"><?php esc_html_e( 'Share Your Success! 🎉', 'rwp-creator-suite' ); ?></h4>
            <p style="color: #78350f; margin-bottom: 15px;">
                <?php esc_html_e( 'Achievements like this deserve celebration. Consider sharing your milestone with your audience!', 'rwp-creator-suite' ); ?>
            </p>
            <div style="color: #78350f; font-size: 14px;">
                💡 <em><?php esc_html_e( 'Sharing your journey builds trust and inspires others to follow their creative path.', 'rwp-creator-suite' ); ?></em>
            </div>
        </div>

        <div class="cta-section">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=achievements' ) ); ?>" class="cta-button">
                <?php esc_html_e( 'View All Achievements', 'rwp-creator-suite' ); ?>
            </a>
        </div>

        <div class="footer">
            <p>
                <?php esc_html_e( 'This achievement was earned through your anonymous contribution to our creator community. Thank you for helping us all grow together!', 'rwp-creator-suite' ); ?>
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