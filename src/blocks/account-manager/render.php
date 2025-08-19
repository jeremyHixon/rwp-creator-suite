<?php
/**
 * Account Manager Block Render Template
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

$wrapper_attributes = get_block_wrapper_attributes( array(
    'id' => 'rwp-account-manager-' . wp_unique_id(),
    'data-view-type' => $attributes['viewType'] ?? 'dashboard',
    'data-show-consent' => isset( $attributes['showConsentSettings'] ) && $attributes['showConsentSettings'] ? '1' : '0',
    'data-allow-guest' => isset( $attributes['allowGuestView'] ) && $attributes['allowGuestView'] ? '1' : '0',
    'data-config' => wp_json_encode( $attributes ),
) );

?>
<div <?php echo $wrapper_attributes; ?>>
    <div class="rwp-account-manager-container">
        <?php if ( ! is_user_logged_in() ) : ?>
            <?php if ( isset( $attributes['allowGuestView'] ) && $attributes['allowGuestView'] ) : ?>
                <div class="rwp-login-prompt">
                    <h3><?php esc_html_e( 'Account Access Required', 'rwp-creator-suite' ); ?></h3>
                    <p><?php esc_html_e( 'Please log in or register to access your account settings and manage your preferences.', 'rwp-creator-suite' ); ?></p>
                    <div class="rwp-auth-buttons">
                        <button type="button" class="primary" onclick="window.location.href='<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>'">
                            <?php esc_html_e( 'Log In', 'rwp-creator-suite' ); ?>
                        </button>
                        <button type="button" class="secondary" onclick="window.location.href='<?php echo esc_url( wp_registration_url() ); ?>'">
                            <?php esc_html_e( 'Register', 'rwp-creator-suite' ); ?>
                        </button>
                    </div>
                </div>
            <?php else : ?>
                <div class="rwp-loading">
                    <?php esc_html_e( 'Please log in to access your account', 'rwp-creator-suite' ); ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="rwp-loading">
                <?php esc_html_e( 'Loading account manager...', 'rwp-creator-suite' ); ?>
            </div>
        <?php endif; ?>
    </div>
</div>