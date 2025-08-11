<?php
/**
 * AI Caption Generation Service
 * 
 * Handles integration with AI services (OpenAI, Claude, etc.) for caption generation.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_AI_Caption_Service {

    private $api_key;
    private $model = 'gpt-3.5-turbo';
    private $api_provider = 'openai'; // openai, claude, local
    
    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_provider = get_option( 'rwp_creator_suite_ai_provider', 'mock' );
        $this->model = get_option( 'rwp_creator_suite_ai_model', 'gpt-3.5-turbo' );
        
        // Set API key based on provider
        if ( $this->api_provider === 'openai' ) {
            $this->api_key = get_option( 'rwp_creator_suite_openai_api_key' );
        } elseif ( $this->api_provider === 'claude' ) {
            $this->api_key = get_option( 'rwp_creator_suite_claude_api_key' );
        }
    }
    
    /**
     * Generate captions using AI service.
     */
    public function generate_captions( $description, $tone, $platform ) {
        if ( empty( $this->api_key ) && $this->api_provider !== 'local' && $this->api_provider !== 'mock' ) {
            return new WP_Error( 
                'no_api_key', 
                __( 'AI service not configured. Please check plugin settings.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        $character_limit = $this->get_character_limit( $platform );
        $prompt = $this->build_prompt( $description, $tone, $platform, $character_limit );
        
        switch ( $this->api_provider ) {
            case 'openai':
                return $this->generate_with_openai( $prompt );
            case 'claude':
                return $this->generate_with_claude( $prompt );
            case 'local':
                return $this->generate_with_local_model( $prompt );
            case 'mock':
            default:
                return $this->generate_mock_captions( $description, $tone, $platform );
        }
    }
    
    /**
     * Generate captions using OpenAI API.
     */
    private function generate_with_openai( $prompt ) {
        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => $this->model,
                'messages' => array(
                    array(
                        'role'    => 'system',
                        'content' => 'You are a social media caption expert who creates engaging, platform-optimized content.',
                    ),
                    array(
                        'role'    => 'user',
                        'content' => $prompt,
                    ),
                ),
                'max_tokens' => 1000,
                'temperature' => 0.7,
                'n' => 1,
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            RWP_Creator_Suite_Error_Logger::log( 'OpenAI API Error: ' . $response->get_error_message() );
            return new WP_Error( 
                'api_error', 
                __( 'Failed to connect to AI service. Please try again later.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( $response_code !== 200 ) {
            $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
            RWP_Creator_Suite_Error_Logger::log( "OpenAI API Error - HTTP {$response_code}: {$error_message}" );
            
            return new WP_Error( 
                'api_error', 
                sprintf( __( 'AI service error: %s', 'rwp-creator-suite' ), $error_message ),
                array( 'status' => $response_code )
            );
        }
        
        if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 
                'ai_error', 
                __( 'Failed to generate captions. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        return $this->parse_captions( $data['choices'][0]['message']['content'] );
    }
    
    /**
     * Generate captions using Claude API (Anthropic).
     */
    private function generate_with_claude( $prompt ) {
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'timeout' => 30,
            'headers' => array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => 1000,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt,
                    ),
                ),
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            RWP_Creator_Suite_Error_Logger::log( 'Claude API Error: ' . $response->get_error_message() );
            return new WP_Error( 
                'api_error', 
                __( 'Failed to connect to AI service. Please try again later.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( $response_code !== 200 ) {
            $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
            RWP_Creator_Suite_Error_Logger::log( "Claude API Error - HTTP {$response_code}: {$error_message}" );
            
            return new WP_Error( 
                'api_error', 
                sprintf( __( 'AI service error: %s', 'rwp-creator-suite' ), $error_message ),
                array( 'status' => $response_code )
            );
        }
        
        if ( ! isset( $data['content'][0]['text'] ) ) {
            return new WP_Error( 
                'ai_error', 
                __( 'Failed to generate captions. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        return $this->parse_captions( $data['content'][0]['text'] );
    }
    
    /**
     * Generate captions using local AI model.
     */
    private function generate_with_local_model( $prompt ) {
        // Placeholder for local AI model integration
        // This would connect to a self-hosted AI model
        return new WP_Error( 
            'not_implemented', 
            __( 'Local AI model not yet implemented.', 'rwp-creator-suite' ),
            array( 'status' => 501 )
        );
    }
    
    /**
     * Generate mock captions for development/fallback.
     */
    private function generate_mock_captions( $description, $tone, $platform ) {
        $tone_variations = array(
            'casual' => array(
                "Check out this amazing {description}! ✨ Perfect for your feed. What do you think? {hashtags}",
                "Loving this {description} moment! 💫 Sometimes the simple things bring the most joy. {hashtags}",
                "{description} vibes hitting different today 🔥 Who else is feeling this energy? {hashtags}"
            ),
            'witty' => array(
                "{description}? More like {description} goals! 😎 {hashtags}",
                "Plot twist: {description} was actually the main character all along 📸 {hashtags}",
                "Instructions unclear, ended up with this epic {description} instead 🤷‍♀️ {hashtags}"
            ),
            'inspirational' => array(
                "Every {description} tells a story of possibility ✨ What story will you write today? {hashtags}",
                "In a world full of ordinary, be a {description} 🌟 Chase your dreams fearlessly. {hashtags}",
                "The beauty in {description} reminds us that magic exists in everyday moments 💫 {hashtags}"
            ),
            'question' => array(
                "What's your favorite thing about {description}? 🤔 Drop your thoughts below! {hashtags}",
                "{description}: love it or leave it? 💭 I'm curious to hear your take! {hashtags}",
                "Quick question: does this {description} spark joy for you too? ✨ {hashtags}"
            ),
            'professional' => array(
                "Presenting: {description}. Excellence in every detail. What are your thoughts on this approach? {hashtags}",
                "Today's focus: {description}. Quality and innovation driving results. {hashtags}",
                "Strategic insight: {description} represents the future of our industry. {hashtags}"
            )
        );
        
        $templates = $tone_variations[ $tone ] ?? $tone_variations['casual'];
        $captions = array();
        
        foreach ( $templates as $template ) {
            $caption_text = str_replace( '{description}', $description, $template );
            $captions[] = array(
                'text' => $caption_text,
                'character_count' => mb_strlen( $caption_text ),
            );
        }
        
        return $captions;
    }
    
    /**
     * Build the AI prompt for caption generation.
     */
    private function build_prompt( $description, $tone, $platform, $character_limit ) {
        $tone_descriptions = array(
            'casual'        => 'friendly, conversational, approachable',
            'witty'         => 'clever, humorous, engaging with wordplay',
            'inspirational' => 'motivational, uplifting, encouraging',
            'question'      => 'engaging with questions that encourage comments',
            'professional'  => 'polished, authoritative, business-appropriate',
        );
        
        $tone_desc = $tone_descriptions[ $tone ] ?? 'casual and engaging';
        
        $platform_notes = array(
            'instagram' => 'Include relevant emoji and hashtag placeholder. Use line breaks for readability.',
            'tiktok'    => 'Keep it punchy and trend-aware. Include emoji and hashtag placeholder.',
            'twitter'   => 'Be concise due to character limit. Use trending topics when relevant.',
            'linkedin'  => 'More professional tone. Focus on industry insights or career growth.',
            'facebook'  => 'Can be longer and more conversational. Include call-to-action questions.',
        );
        
        $platform_note = $platform_notes[ $platform ] ?? $platform_notes['instagram'];
        
        return sprintf(
            "Create 3 different %s captions for %s based on this content description: \"%s\"\n\n" .
            "Requirements:\n" .
            "- Each caption should be under %d characters (leaving room for hashtags)\n" .
            "- %s\n" .
            "- End each caption with {hashtags} as a placeholder for hashtag insertion\n" .
            "- Make each caption distinctly different in approach and style\n" .
            "- Format as a numbered list (1., 2., 3.)\n" .
            "- Focus on engagement and authenticity\n\n" .
            "The tone should be: %s",
            $tone_desc,
            $platform,
            $description,
            $character_limit - 200, // Leave room for hashtags
            $platform_note,
            $tone_desc
        );
    }
    
    /**
     * Parse AI-generated caption response into structured data.
     */
    private function parse_captions( $content ) {
        $lines = explode( "\n", trim( $content ) );
        $captions = array();
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            
            // Match numbered list items (1., 2., 3., etc.)
            if ( preg_match( '/^\d+\.\s*(.+)/', $line, $matches ) ) {
                $caption_text = trim( $matches[1] );
                
                // Remove quotes if present
                $caption_text = trim( $caption_text, '"' );
                
                if ( ! empty( $caption_text ) ) {
                    $captions[] = array(
                        'text' => $caption_text,
                        'character_count' => mb_strlen( $caption_text ),
                    );
                }
            }
        }
        
        // If no numbered captions found, try to split by double newlines
        if ( empty( $captions ) ) {
            $sections = preg_split( '/\n\s*\n/', trim( $content ) );
            
            foreach ( $sections as $section ) {
                $section = trim( $section );
                if ( ! empty( $section ) && mb_strlen( $section ) > 10 ) {
                    $captions[] = array(
                        'text' => $section,
                        'character_count' => mb_strlen( $section ),
                    );
                }
            }
        }
        
        // Ensure we have at least one caption
        if ( empty( $captions ) ) {
            $captions[] = array(
                'text' => trim( $content ),
                'character_count' => mb_strlen( trim( $content ) ),
            );
        }
        
        // Limit to maximum of 5 captions
        return array_slice( $captions, 0, 5 );
    }
    
    /**
     * Get character limit for platform.
     */
    private function get_character_limit( $platform ) {
        $limits = array(
            'instagram' => 2200,
            'tiktok'    => 2200,
            'twitter'   => 280,
            'linkedin'  => 3000,
            'facebook'  => 63206,
        );
        
        return isset( $limits[ $platform ] ) ? $limits[ $platform ] : 2200;
    }
}