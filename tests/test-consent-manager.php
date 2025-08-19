<?php
/**
 * Tests for Consent Manager
 * 
 * @package RWP_Creator_Suite
 * @subpackage Tests
 */

require_once dirname( __DIR__ ) . '/tests/bootstrap.php';

class Test_Consent_Manager extends WP_UnitTestCase {

    /**
     * Consent manager instance.
     *
     * @var RWP_Creator_Suite_Consent_Manager
     */
    private $consent_manager;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Mock WordPress functions
        \Brain\Monkey\setUp();
        
        // Mock common WordPress functions
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( false );
        \Brain\Monkey\Functions\when( 'is_admin' )->justReturn( false );
        \Brain\Monkey\Functions\when( 'current_user_can' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( false );
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 0 );
        \Brain\Monkey\Functions\when( 'get_user_meta' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce' );
        \Brain\Monkey\Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/' );
        \Brain\Monkey\Functions\when( '__' )->returnArg();
        
        $this->consent_manager = RWP_Creator_Suite_Consent_Manager::get_instance();
    }

    /**
     * Tear down test environment.
     */
    public function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test consent status detection from cookies.
     */
    public function test_cookie_consent_detection() {
        // Test no consent cookie
        unset( $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] );
        $this->assertNull( $this->consent_manager->has_user_consented() );

