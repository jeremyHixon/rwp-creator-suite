<?php
/**
 * Registration Consent Handler Tests
 *
 * @package RWP_Creator_Suite
 */

require_once __DIR__ . '/bootstrap.php';

class Test_Registration_Consent extends RWP_Creator_Suite_Test_Case {

    /**
     * Consent handler instance.
     *
     * @var RWP_Creator_Suite_Registration_Consent_Handler
     */
    private $consent_handler;

    /**
     * Set up test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
    }

    /**
     * Test consent meta key.
     */
    public function test_consent_meta_key() {
        $this->assertEquals( 'advanced_features_consent', RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key() );
    }

    /**
     * Test update user consent.
     */
    public function test_update_user_consent() {
        // Mock WordPress functions
        \Brain\Monkey\Functions\when( 'update_user_meta' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'do_action' )->justReturn( null );
        \Brain\Monkey\Functions\when( 'current_time' )->justReturn( 123456789 );

        $result = $this->consent_handler->update_user_consent( 1, true );
        $this->assertTrue( $result );

        $result = $this->consent_handler->update_user_consent( 1, false );
        $this->assertTrue( $result );
    }

    /**
     * Test get user consent when not set.
     */
    public function test_get_user_consent_not_set() {
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 1 );
        \Brain\Monkey\Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = $this->consent_handler->get_user_consent( 1 );
        $this->assertNull( $result );
    }

    /**
     * Test get user consent when true.
     */
    public function test_get_user_consent_true() {
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 1 );
        \Brain\Monkey\Functions\when( 'get_user_meta' )->justReturn( '1' );

        $result = $this->consent_handler->get_user_consent( 1 );
        $this->assertTrue( $result );
    }

    /**
     * Test get user consent when false.
     */
    public function test_get_user_consent_false() {
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 1 );
        \Brain\Monkey\Functions\when( 'get_user_meta' )->justReturn( '0' );

        $result = $this->consent_handler->get_user_consent( 1 );
        $this->assertFalse( $result );
    }

    /**
     * Test user has consent.
     */
    public function test_user_has_consent() {
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 1 );
        
        // Test with consent
        \Brain\Monkey\Functions\when( 'get_user_meta' )->justReturn( '1' );
        $result = $this->consent_handler->user_has_consent( 1 );
        $this->assertTrue( $result );
        
        // Test without consent
        \Brain\Monkey\Functions\when( 'get_user_meta' )->justReturn( '0' );
        $result = $this->consent_handler->user_has_consent( 1 );
        $this->assertFalse( $result );
        
        // Test not set
        \Brain\Monkey\Functions\when( 'get_user_meta' )->justReturn( '' );
        $result = $this->consent_handler->user_has_consent( 1 );
        $this->assertFalse( $result );
    }

    /**
     * Test save consent with valid nonce.
     */
    public function test_save_consent_valid_nonce() {
        // Mock $_POST data
        $_POST['_wpnonce'] = 'test-nonce';
        $_POST['advanced_features_consent'] = '1';
        
        \Brain\Monkey\Functions\when( 'wp_verify_nonce' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'update_user_meta' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'do_action' )->justReturn( null );
        \Brain\Monkey\Functions\when( 'current_time' )->justReturn( 123456789 );

        // Should not throw any errors
        $this->consent_handler->save_consent( 1 );
        
        // Clean up
        unset( $_POST['_wpnonce'], $_POST['advanced_features_consent'] );
        
        $this->assertTrue( true ); // If we reach here, test passed
    }

    /**
     * Test save consent without consent checkbox.
     */
    public function test_save_consent_no_checkbox() {
        // Mock $_POST data
        $_POST['_wpnonce'] = 'test-nonce';
        // No consent checkbox checked
        
        \Brain\Monkey\Functions\when( 'wp_verify_nonce' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'update_user_meta' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'do_action' )->justReturn( null );
        \Brain\Monkey\Functions\when( 'current_time' )->justReturn( 123456789 );

        // Should not throw any errors and should save 0
        $this->consent_handler->save_consent( 1 );
        
        // Clean up
        unset( $_POST['_wpnonce'] );
        
        $this->assertTrue( true ); // If we reach here, test passed
    }

    /**
     * Test get consented users.
     */
    public function test_get_consented_users() {
        \Brain\Monkey\Functions\when( 'wp_parse_args' )->returnArg( 0 );
        \Brain\Monkey\Functions\when( 'get_users' )->justReturn( array( 1, 2, 3 ) );

        $result = $this->consent_handler->get_consented_users();
        $this->assertIsArray( $result );
        $this->assertCount( 3, $result );
    }

    /**
     * Test validate consent - should never add errors since consent is optional.
     */
    public function test_validate_consent() {
        $errors = new \WP_Error();
        
        $result = $this->consent_handler->validate_consent( $errors, 'testuser', 'test@example.com' );
        
        // Should return the same errors object without modifications
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEmpty( $result->get_error_messages() );
    }
}