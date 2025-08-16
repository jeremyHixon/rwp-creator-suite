<?php
/**
 * Tests for API Validation Trait
 */

// Test class that uses the trait
class Test_API_Class {
    use RWP_Creator_Suite_API_Validation_Trait;
}

class Test_API_Validation_Trait extends WP_UnitTestCase {

    protected $api_class;

    public function setUp(): void {
        parent::setUp();
        $this->api_class = new Test_API_Class();
    }

    public function test_verify_nonce_permission_missing_nonce() {
        $request = new WP_REST_Request( 'POST', '/test' );
        
        $result = $this->api_class->verify_nonce_permission( $request );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'missing_nonce', $result->get_error_code() );
    }

    public function test_verify_nonce_permission_invalid_nonce() {
        $request = new WP_REST_Request( 'POST', '/test' );
        $request->set_header( 'X-WP-Nonce', 'invalid_nonce' );
        
        $result = $this->api_class->verify_nonce_permission( $request );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'invalid_nonce', $result->get_error_code() );
    }

    public function test_verify_nonce_permission_valid_nonce() {
        $request = new WP_REST_Request( 'POST', '/test' );
        $valid_nonce = wp_create_nonce( 'wp_rest' );
        $request->set_header( 'X-WP-Nonce', $valid_nonce );
        
        $result = $this->api_class->verify_nonce_permission( $request );
        
        $this->assertTrue( $result );
    }

    public function test_check_user_logged_in_not_logged_in() {
        // Ensure no user is logged in
        wp_set_current_user( 0 );
        
        $result = $this->api_class->check_user_logged_in();
        
        $this->assertWPError( $result );
        $this->assertEquals( 'not_logged_in', $result->get_error_code() );
    }

    public function test_check_user_logged_in_logged_in() {
        // Create and set a user
        $user_id = $this->factory->user->create();
        wp_set_current_user( $user_id );
        
        $result = $this->api_class->check_user_logged_in();
        
        $this->assertTrue( $result );
    }

    public function test_sanitize_description_empty() {
        $result = $this->api_class->sanitize_description( '' );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'empty_description', $result->get_error_code() );
    }

    public function test_sanitize_description_with_html() {
        $input = '<script>alert("test")</script>Clean text <strong>bold</strong>';
        $result = $this->api_class->sanitize_description( $input );
        
        $this->assertIsString( $result );
        $this->assertStringNotContainsString( '<script>', $result );
        $this->assertStringNotContainsString( '<strong>', $result );
        $this->assertStringContainsString( 'Clean text', $result );
    }

    public function test_validate_description_empty() {
        $result = $this->api_class->validate_description( '' );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'empty_description', $result->get_error_code() );
    }

    public function test_validate_description_too_short() {
        $short_description = 'short';
        $result = $this->api_class->validate_description( $short_description );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'description_too_short', $result->get_error_code() );
    }

    public function test_validate_description_too_long() {
        $long_description = str_repeat( 'a', 3000 );
        $result = $this->api_class->validate_description( $long_description );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'description_too_long', $result->get_error_code() );
    }

    public function test_validate_description_valid() {
        $valid_description = 'This is a valid description that meets length requirements.';
        $result = $this->api_class->validate_description( $valid_description );
        
        $this->assertTrue( $result );
    }

    public function test_sanitize_platforms_not_array() {
        $result = $this->api_class->sanitize_platforms( 'not_an_array' );
        
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_sanitize_platforms_valid() {
        $platforms = array( 'instagram', 'twitter', 'invalid_platform', 'linkedin' );
        $result = $this->api_class->sanitize_platforms( $platforms );
        
        $this->assertIsArray( $result );
        $this->assertContains( 'instagram', $result );
        $this->assertContains( 'twitter', $result );
        $this->assertContains( 'linkedin', $result );
        $this->assertNotContains( 'invalid_platform', $result );
    }

    public function test_sanitize_platforms_duplicates() {
        $platforms = array( 'instagram', 'instagram', 'twitter' );
        $result = $this->api_class->sanitize_platforms( $platforms );
        
        $this->assertIsArray( $result );
        $this->assertCount( 2, $result );
        $this->assertContains( 'instagram', $result );
        $this->assertContains( 'twitter', $result );
    }

    public function test_validate_platforms_not_array() {
        $result = $this->api_class->validate_platforms( 'not_array' );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'invalid_platforms', $result->get_error_code() );
    }

    public function test_validate_platforms_empty() {
        $result = $this->api_class->validate_platforms( array() );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'no_platforms', $result->get_error_code() );
    }

    public function test_validate_platforms_too_many() {
        $platforms = array( 'instagram', 'twitter', 'facebook', 'linkedin', 'tiktok', 'extra' );
        $result = $this->api_class->validate_platforms( $platforms );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'too_many_platforms', $result->get_error_code() );
    }

    public function test_validate_platforms_invalid_platform() {
        $platforms = array( 'instagram', 'invalid_platform' );
        $result = $this->api_class->validate_platforms( $platforms );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'invalid_platform', $result->get_error_code() );
    }

    public function test_validate_platforms_valid() {
        $platforms = array( 'instagram', 'twitter', 'linkedin' );
        $result = $this->api_class->validate_platforms( $platforms );
        
        $this->assertTrue( $result );
    }

    public function test_validate_content_empty() {
        $result = $this->api_class->validate_content( '' );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'empty_content', $result->get_error_code() );
    }

    public function test_validate_content_too_short() {
        $short_content = 'short';
        $result = $this->api_class->validate_content( $short_content );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'content_too_short', $result->get_error_code() );
    }

    public function test_validate_content_too_long() {
        $long_content = str_repeat( 'a', 15000 );
        $result = $this->api_class->validate_content( $long_content );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'content_too_long', $result->get_error_code() );
    }

    public function test_validate_content_valid() {
        $valid_content = 'This is valid content for repurposing that meets the length requirements.';
        $result = $this->api_class->validate_content( $valid_content );
        
        $this->assertTrue( $result );
    }

    public function test_sanitize_content() {
        $content = '<script>alert("test")</script><p>Valid paragraph</p><div>Invalid div</div><strong>Bold text</strong>';
        $result = $this->api_class->sanitize_content( $content );
        
        $this->assertIsString( $result );
        $this->assertStringNotContainsString( '<script>', $result );
        $this->assertStringNotContainsString( '<div>', $result );
        $this->assertStringContainsString( '<p>Valid paragraph</p>', $result );
        $this->assertStringContainsString( '<strong>Bold text</strong>', $result );
    }

    public function test_validate_tone_valid() {
        $valid_tones = array( 'professional', 'casual', 'witty', 'inspirational' );
        
        foreach ( $valid_tones as $tone ) {
            $result = $this->api_class->validate_tone( $tone );
            $this->assertTrue( $result, "Tone '{$tone}' should be valid" );
        }
    }

    public function test_validate_tone_invalid() {
        $result = $this->api_class->validate_tone( 'invalid_tone' );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'invalid_tone', $result->get_error_code() );
    }

    public function test_success_response() {
        $data = array( 'key' => 'value' );
        $message = 'Success message';
        $meta = array( 'meta_key' => 'meta_value' );
        
        $response = $this->api_class->success_response( $data, $message, $meta );
        
        $this->assertInstanceOf( 'WP_REST_Response', $response );
        $response_data = $response->get_data();
        
        $this->assertTrue( $response_data['success'] );
        $this->assertEquals( $data, $response_data['data'] );
        $this->assertEquals( $message, $response_data['message'] );
        $this->assertEquals( $meta, $response_data['meta'] );
    }

    public function test_success_response_minimal() {
        $data = array( 'key' => 'value' );
        
        $response = $this->api_class->success_response( $data );
        
        $this->assertInstanceOf( 'WP_REST_Response', $response );
        $response_data = $response->get_data();
        
        $this->assertTrue( $response_data['success'] );
        $this->assertEquals( $data, $response_data['data'] );
        $this->assertArrayNotHasKey( 'message', $response_data );
        $this->assertArrayNotHasKey( 'meta', $response_data );
    }

    public function test_error_response() {
        $code = 'test_error';
        $message = 'Test error message';
        $data = array( 'error_data' => 'value' );
        $status = 400;
        
        $error = $this->api_class->error_response( $code, $message, $data, $status );
        
        $this->assertWPError( $error );
        $this->assertEquals( $code, $error->get_error_code() );
        $this->assertEquals( $message, $error->get_error_message() );
        
        $error_data = $error->get_error_data();
        $this->assertEquals( $status, $error_data['status'] );
        $this->assertEquals( $data, $error_data['details'] );
    }

    public function test_sanitize_input_array_not_array() {
        $result = $this->api_class->sanitize_input_array( 'not_array', array( 'key1' ) );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'invalid_input', $result->get_error_code() );
    }

    public function test_sanitize_input_array_valid() {
        $input = array(
            'allowed_key' => 'value',
            'another_allowed' => 'value2',
            'not_allowed' => 'should_be_filtered',
        );
        $allowed_keys = array( 'allowed_key', 'another_allowed' );
        
        $result = $this->api_class->sanitize_input_array( $input, $allowed_keys );
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'allowed_key', $result );
        $this->assertArrayHasKey( 'another_allowed', $result );
        $this->assertArrayNotHasKey( 'not_allowed', $result );
    }

    public function test_sanitize_input_array_with_array_values() {
        $input = array(
            'array_field' => array( 'value1', 'value2' ),
            'string_field' => 'string_value',
        );
        $allowed_keys = array( 'array_field', 'string_field' );
        
        $result = $this->api_class->sanitize_input_array( $input, $allowed_keys );
        
        $this->assertIsArray( $result );
        $this->assertIsArray( $result['array_field'] );
        $this->assertIsString( $result['string_field'] );
    }

    public function test_validate_request_size_no_header() {
        $request = new WP_REST_Request( 'POST', '/test' );
        
        $result = $this->api_class->validate_request_size( $request );
        
        $this->assertTrue( $result );
    }

    public function test_validate_request_size_within_limit() {
        $request = new WP_REST_Request( 'POST', '/test' );
        $request->set_header( 'content-length', '1024' ); // 1KB
        
        $result = $this->api_class->validate_request_size( $request );
        
        $this->assertTrue( $result );
    }

    public function test_validate_request_size_exceeds_limit() {
        $request = new WP_REST_Request( 'POST', '/test' );
        $request->set_header( 'content-length', '2097152' ); // 2MB
        
        $result = $this->api_class->validate_request_size( $request );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'request_too_large', $result->get_error_code() );
    }
}