        // Test consent given
        $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] = 'yes';
        $this->assertTrue( $this->consent_manager->has_user_consented() );

        // Test consent declined
        $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] = 'no';
        $this->assertFalse( $this->consent_manager->has_user_consented() );

        // Clean up
        unset( $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] );
    }

    /**
     * Test logged-in user consent preference.
     */
    public function test_logged_in_user_consent() {
        $user_id = 123;
        
        \Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( $user_id );
        
        $consent_key = 'advanced_features_consent';
        
        // Test user consent given
        \Brain\Monkey\Functions\when( 'get_user_meta' )
            ->with( $user_id, $consent_key, true )
            ->justReturn( 1 );
        
        $this->assertTrue( $this->consent_manager->has_user_consented() );
        
        // Test user consent declined
        \Brain\Monkey\Functions\when( 'get_user_meta' )
            ->with( $user_id, $consent_key, true )
            ->justReturn( 0 );
        
        $this->assertFalse( $this->consent_manager->has_user_consented() );
        
        // Test no user preference (should check cookie)
        \Brain\Monkey\Functions\when( 'get_user_meta' )
            ->with( $user_id, $consent_key, true )
            ->justReturn( '' );
        
        $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] = 'yes';
        $this->assertTrue( $this->consent_manager->has_user_consented() );
        
        // Clean up
        unset( $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] );
    }

    /**
     * Test consent banner display conditions.
     */
    public function test_consent_banner_display_conditions() {
        $reflection = new ReflectionClass( $this->consent_manager );
        $method = $reflection->getMethod( 'should_show_consent_banner' );
        $method->setAccessible( true );

        // Test with no consent set (should show)
        \Brain\Monkey\Functions\when( 'is_admin' )->justReturn( false );
        unset( $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] );
        unset( $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_SHOWN_COOKIE ] );
        
        global $post;
        $post = (object) array( 'post_content' => '<!-- wp:rwp-creator-suite/caption-writer -->' );
        
        \Brain\Monkey\Functions\when( 'has_blocks' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'parse_blocks' )->justReturn( array(
            array( 'blockName' => 'rwp-creator-suite/caption-writer' )
        ) );
        
        $this->assertTrue( $method->invoke( $this->consent_manager ) );

        // Test with consent already given (should not show)
        $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] = 'yes';
        $this->assertFalse( $method->invoke( $this->consent_manager ) );

        // Test with banner already shown (should not show)
        unset( $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] );
        $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_SHOWN_COOKIE ] = '1';
        $this->assertFalse( $method->invoke( $this->consent_manager ) );

        // Clean up
        unset( $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_COOKIE ] );
        unset( $_COOKIE[ RWP_Creator_Suite_Consent_Manager::CONSENT_SHOWN_COOKIE ] );
    }

    /**
     * Test consent message generation.
     */
    public function test_consent_message() {
        $reflection = new ReflectionClass( $this->consent_manager );
        $method = $reflection->getMethod( 'get_consent_message' );
        $method->setAccessible( true );

        $message = $method->invoke( $this->consent_manager );
        
        $this->assertNotEmpty( $message );
        $this->assertIsString( $message );
        $this->assertStringContainsString( 'anonymous', $message );
        $this->assertStringContainsString( 'improve', $message );
    }

    /**
     * Test privacy policy URL generation.
     */
    public function test_privacy_policy_url() {
        $reflection = new ReflectionClass( $this->consent_manager );
        $method = $reflection->getMethod( 'get_privacy_policy_url' );
        $method->setAccessible( true );

        // Test with WordPress privacy page
        \Brain\Monkey\Functions\when( 'get_option' )
            ->with( 'wp_page_for_privacy_policy' )
            ->justReturn( 123 );
        \Brain\Monkey\Functions\when( 'get_permalink' )
            ->with( 123 )
            ->justReturn( 'https://example.com/privacy/' );
        
        $url = $method->invoke( $this->consent_manager );
        $this->assertEquals( 'https://example.com/privacy/', $url );

        // Test fallback to admin page
        \Brain\Monkey\Functions\when( 'get_option' )
            ->with( 'wp_page_for_privacy_policy' )
            ->justReturn( false );
        \Brain\Monkey\Functions\when( 'admin_url' )
            ->with( 'admin.php?page=rwp-creator-tools&tab=privacy' )
            ->justReturn( 'https://example.com/wp-admin/admin.php?page=rwp-creator-tools&tab=privacy' );
        
        $url = $method->invoke( $this->consent_manager );
        $this->assertStringContains( 'admin.php', $url );
    }

    /**
     * Test consent statistics.
     */
    public function test_consent_stats() {
        global $wpdb;
        $wpdb = $this->createMock( wpdb::class );
        
        // Mock database results
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->expects( $this->once() )
            ->method( 'get_results' )
            ->willReturn( array(
                (object) array( 'meta_value' => 'yes', 'count' => 10 ),
                (object) array( 'meta_value' => 'no', 'count' => 5 )
            ) );
        
        $stats = $this->consent_manager->get_consent_stats();
        
        $this->assertIsArray( $stats );
        $this->assertEquals( 15, $stats['total_users_with_preference'] );
        $this->assertEquals( 10, $stats['consented_users'] );
        $this->assertEquals( 5, $stats['declined_users'] );
    }

    /**
     * Test consent data export.
     */
    public function test_consent_data_export() {
        $user_id = 123;
        
        $consent_key = 'advanced_features_consent';
        
        \Brain\Monkey\Functions\when( 'get_user_meta' )
            ->with( $user_id, $consent_key, true )
            ->justReturn( 1 );
        \Brain\Monkey\Functions\when( 'get_user_meta' )
            ->with( $user_id, $consent_key . '_updated', true )
            ->justReturn( '2023-01-01 12:00:00' );
        
        $export_data = $this->consent_manager->export_user_consent_data( $user_id );
        
        $this->assertIsArray( $export_data );
        $this->assertEquals( 'yes', $export_data['analytics_consent'] );
        $this->assertEquals( '2023-01-01 12:00:00', $export_data['last_updated'] );
    }

    /**
     * Test consent data clearing.
     */
    public function test_consent_data_clearing() {
        $user_id = 123;
        
        $consent_key = 'advanced_features_consent';
        
        \Brain\Monkey\Functions\expect( 'delete_user_meta' )
            ->once()
            ->with( $user_id, $consent_key );
        
        $this->consent_manager->clear_consent_data( $user_id );
    }

    /**
     * Test singleton pattern.
     */
    public function test_singleton_pattern() {
        $instance1 = RWP_Creator_Suite_Consent_Manager::get_instance();
        $instance2 = RWP_Creator_Suite_Consent_Manager::get_instance();
        
        $this->assertSame( $instance1, $instance2 );
    }
}