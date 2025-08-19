<?php
/**
 * Tests for Anonymous Analytics
 * 
 * @package RWP_Creator_Suite
 * @subpackage Tests
 */

require_once dirname( __DIR__ ) . '/tests/bootstrap.php';

class Test_Anonymous_Analytics extends WP_UnitTestCase {

    /**
     * Analytics instance.
     *
     * @var RWP_Creator_Suite_Anonymous_Analytics
     */
    private $analytics;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Mock WordPress functions
        \Brain\Monkey\setUp();
        
        // Mock current_time function
        \Brain\Monkey\Functions\when( 'current_time' )->justReturn( time() );
        
        // Mock wp_salt function
        \Brain\Monkey\Functions\when( 'wp_salt' )->justReturn( 'test_salt_123' );
        
        // Mock wp_generate_password function
        \Brain\Monkey\Functions\when( 'wp_generate_password' )->justReturn( 'random_password_123' );
        
        // Mock WordPress database functions
        global $wpdb;
        $wpdb = $this->createMock( wpdb::class );
        
        $this->analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
    }

    /**
     * Tear down test environment.
     */
    public function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test anonymous session creation.
     */
    public function test_create_anonymous_session() {
        // Mock server variables
        $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
        $session_hash = $this->analytics->get_session_hash();
        
        $this->assertNotEmpty( $session_hash );
        $this->assertEquals( 32, strlen( $session_hash ) );
        $this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $session_hash );
    }

    /**
     * Test hashtag anonymization.
     */
    public function test_hashtag_anonymization() {
        $reflection = new ReflectionClass( $this->analytics );
        $method = $reflection->getMethod( 'anonymize_hashtag' );
        $method->setAccessible( true );

        // Test basic hashtag
        $hashtag = 'testhashtag';
        $hash1 = $method->invoke( $this->analytics, $hashtag );
        $hash2 = $method->invoke( $this->analytics, $hashtag );
        
        $this->assertNotEmpty( $hash1 );
        $this->assertEquals( $hash1, $hash2 ); // Same input should produce same hash
        $this->assertEquals( 64, strlen( $hash1 ) ); // SHA256 hash length
        
        // Test case insensitivity
        $hash_upper = $method->invoke( $this->analytics, 'TESTHASHTAG' );
        $hash_lower = $method->invoke( $this->analytics, 'testhashtag' );
        $this->assertEquals( $hash_upper, $hash_lower );
        
        // Test different hashtags produce different hashes
        $hash_different = $method->invoke( $this->analytics, 'differenthashtag' );
        $this->assertNotEquals( $hash1, $hash_different );
    }

    /**
     * Test consent checking.
     */
    public function test_consent_checking() {
        // Test default consent (should be false)
        $this->assertFalse( $this->analytics->user_has_consented() );
        
        // Test setting consent
        $_COOKIE['rwp_analytics_consent'] = 'yes';
        $this->assertTrue( $this->analytics->user_has_consented() );
        
        $_COOKIE['rwp_analytics_consent'] = 'no';
        $this->assertFalse( $this->analytics->user_has_consented() );
        
        // Clean up
        unset( $_COOKIE['rwp_analytics_consent'] );
    }

    /**
     * Test user consent with logged-in user.
     */
    public function test_logged_in_user_consent() {
        // Mock logged-in user
        $user_id = 123;
        \Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( true );
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( $user_id );
        $consent_key = 'advanced_features_consent';
        \Brain\Monkey\Functions\when( 'get_user_meta' )
            ->with( $user_id, $consent_key, true )
            ->justReturn( 1 );
        
        $this->assertTrue( $this->analytics->user_has_consented() );
        
        // Test declining consent
        \Brain\Monkey\Functions\when( 'get_user_meta' )
            ->with( $user_id, $consent_key, true )
            ->justReturn( 0 );
        
        $this->assertFalse( $this->analytics->user_has_consented() );
    }

    /**
     * Test hashtag tracking without consent.
     */
    public function test_hashtag_tracking_without_consent() {
        // Ensure no consent
        unset( $_COOKIE['rwp_analytics_consent'] );
        \Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( false );
        
        // Mock database to ensure no insert happens
        global $wpdb;
        $wpdb->expects( $this->never() )->method( 'insert' );
        
        $this->analytics->track_user_hashtag( 'testhashtag', array( 'platform' => 'instagram' ) );
    }

    /**
     * Test hashtag tracking with consent.
     */
    public function test_hashtag_tracking_with_consent() {
        // Set consent
        $_COOKIE['rwp_analytics_consent'] = 'yes';
        
        // Mock database insert
        global $wpdb;
        $wpdb->expects( $this->once() )
            ->method( 'insert' )
            ->with(
                $this->stringContains( 'rwp_anonymous_analytics' ),
                $this->callback( function( $data ) {
                    return isset( $data['event_type'] ) 
                        && $data['event_type'] === RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED
                        && isset( $data['event_data'] )
                        && isset( $data['anonymous_session_hash'] );
                } )
            )
            ->willReturn( 1 );
        
        $this->analytics->track_user_hashtag( 'testhashtag', array( 
            'platform' => 'instagram',
            'tone' => 'casual'
        ) );
        
        // Clean up
        unset( $_COOKIE['rwp_analytics_consent'] );
    }

    /**
     * Test platform selection tracking.
     */
    public function test_platform_selection_tracking() {
        // Set consent
        $_COOKIE['rwp_analytics_consent'] = 'yes';
        
        // Mock database insert
        global $wpdb;
        $wpdb->expects( $this->once() )
            ->method( 'insert' )
            ->with(
                $this->stringContains( 'rwp_anonymous_analytics' ),
                $this->callback( function( $data ) {
                    $event_data = json_decode( $data['event_data'], true );
                    return $data['event_type'] === RWP_Creator_Suite_Anonymous_Analytics::EVENT_PLATFORM_SELECTED
                        && $event_data['platform'] === 'instagram'
                        && $event_data['feature'] === 'caption_writer';
                } )
            )
            ->willReturn( 1 );
        
        $this->analytics->track_platform_selection( 'instagram', array( 
            'feature' => 'caption_writer'
        ) );
        
        // Clean up
        unset( $_COOKIE['rwp_analytics_consent'] );
    }

    /**
     * Test content generation tracking.
     */
    public function test_content_generation_tracking() {
        // Set consent
        $_COOKIE['rwp_analytics_consent'] = 'yes';
        
        // Mock database insert
        global $wpdb;
        $wpdb->expects( $this->once() )
            ->method( 'insert' )
            ->with(
                $this->stringContains( 'rwp_anonymous_analytics' ),
                $this->callback( function( $data ) {
                    $event_data = json_decode( $data['event_data'], true );
                    return $data['event_type'] === RWP_Creator_Suite_Anonymous_Analytics::EVENT_CONTENT_GENERATED
                        && $event_data['feature'] === 'caption_writer'
                        && $event_data['success'] === true
                        && isset( $event_data['content_length'] );
                } )
            )
            ->willReturn( 1 );
        
        $this->analytics->track_content_generation( array( 
            'feature' => 'caption_writer',
            'platform' => 'instagram',
            'success' => true,
            'content_length' => 150
        ) );
        
        // Clean up
        unset( $_COOKIE['rwp_analytics_consent'] );
    }

    /**
     * Test session hash validation.
     */
    public function test_session_hash_validation() {
        $reflection = new ReflectionClass( $this->analytics );
        $method = $reflection->getMethod( 'validate_session_format' );
        $method->setAccessible( true );

        // Valid session hashes
        $this->assertTrue( $method->invoke( $this->analytics, 'a1b2c3d4e5f6789012345678901234ab' ) );
        $this->assertTrue( $method->invoke( $this->analytics, '12345678901234567890123456789012' ) );
        
        // Invalid session hashes
        $this->assertFalse( $method->invoke( $this->analytics, 'invalid' ) );
        $this->assertFalse( $method->invoke( $this->analytics, 'a1b2c3d4e5f6789012345678901234abz' ) ); // too long
        $this->assertFalse( $method->invoke( $this->analytics, 'a1b2c3d4e5f678901234567890123' ) ); // too short
        $this->assertFalse( $method->invoke( $this->analytics, 'G1b2c3d4e5f6789012345678901234ab' ) ); // invalid character
    }

    /**
     * Test data sanitization.
     */
    public function test_data_sanitization() {
        // Set consent
        $_COOKIE['rwp_analytics_consent'] = 'yes';
        
        // Mock database insert to capture sanitized data
        global $wpdb;
        $captured_data = null;
        $wpdb->expects( $this->once() )
            ->method( 'insert' )
            ->willReturnCallback( function( $table, $data ) use ( &$captured_data ) {
                $captured_data = $data;
                return 1;
            } );
        
        // Track with potentially unsafe data
        $this->analytics->track_platform_selection( '<script>alert("xss")</script>', array( 
            'feature' => 'caption_writer<script>'
        ) );
        
        // Verify data was sanitized
        $this->assertNotNull( $captured_data );
        $event_data = json_decode( $captured_data['event_data'], true );
        $this->assertStringNotContainsString( '<script>', $event_data['platform'] );
        $this->assertStringNotContainsString( '<script>', $event_data['feature'] );
        
        // Clean up
        unset( $_COOKIE['rwp_analytics_consent'] );
    }

    /**
     * Test get session hash method.
     */
    public function test_get_session_hash() {
        $session_hash = $this->analytics->get_session_hash();
        
        $this->assertNotEmpty( $session_hash );
        $this->assertIsString( $session_hash );
        
        // Should return same hash on multiple calls
        $session_hash2 = $this->analytics->get_session_hash();
        $this->assertEquals( $session_hash, $session_hash2 );
    }
}