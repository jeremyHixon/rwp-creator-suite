<?php
/**
 * Tests for Hashtag Tracker
 * 
 * @package RWP_Creator_Suite
 * @subpackage Tests
 */

require_once dirname( __DIR__ ) . '/tests/bootstrap.php';

class Test_Hashtag_Tracker extends WP_UnitTestCase {

    /**
     * Hashtag tracker instance.
     *
     * @var RWP_Creator_Suite_Hashtag_Tracker
     */
    private $hashtag_tracker;

    /**
     * Mock analytics instance.
     *
     * @var RWP_Creator_Suite_Anonymous_Analytics
     */
    private $mock_analytics;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Mock WordPress functions
        \Brain\Monkey\setUp();
        
        // Mock analytics instance
        $this->mock_analytics = $this->createMock( RWP_Creator_Suite_Anonymous_Analytics::class );
        
        $this->hashtag_tracker = new RWP_Creator_Suite_Hashtag_Tracker();
        
        // Inject mock analytics via reflection
        $reflection = new ReflectionClass( $this->hashtag_tracker );
        $property = $reflection->getProperty( 'analytics' );
        $property->setAccessible( true );
        $property->setValue( $this->hashtag_tracker, $this->mock_analytics );
    }

    /**
     * Tear down test environment.
     */
    public function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test hashtag extraction from text.
     */
    public function test_extract_hashtags_from_text() {
        $reflection = new ReflectionClass( $this->hashtag_tracker );
        $method = $reflection->getMethod( 'extract_hashtags_from_text' );
        $method->setAccessible( true );

        // Test basic hashtag extraction
        $text = 'This is a post with #hashtag and #another';
        $hashtags = $method->invoke( $this->hashtag_tracker, $text );
        
        $this->assertEquals( array( 'hashtag', 'another' ), $hashtags );

        // Test mixed case
        $text = 'Post with #HashTag and #ANOTHER and #lowercase';
        $hashtags = $method->invoke( $this->hashtag_tracker, $text );
        
        $this->assertEquals( array( 'hashtag', 'another', 'lowercase' ), $hashtags );

        // Test duplicate hashtags
        $text = 'Post with #hashtag and #hashtag again';
        $hashtags = $method->invoke( $this->hashtag_tracker, $text );
        
        $this->assertEquals( array( 'hashtag' ), $hashtags );

        // Test no hashtags
        $text = 'Post with no hashtags';
        $hashtags = $method->invoke( $this->hashtag_tracker, $text );
        
        $this->assertEquals( array(), $hashtags );

        // Test hashtags with numbers and underscores
        $text = 'Post with #hash123 and #hash_tag_2';
        $hashtags = $method->invoke( $this->hashtag_tracker, $text );
        
        $this->assertEquals( array( 'hash123', 'hash_tag_2' ), $hashtags );
    }

    /**
     * Test hashtag sanitization.
     */
    public function test_sanitize_hashtag() {
        // Test basic hashtag
        $result = $this->hashtag_tracker->sanitize_hashtag( 'testhashtag' );
        $this->assertEquals( 'testhashtag', $result );

        // Test hashtag with #
        $result = $this->hashtag_tracker->sanitize_hashtag( '#testhashtag' );
        $this->assertEquals( 'testhashtag', $result );

        // Test hashtag with spaces and special characters
        $result = $this->hashtag_tracker->sanitize_hashtag( 'test hashtag!' );
        $this->assertEquals( 'testhashtag', $result );

        // Test hashtag with numbers and underscores
        $result = $this->hashtag_tracker->sanitize_hashtag( 'test_hashtag_123' );
        $this->assertEquals( 'test_hashtag_123', $result );

        // Test empty hashtag
        $result = $this->hashtag_tracker->sanitize_hashtag( '' );
        $this->assertEquals( '', $result );

        // Test hashtag with only #
        $result = $this->hashtag_tracker->sanitize_hashtag( '#' );
        $this->assertEquals( '', $result );
    }

    /**
     * Test hashtag validation.
     */
    public function test_validate_hashtag() {
        // Test valid hashtags
        $this->assertTrue( $this->hashtag_tracker->validate_hashtag( 'validhashtag' ) );
        $this->assertTrue( $this->hashtag_tracker->validate_hashtag( 'valid_hashtag_123' ) );
        $this->assertTrue( $this->hashtag_tracker->validate_hashtag( '#validhashtag' ) );

        // Test empty hashtag
        $result = $this->hashtag_tracker->validate_hashtag( '' );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'empty_hashtag', $result->get_error_code() );

        // Test hashtag too long
        $long_hashtag = str_repeat( 'a', 101 );
        $result = $this->hashtag_tracker->validate_hashtag( $long_hashtag );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'hashtag_too_long', $result->get_error_code() );

        // Test hashtag with invalid characters
        $result = $this->hashtag_tracker->validate_hashtag( 'invalid hashtag!' );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'invalid_hashtag', $result->get_error_code() );
    }

    /**
     * Test caption hashtag tracking.
     */
    public function test_track_caption_hashtags() {
        $caption = 'Generated caption content';
        $context = array(
            'platform' => 'instagram',
            'tone' => 'casual'
        );
        $user_data = array(
            'description' => 'User input with #hashtag1 and #hashtag2'
        );

        // Expect hashtag tracking calls
        $this->mock_analytics->expects( $this->exactly( 2 ) )
            ->method( 'track_user_hashtag' )
            ->withConsecutive(
                array( 'hashtag1', array(
                    'platform' => 'instagram',
                    'tone' => 'casual',
                    'content_type' => 'caption',
                    'source' => 'user_input'
                ) ),
                array( 'hashtag2', array(
                    'platform' => 'instagram',
                    'tone' => 'casual',
                    'content_type' => 'caption',
                    'source' => 'user_input'
                ) )
            );

        // Expect content generation tracking
        $this->mock_analytics->expects( $this->once() )
            ->method( 'track_content_generation' )
            ->with( array(
                'feature' => 'caption_writer',
                'platform' => 'instagram',
                'tone' => 'casual',
                'success' => true,
                'content_length' => strlen( $caption )
            ) );

        $this->hashtag_tracker->track_caption_hashtags( $caption, $context, $user_data );
    }

    /**
     * Test repurposed content hashtag tracking.
     */
    public function test_track_repurposed_hashtags() {
        $content = 'Repurposed content';
        $context = array(
            'platform' => 'twitter',
            'tone' => 'professional'
        );
        $user_data = array(
            'content' => 'Original content with #marketing and #business'
        );

        // Expect hashtag tracking calls
        $this->mock_analytics->expects( $this->exactly( 2 ) )
            ->method( 'track_user_hashtag' )
            ->withConsecutive(
                array( 'marketing', array(
                    'platform' => 'twitter',
                    'tone' => 'professional',
                    'content_type' => 'repurposed',
                    'source' => 'user_input'
                ) ),
                array( 'business', array(
                    'platform' => 'twitter',
                    'tone' => 'professional',
                    'content_type' => 'repurposed',
                    'source' => 'user_input'
                ) )
            );

        // Expect content generation tracking
        $this->mock_analytics->expects( $this->once() )
            ->method( 'track_content_generation' )
            ->with( array(
                'feature' => 'content_repurposer',
                'platform' => 'twitter',
                'tone' => 'professional',
                'success' => true,
                'content_length' => strlen( $content )
            ) );

        $this->hashtag_tracker->track_repurposed_hashtags( $content, $context, $user_data );
    }

    /**
     * Test template hashtag tracking.
     */
    public function test_track_template_hashtags() {
        $template_id = 'test_template_123';
        $variables = array(
            'title' => 'Test Title',
            'hashtag_field' => '#template #test',
            'custom_hashtags' => '#custom #tags',
            'regular_field' => 'Not hashtags'
        );
        $context = array(
            'platform' => 'linkedin',
            'tone' => 'professional'
        );
        $final_content = 'Final template content';

        // Expect hashtag tracking calls for hashtag fields
        $this->mock_analytics->expects( $this->exactly( 4 ) )
            ->method( 'track_user_hashtag' )
            ->withConsecutive(
                array( 'template', array(
                    'platform' => 'linkedin',
                    'tone' => 'professional',
                    'content_type' => 'template',
                    'source' => 'template_customization'
                ) ),
                array( 'test', array(
                    'platform' => 'linkedin',
                    'tone' => 'professional',
                    'content_type' => 'template',
                    'source' => 'template_customization'
                ) ),
                array( 'custom', array(
                    'platform' => 'linkedin',
                    'tone' => 'professional',
                    'content_type' => 'template',
                    'source' => 'template_customization'
                ) ),
                array( 'tags', array(
                    'platform' => 'linkedin',
                    'tone' => 'professional',
                    'content_type' => 'template',
                    'source' => 'template_customization'
                ) )
            );

        // Expect template usage tracking
        $this->mock_analytics->expects( $this->once() )
            ->method( 'track_template_usage' )
            ->with( $template_id, array(
                'platform' => 'linkedin',
                'tone' => 'professional',
                'completion_status' => 'completed',
                'customizations_made' => count( $variables )
            ) );

        $this->hashtag_tracker->track_template_hashtags( $template_id, $variables, $context, $final_content );
    }

    /**
     * Test that no hashtags are tracked from AI-generated content.
     */
    public function test_no_ai_hashtag_tracking() {
        $caption = 'AI generated caption with #aihashtag';
        $context = array( 'platform' => 'instagram' );
        $user_data = array(
            'description' => 'User input without hashtags'
        );

        // Should only track content generation, not hashtags
        $this->mock_analytics->expects( $this->never() )
            ->method( 'track_user_hashtag' );

        $this->mock_analytics->expects( $this->once() )
            ->method( 'track_content_generation' );

        $this->hashtag_tracker->track_caption_hashtags( $caption, $context, $user_data );
    }

    /**
     * Test tracking disabled scenarios.
     */
    public function test_tracking_disabled() {
        $reflection = new ReflectionClass( $this->hashtag_tracker );
        $method = $reflection->getMethod( 'is_tracking_disabled' );
        $method->setAccessible( true );

        // Test explicit opt-out
        $_REQUEST['rwp_no_tracking'] = '1';
        $this->assertTrue( $method->invoke( $this->hashtag_tracker ) );
        unset( $_REQUEST['rwp_no_tracking'] );

        // Test Do Not Track header
        $_SERVER['HTTP_DNT'] = '1';
        $this->assertTrue( $method->invoke( $this->hashtag_tracker ) );
        unset( $_SERVER['HTTP_DNT'] );

        // Test normal scenario
        $this->assertFalse( $method->invoke( $this->hashtag_tracker ) );
    }
}