<?php
/**
 * Basic Email Template (Fallback)
 * 
 * @var WP_User $user
 * @var string  $subject
 * @var string  $message
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $subject ?? __( 'Creator Suite Notification', 'rwp-creator-suite' ) ); ?></title>
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
        .content {
            margin: 20px 0;
            color: #374151;
            line-height: 1.8;
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
            <h1><?php esc_html_e( 'Creator Suite', 'rwp-creator-suite' ); ?></h1>
        </div>

        <div class="content">
            <?php if ( isset( $user ) ) : ?>
                <p><?php printf( esc_html__( 'Hi %s,', 'rwp-creator-suite' ), esc_html( $user->display_name ) ); ?></p>
            <?php endif; ?>
            
            <?php if ( isset( $message ) ) : ?>
                <?php echo wp_kses_post( $message ); ?>
            <?php else : ?>
                <p><?php esc_html_e( 'We have an update from your Creator Suite!', 'rwp-creator-suite' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>
                <?php esc_html_e( 'Thank you for being part of our creator community!', 'rwp-creator-suite' ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=notifications' ) ); ?>" style="color: #9ca3af; text-decoration: none;">
                    <?php esc_html_e( 'Manage notification preferences', 'rwp-creator-suite' ); ?>
                </a>
            </p>
        </div>
    </div>
</body>
</html>