<?php
/**
 * AI Service
 * 
 * Handles integration with AI services (OpenAI, Claude, etc.) for various AI tasks
 * including caption generation and content repurposing.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_AI_Service {

    private $api_key;
    private $model = 'gpt-3.5-turbo';
    private $api_provider = 'openai'; // openai, claude, local
    private $key_manager;
    
    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_provider = get_option( 'rwp_creator_suite_ai_provider', 'mock' );
        $this->model = get_option( 'rwp_creator_suite_ai_model', 'gpt-3.5-turbo' );
        
        // Initialize secure key manager
        $this->key_manager = new RWP_Creator_Suite_Key_Manager();
        
        // Get API key securely based on provider
        if ( $this->api_provider === 'openai' || $this->api_provider === 'claude' ) {
            $this->api_key = $this->key_manager->get_api_key( $this->api_provider );
        }
    }
    
    /**
     * Generate content using AI service with custom prompt.
     */
    public function generate_content( $prompt, $context = 'general' ) {
        if ( empty( $this->api_key ) && $this->api_provider !== 'local' && $this->api_provider !== 'mock' ) {
            return new WP_Error( 
                'no_api_key', 
                __( 'AI service not configured. Please check plugin settings.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        switch ( $this->api_provider ) {
            case 'openai':
                return $this->generate_with_openai( $prompt, $context );
            case 'claude':
                return $this->generate_with_claude( $prompt, $context );
            case 'local':
                return $this->generate_with_local_model( $prompt, $context );
            case 'mock':
            default:
                return $this->generate_mock_content( $prompt, $context );
        }
    }
    
    /**
     * Generate captions using AI service.
     */
    public function generate_captions( $description, $tone, $platform ) {
        $character_limit = $this->get_character_limit( $platform );
        $prompt = $this->build_caption_prompt( $description, $tone, $platform, $character_limit );
        
        $result = $this->generate_content( $prompt, 'captions' );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Validate AI response format
        $validation_result = $this->validate_ai_response( $result, 'captions' );
        if ( is_wp_error( $validation_result ) ) {
            // Try to fix common formatting issues
            $result = $this->clean_ai_response( $result );
        }
        
        return $this->parse_captions( $result );
    }
    
    /**
     * Repurpose content for multiple platforms.
     */
    public function repurpose_content( $content, $platforms, $tone = 'professional' ) {
        // Build a single prompt for all platforms
        $prompt = $this->build_multi_platform_repurpose_prompt( $content, $platforms, $tone );
        
        // Make single AI API call
        $result = $this->generate_content( $prompt, 'repurpose' );
        
        if ( is_wp_error( $result ) ) {
            // If AI call fails, return error for all platforms
            $error_result = array();
            foreach ( $platforms as $platform ) {
                $error_result[ $platform ] = array(
                    'success' => false,
                    'error' => $result->get_error_message(),
                );
            }
            return $error_result;
        }
        
        // Validate AI response format before parsing
        $validation_result = $this->validate_ai_response( $result, 'repurpose_multi' );
        if ( is_wp_error( $validation_result ) ) {
            // Try to fix common formatting issues
            $result = $this->clean_ai_response( $result );
        }
        
        // Parse the multi-platform response
        return $this->parse_multi_platform_content( $result, $platforms );
    }
    
    /**
     * Generate content using OpenAI API.
     */
    private function generate_with_openai( $prompt, $context = 'general' ) {
        $system_message = $this->get_system_message( $context );
        
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
                        'content' => $system_message,
                    ),
                    array(
                        'role'    => 'user',
                        'content' => $prompt,
                    ),
                ),
                'max_tokens' => $this->get_max_tokens( $context ),
                'temperature' => $this->get_temperature( $context ),
                'n' => 1,
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            $this->log_error( 'OpenAI API Error: ' . $response->get_error_message() );
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
            $this->log_error( "OpenAI API Error - HTTP {$response_code}: {$error_message}" );
            
            return new WP_Error( 
                'api_error', 
                sprintf( __( 'AI service error: %s', 'rwp-creator-suite' ), $error_message ),
                array( 'status' => $response_code )
            );
        }
        
        if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 
                'ai_error', 
                __( 'Failed to generate content. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        return $data['choices'][0]['message']['content'];
    }
    
    /**
     * Generate content using Claude API (Anthropic).
     */
    private function generate_with_claude( $prompt, $context = 'general' ) {
        $system_message = $this->get_system_message( $context );
        $full_prompt = $system_message . "\n\n" . $prompt;
        
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'timeout' => 30,
            'headers' => array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => $this->get_max_tokens( $context ),
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $full_prompt,
                    ),
                ),
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            $this->log_error( 'Claude API Error: ' . $response->get_error_message() );
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
            $this->log_error( "Claude API Error - HTTP {$response_code}: {$error_message}" );
            
            return new WP_Error( 
                'api_error', 
                sprintf( __( 'AI service error: %s', 'rwp-creator-suite' ), $error_message ),
                array( 'status' => $response_code )
            );
        }
        
        if ( ! isset( $data['content'][0]['text'] ) ) {
            return new WP_Error( 
                'ai_error', 
                __( 'Failed to generate content. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        return $data['content'][0]['text'];
    }
    
    /**
     * Generate content using local AI model.
     */
    private function generate_with_local_model( $prompt, $context = 'general' ) {
        // Placeholder for local AI model integration
        // This would connect to a self-hosted AI model
        return new WP_Error( 
            'not_implemented', 
            __( 'Local AI model not yet implemented.', 'rwp-creator-suite' ),
            array( 'status' => 501 )
        );
    }
    
    /**
     * Generate mock content for development/fallback.
     */
    private function generate_mock_content( $prompt, $context = 'general' ) {
        if ( $context === 'captions' ) {
            return $this->generate_mock_captions_content( $prompt );
        } elseif ( $context === 'repurpose' ) {
            return $this->generate_mock_repurpose_content( $prompt );
        }
        
        return "Mock AI response for: " . substr( $prompt, 0, 100 ) . "...";
    }
    
    /**
     * Generate mock caption content.
     */
    private function generate_mock_captions_content( $prompt ) {
        $mock_captions = array(
            "✨ Capturing those perfect moments that make life beautiful! What's your favorite way to create memories? #memories #lifestyle",
            "🌟 Sometimes the simplest things bring the greatest joy. Finding magic in the everyday moments! #inspiration #gratitude",
            "💫 Ready to embrace whatever comes next! Life is full of amazing surprises waiting to be discovered. #adventure #positivity"
        );
        
        return implode( "\n\n", array_map( function( $caption, $index ) {
            return ($index + 1) . ". " . $caption;
        }, $mock_captions, array_keys( $mock_captions ) ) );
    }
    
    /**
     * Generate mock repurpose content.
     */
    private function generate_mock_repurpose_content( $prompt ) {
        $mock_versions = array(
            "🎯 Transform your ideas into impactful content that resonates with your audience.",
            "💡 Ready to take your content strategy to the next level? Let's explore new possibilities!",
            "✨ Every great story starts with a single idea. What story will you tell today?"
        );
        
        return implode( "\n\n", array_map( function( $version, $index ) {
            return ($index + 1) . ". " . $version;
        }, $mock_versions, array_keys( $mock_versions ) ) );
    }
    
    /**
     * Build the AI prompt for caption generation.
     */
    private function build_caption_prompt( $description, $tone, $platform, $character_limit ) {
        // Get configurable prompts (only if class is available)
        $prompts_config = array();
        if ( class_exists( 'RWP_Creator_Suite_Caption_Admin_Settings' ) ) {
            $prompts_config = RWP_Creator_Suite_Caption_Admin_Settings::get_prompts_config();
        }
        
        // Get tone description from config or fallback
        $tone_desc = 'casual and engaging';
        if ( isset( $prompts_config['tone_descriptions'][ $tone ] ) ) {
            $tone_desc = $prompts_config['tone_descriptions'][ $tone ];
        } else {
            // Fallback to hardcoded values
            $tone_descriptions = array(
                'casual'        => 'friendly, conversational, approachable',
                'witty'         => 'clever, humorous, engaging with wordplay',
                'inspirational' => 'motivational, uplifting, encouraging',
                'question'      => 'engaging with questions that encourage comments',
                'professional'  => 'polished, authoritative, business-appropriate',
            );
            $tone_desc = $tone_descriptions[ $tone ] ?? 'casual and engaging';
        }
        
        // Get platform guidance from config or fallback
        $platform_guidance = '';
        if ( isset( $prompts_config['platform_guidance'][ $platform ] ) ) {
            $platform_guidance = $prompts_config['platform_guidance'][ $platform ];
        } else {
            // Fallback to hardcoded values
            $platform_notes = array(
                'instagram' => 'Include relevant emoji and hashtag placeholder. Use line breaks for readability.',
                'tiktok'    => 'Keep it punchy and trend-aware. Include emoji and hashtag placeholder.',
                'twitter'   => 'Be concise due to character limit. Use trending topics when relevant.',
                'linkedin'  => 'More professional tone. Focus on industry insights or career growth.',
                'facebook'  => 'Can be longer and more conversational. Include call-to-action questions.',
            );
            $platform_guidance = $platform_notes[ $platform ] ?? $platform_notes['instagram'];
        }
        
        // Use configurable prompt template or fallback
        if ( isset( $prompts_config['prompt_templates']['caption_generation'] ) ) {
            $template = $prompts_config['prompt_templates']['caption_generation'];
            
            // Replace placeholders
            return str_replace(
                array( '{tone_desc}', '{platform}', '{description}', '{character_limit}', '{platform_guidance}', '{hashtags}' ),
                array( $tone_desc, $platform, $description, $character_limit - 200, $platform_guidance, '{hashtags}' ),
                $template
            );
        }
        
        // Fallback to hardcoded prompt
        return sprintf(
            "Create 3 different %s captions for %s based on this content description: \"%s\"\n\n" .
            "CRITICAL FORMATTING REQUIREMENTS:\n" .
            "- You MUST respond with exactly 3 numbered items\n" .
            "- Use ONLY this format: \"1. [caption]\n\n2. [caption]\n\n3. [caption]\"\n" .
            "- Do NOT use markdown formatting (no **, __, or other markup)\n" .
            "- Each numbered item must be complete on its own\n" .
            "- Each caption should be under %d characters (leaving room for hashtags)\n\n" .
            "CONTENT REQUIREMENTS:\n" .
            "- %s\n" .
            "- End each caption with {hashtags} as a placeholder for hashtag insertion\n" .
            "- Make each caption distinctly different in approach and style\n" .
            "- Focus on engagement and authenticity\n" .
            "- The tone should be: %s\n\n" .
            "EXAMPLE FORMAT:\n" .
            "1. First caption text here {hashtags}\n\n" .
            "2. Second caption text here {hashtags}\n\n" .
            "3. Third caption text here {hashtags}",
            $tone_desc,
            $platform,
            $description,
            $character_limit - 200,
            $platform_guidance,
            $tone_desc
        );
    }
    
    /**
     * Build the AI prompt for content repurposing.
     */
    private function build_repurpose_prompt( $content, $platform, $tone, $character_limit ) {
        // Get configurable prompts (only if class is available)
        $prompts_config = array();
        if ( class_exists( 'RWP_Creator_Suite_Caption_Admin_Settings' ) ) {
            $prompts_config = RWP_Creator_Suite_Caption_Admin_Settings::get_prompts_config();
        }
        
        // Get tone description from config or fallback
        $tone_desc = 'professional';
        if ( isset( $prompts_config['tone_descriptions'][ $tone ] ) ) {
            $tone_desc = $prompts_config['tone_descriptions'][ $tone ];
        } else {
            // Fallback to hardcoded values
            $tone_descriptions = array(
                'professional' => 'polished, authoritative, business-appropriate',
                'casual'       => 'friendly, conversational, approachable', 
                'engaging'     => 'compelling, interactive, encourages responses',
                'informative'  => 'educational, fact-focused, clear and concise',
            );
            $tone_desc = $tone_descriptions[ $tone ] ?? 'professional';
        }
        
        // Get platform guidance from config or fallback
        $platform_guidance = '';
        if ( isset( $prompts_config['platform_guidance'][ $platform ] ) ) {
            $platform_guidance = $prompts_config['platform_guidance'][ $platform ];
        } else {
            // Fallback to hardcoded values
            $platform_guidance_map = array(
                'twitter'   => 'Create concise, impactful posts that capture key points. Use threads if needed.',
                'linkedin'  => 'Focus on professional insights and industry relevance. Include thought-provoking questions.',
                'facebook'  => 'Create engaging posts that encourage discussion. Can be conversational and longer.',
                'instagram' => 'Visual-focused content with engaging captions. Include relevant emojis and hashtags.',
            );
            $platform_guidance = $platform_guidance_map[ $platform ] ?? $platform_guidance_map['twitter'];
        }
        
        // Use configurable prompt template or fallback
        if ( isset( $prompts_config['prompt_templates']['single_repurpose'] ) ) {
            $template = $prompts_config['prompt_templates']['single_repurpose'];
            
            // Replace placeholders
            return str_replace(
                array( '{platform}', '{content}', '{character_limit}', '{platform_guidance}', '{tone_desc}' ),
                array( $platform, $content, $character_limit - 100, $platform_guidance, $tone_desc ),
                $template
            );
        }
        
        // Fallback to hardcoded prompt
        return sprintf(
            "Repurpose the following content for %s:\n\n\"%s\"\n\n" .
            "CRITICAL FORMATTING REQUIREMENTS:\n" .
            "- You MUST respond with exactly 3 numbered items\n" .
            "- Use ONLY this format: \"1. [content]\n\n2. [content]\n\n3. [content]\"\n" .
            "- Do NOT use markdown formatting (no **, __, or other markup)\n" .
            "- Do NOT include sub-bullets or nested content\n" .
            "- Each numbered item must be complete on its own\n" .
            "- Keep each version under %d characters\n\n" .
            "CONTENT REQUIREMENTS:\n" .
            "- Create 3 different versions optimized for %s\n" .
            "- %s\n" .
            "- Maintain the core message while adapting the style and format\n" .
            "- Use a %s tone\n" .
            "- Extract and highlight the most important points\n" .
            "- Make each version distinctly different in approach\n\n" .
            "EXAMPLE FORMAT:\n" .
            "1. First version of the repurposed content here.\n\n" .
            "2. Second version of the repurposed content here.\n\n" .
            "3. Third version of the repurposed content here.",
            $platform,
            $content,
            $character_limit - 100,
            $platform,
            $platform_guidance,
            $tone_desc
        );
    }
    
    /**
     * Build the AI prompt for multi-platform content repurposing (single API call).
     */
    private function build_multi_platform_repurpose_prompt( $content, $platforms, $tone ) {
        // Get configurable prompts (only if class is available)
        $prompts_config = array();
        if ( class_exists( 'RWP_Creator_Suite_Caption_Admin_Settings' ) ) {
            $prompts_config = RWP_Creator_Suite_Caption_Admin_Settings::get_prompts_config();
        }
        
        // Get tone description from config or fallback
        $tone_desc = 'professional';
        if ( isset( $prompts_config['tone_descriptions'][ $tone ] ) ) {
            $tone_desc = $prompts_config['tone_descriptions'][ $tone ];
        } else {
            // Fallback to hardcoded values
            $tone_descriptions = array(
                'professional' => 'polished, authoritative, business-appropriate',
                'casual'       => 'friendly, conversational, approachable', 
                'engaging'     => 'compelling, interactive, encourages responses',
                'informative'  => 'educational, fact-focused, clear and concise',
            );
            $tone_desc = $tone_descriptions[ $tone ] ?? 'professional';
        }
        
        // Build platform-specific guidelines
        $platform_instructions = array();
        foreach ( $platforms as $platform ) {
            $character_limit = $this->get_character_limit( $platform );
            
            // Get platform guidance from config or fallback
            if ( isset( $prompts_config['platform_guidance'][ $platform ] ) ) {
                $guidance = $prompts_config['platform_guidance'][ $platform ];
            } else {
                // Fallback to hardcoded values
                $platform_guidance_fallback = array(
                    'twitter'   => 'Twitter: Concise, impactful posts under 280 characters.',
                    'linkedin'  => 'LinkedIn: Professional insights and industry relevance under 3000 characters.',
                    'facebook'  => 'Facebook: Engaging, conversational posts under 63206 characters.',
                    'instagram' => 'Instagram: Visual-focused captions under 2200 characters.',
                );
                $guidance = $platform_guidance_fallback[ $platform ] ?? $platform_guidance_fallback['twitter'];
            }
            
            $platform_instructions[] = "- {$guidance}";
        }
        
        $platform_list = implode( ', ', $platforms );
        $platform_guidance_text = implode( "\n", $platform_instructions );
        $platform_order = strtoupper( implode( ', ', $platforms ) );
        
        // Use configurable prompt template or fallback
        if ( isset( $prompts_config['prompt_templates']['multi_repurpose'] ) ) {
            $template = $prompts_config['prompt_templates']['multi_repurpose'];
            
            // Replace placeholders
            return str_replace(
                array( '{platform_list}', '{content}', '{platform_order}', '{platform_guidance_text}', '{tone_desc}' ),
                array( $platform_list, $content, $platform_order, $platform_guidance_text, $tone_desc ),
                $template
            );
        }
        
        // Fallback to hardcoded prompt
        return sprintf(
            "Repurpose the following content for multiple social media platforms (%s):\n\n\"%s\"\n\n" .
            "CRITICAL FORMATTING REQUIREMENTS:\n" .
            "- You MUST create content for each platform in this EXACT order: %s\n" .
            "- For each platform, provide exactly 3 numbered versions\n" .
            "- Use this format: \"PLATFORM_NAME:\n1. [content]\n\n2. [content]\n\n3. [content]\n\n\"\n" .
            "- Do NOT use markdown formatting (no **, __, or other markup)\n" .
            "- Each numbered item must be complete and standalone\n" .
            "- Separate each platform section with a blank line\n\n" .
            "PLATFORM REQUIREMENTS:\n" .
            "%s\n\n" .
            "CONTENT REQUIREMENTS:\n" .
            "- Maintain the core message while adapting style for each platform\n" .
            "- Use a %s tone throughout\n" .
            "- Extract and highlight the most important points\n" .
            "- Make each version within a platform distinctly different\n\n" .
            "EXAMPLE FORMAT:\n" .
            "TWITTER:\n" .
            "1. First Twitter version here.\n\n" .
            "2. Second Twitter version here.\n\n" .
            "3. Third Twitter version here.\n\n" .
            "LINKEDIN:\n" .
            "1. First LinkedIn version here.\n\n" .
            "2. Second LinkedIn version here.\n\n" .
            "3. Third LinkedIn version here.",
            $platform_list,
            $content,
            $platform_order,
            $platform_guidance_text,
            $tone_desc
        );
    }
    
    /**
     * Get system message based on context.
     */
    private function get_system_message( $context ) {
        // Get configurable prompts (only if class is available)
        $prompts_config = array();
        if ( class_exists( 'RWP_Creator_Suite_Caption_Admin_Settings' ) ) {
            $prompts_config = RWP_Creator_Suite_Caption_Admin_Settings::get_prompts_config();
        }
        
        // Use configured system messages with fallback to defaults
        if ( isset( $prompts_config['system_messages'][ $context ] ) ) {
            return $prompts_config['system_messages'][ $context ];
        }
        
        // Fallback to hardcoded defaults
        switch ( $context ) {
            case 'captions':
                return 'You are a social media caption expert who creates engaging, platform-optimized content. Always follow formatting instructions exactly as specified. Use only plain text without markdown formatting unless explicitly requested.';
            case 'repurpose':
                return 'You are a content strategist who specializes in adapting content for different social media platforms. You MUST follow formatting instructions precisely. Always respond with exactly the number of items requested, using simple numbered format (1., 2., 3.) with no markdown formatting, sub-bullets, or complex structure. Each numbered item should be complete and standalone.';
            default:
                return 'You are a helpful AI assistant focused on creating high-quality content. Follow all formatting instructions exactly as provided.';
        }
    }
    
    /**
     * Get max tokens based on context.
     */
    private function get_max_tokens( $context ) {
        switch ( $context ) {
            case 'captions':
                return 1000;
            case 'repurpose':
                return 1500;
            default:
                return 1000;
        }
    }
    
    /**
     * Get temperature based on context.
     */
    private function get_temperature( $context ) {
        switch ( $context ) {
            case 'captions':
                return 0.7;
            case 'repurpose':
                return 0.6;
            default:
                return 0.7;
        }
    }
    
    /**
     * Parse AI-generated caption response into structured data.
     */
    private function parse_captions( $content ) {
        return $this->parse_numbered_content( $content );
    }
    
    /**
     * Parse AI-generated repurposed content into structured data.
     */
    private function parse_repurposed_content( $content ) {
        // With improved prompts and validation, we can use the simpler parsing method
        return $this->parse_numbered_content( $content );
    }
    
    /**
     * Validate AI response format for consistency.
     */
    private function validate_ai_response( $content, $context ) {
        if ( empty( $content ) ) {
            return new WP_Error( 'empty_response', 'AI response is empty' );
        }
        
        switch ( $context ) {
            case 'repurpose':
                // Check for numbered list pattern
                if ( ! preg_match( '/^\s*1\.\s+/', $content ) ) {
                    return new WP_Error( 'invalid_format', 'Response does not start with numbered list' );
                }
                
                // Count numbered items
                preg_match_all( '/^\s*\d+\.\s+/m', $content, $matches );
                $count = count( $matches[0] );
                
                if ( $count < 2 || $count > 5 ) {
                    return new WP_Error( 'invalid_count', "Expected 3 items, found {$count}" );
                }
                break;
                
            case 'repurpose_multi':
                // Check for platform headers (e.g., "TWITTER:", "LINKEDIN:")
                if ( ! preg_match( '/^[A-Z]+:\s*$/m', $content ) ) {
                    return new WP_Error( 'invalid_format', 'Multi-platform response missing platform headers' );
                }
                
                // Count numbered items - should have 3 per platform
                preg_match_all( '/^\s*\d+\.\s+/m', $content, $matches );
                $total_items = count( $matches[0] );
                
                // Count platforms
                preg_match_all( '/^[A-Z]+:\s*$/m', $content, $platform_matches );
                $platform_count = count( $platform_matches[0] );
                
                $expected_items = $platform_count * 3;
                if ( $total_items !== $expected_items ) {
                    return new WP_Error( 'invalid_count', "Expected {$expected_items} items ({$platform_count} platforms × 3), found {$total_items}" );
                }
                break;
                
            case 'captions':
                // Similar validation for captions
                preg_match_all( '/^\s*\d+\.\s+/m', $content, $matches );
                $count = count( $matches[0] );
                
                if ( $count < 2 || $count > 5 ) {
                    return new WP_Error( 'invalid_count', "Expected 3 captions, found {$count}" );
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Clean AI response to fix common formatting issues.
     */
    private function clean_ai_response( $content ) {
        // Remove markdown bold/italic formatting
        $content = preg_replace( '/\*\*(.*?)\*\*/', '$1', $content );
        $content = preg_replace( '/\*(.*?)\*/', '$1', $content );
        $content = preg_replace( '/__(.*?)__/', '$1', $content );
        $content = preg_replace( '/_(.*?)_/', '$1', $content );
        
        // Remove markdown headers
        $content = preg_replace( '/^#+\s+/m', '', $content );
        
        // Clean up excessive whitespace but preserve structure
        $content = preg_replace( '/\n{3,}/', "\n\n", $content );
        
        // Ensure proper numbered format
        $content = preg_replace( '/^(\d+)\)\s+/m', '$1. ', $content );
        
        return trim( $content );
    }

    /**
     * Parse numbered content list into structured array.
     */
    private function parse_numbered_content( $content ) {
        $lines = explode( "\n", trim( $content ) );
        $items = array();
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            
            // Match numbered list items (1., 2., 3., etc.)
            if ( preg_match( '/^\d+\.\s*(.+)/', $line, $matches ) ) {
                $item_text = trim( $matches[1] );
                
                // Remove quotes if present
                $item_text = trim( $item_text, '"' );
                
                if ( ! empty( $item_text ) ) {
                    $items[] = array(
                        'text' => $item_text,
                        'character_count' => mb_strlen( $item_text ),
                    );
                }
            }
        }
        
        // If no numbered items found, try to split by double newlines
        if ( empty( $items ) ) {
            $sections = preg_split( '/\n\s*\n/', trim( $content ) );
            
            foreach ( $sections as $section ) {
                $section = trim( $section );
                if ( ! empty( $section ) && mb_strlen( $section ) > 10 ) {
                    $items[] = array(
                        'text' => $section,
                        'character_count' => mb_strlen( $section ),
                    );
                }
            }
        }
        
        // Ensure we have at least one item
        if ( empty( $items ) ) {
            $items[] = array(
                'text' => trim( $content ),
                'character_count' => mb_strlen( trim( $content ) ),
            );
        }
        
        // Limit to maximum of 5 items
        return array_slice( $items, 0, 5 );
    }
    
    /**
     * Parse multi-platform AI response into structured data.
     */
    private function parse_multi_platform_content( $content, $platforms ) {
        $result = array();
        
        // Split content by platform headers
        $sections = preg_split( '/^([A-Z]+):\s*$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
        
        // Remove empty first section if it exists
        if ( isset( $sections[0] ) && trim( $sections[0] ) === '' ) {
            array_shift( $sections );
        }
        
        // Process sections in pairs (platform name, content)
        for ( $i = 0; $i < count( $sections ); $i += 2 ) {
            if ( ! isset( $sections[$i] ) || ! isset( $sections[$i + 1] ) ) {
                continue;
            }
            
            $platform_name = strtolower( trim( $sections[$i] ) );
            $platform_content = trim( $sections[$i + 1] );
            
            // Skip if this platform wasn't requested
            if ( ! in_array( $platform_name, $platforms, true ) ) {
                continue;
            }
            
            // Parse the numbered content for this platform
            $versions = $this->parse_numbered_content( $platform_content );
            
            // Validate we got 3 versions
            if ( count( $versions ) !== 3 ) {
                // Expected 3 versions but got different count - continue processing
            }
            
            $result[ $platform_name ] = array(
                'success' => true,
                'versions' => $versions,
                'character_limit' => $this->get_character_limit( $platform_name ),
            );
        }
        
        // Add error results for any requested platforms that weren't found
        foreach ( $platforms as $platform ) {
            if ( ! isset( $result[ $platform ] ) ) {
                $result[ $platform ] = array(
                    'success' => false,
                    'error' => "Content for {$platform} was not found in AI response",
                );
            }
        }
        
        return $result;
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
    
    /**
     * Check shared rate limiting for AI features.
     */
    public function check_rate_limit( $feature = 'ai_generation' ) {
        $user_id = get_current_user_id();
        $is_guest = ! $user_id;
        
        if ( $is_guest ) {
            // Use IP-based rate limiting for guests
            $identifier = $this->get_client_ip();
            $limit = get_option( 'rwp_creator_suite_rate_limit_guest', 5 );
        } else {
            $identifier = $user_id;
            // Check if user is premium
            $is_premium = apply_filters( 'rwp_creator_suite_is_premium_user', false, $user_id );
            $limit = $is_premium 
                ? get_option( 'rwp_creator_suite_rate_limit_premium', 50 )
                : get_option( 'rwp_creator_suite_rate_limit_free', 10 );
        }
        
        // Apply filters for customization
        $limit = apply_filters( 'rwp_creator_suite_rate_limit', $limit, $identifier, $feature );
        
        // Use shared transient key for all AI features
        $transient_key = 'rwp_ai_rate_limit_' . hash( 'sha256', $identifier . wp_salt( 'secure_auth' ) );
        $current_usage = get_transient( $transient_key );
        
        if ( false === $current_usage ) {
            $current_usage = 0;
        }
        
        if ( $current_usage >= $limit ) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __( 'Rate limit exceeded. You can make %d AI requests per hour.', 'rwp-creator-suite' ),
                    $limit
                ),
                array( 'status' => 429 )
            );
        }
        
        return true;
    }
    
    /**
     * Track usage for shared rate limiting.
     */
    public function track_usage( $usage_count = 1, $feature = 'ai_generation' ) {
        $user_id = get_current_user_id();
        $is_guest = ! $user_id;
        
        if ( $is_guest ) {
            $identifier = $this->get_client_ip();
        } else {
            $identifier = $user_id;
        }
        
        // Update shared rate limiting counter
        $transient_key = 'rwp_ai_rate_limit_' . hash( 'sha256', $identifier . wp_salt( 'secure_auth' ) );
        $current_usage = get_transient( $transient_key );
        
        if ( false === $current_usage ) {
            $current_usage = 0;
        }
        
        $new_usage = $current_usage + $usage_count;
        set_transient( $transient_key, $new_usage, HOUR_IN_SECONDS );
        
        // Track detailed usage statistics for logged-in users
        if ( ! $is_guest ) {
            $this->track_detailed_usage( $user_id, $feature, $usage_count );
        }
    }
    
    /**
     * Get usage statistics for current user.
     */
    public function get_usage_stats() {
        $user_id = get_current_user_id();
        $is_guest = ! $user_id;
        
        if ( $is_guest ) {
            // For guests, only show rate limit info
            $identifier = $this->get_client_ip();
            $transient_key = 'rwp_ai_rate_limit_' . hash( 'sha256', $identifier . wp_salt( 'secure_auth' ) );
            $current_usage = get_transient( $transient_key );
            $limit = get_option( 'rwp_creator_suite_rate_limit_guest', 5 );
            
            return array(
                'current_hour_usage' => $current_usage ? $current_usage : 0,
                'hourly_limit' => $limit,
                'remaining' => max( 0, $limit - ( $current_usage ? $current_usage : 0 ) ),
                'is_guest' => true,
            );
        }
        
        // For logged-in users
        $is_premium = apply_filters( 'rwp_creator_suite_is_premium_user', false, $user_id );
        $limit = $is_premium 
            ? get_option( 'rwp_creator_suite_rate_limit_premium', 50 )
            : get_option( 'rwp_creator_suite_rate_limit_free', 10 );
            
        $transient_key = 'rwp_ai_rate_limit_' . hash( 'sha256', $user_id . wp_salt( 'secure_auth' ) );
        $current_usage = get_transient( $transient_key );
        
        // Get detailed usage stats
        $total_usage = get_user_meta( $user_id, 'rwp_ai_total_usage', true );
        $current_month = date( 'Y-m' );
        $monthly_usage = get_user_meta( $user_id, "rwp_ai_usage_{$current_month}", true );
        
        return array(
            'current_hour_usage' => $current_usage ? $current_usage : 0,
            'hourly_limit' => $limit,
            'remaining' => max( 0, $limit - ( $current_usage ? $current_usage : 0 ) ),
            'total_usage' => $total_usage ? $total_usage : 0,
            'monthly_usage' => $monthly_usage ? $monthly_usage : 0,
            'is_premium' => $is_premium,
            'is_guest' => false,
        );
    }
    
    /**
     * Track detailed usage statistics for logged-in users.
     */
    private function track_detailed_usage( $user_id, $feature, $usage_count ) {
        // Track total usage across all features
        $total_usage = get_user_meta( $user_id, 'rwp_ai_total_usage', true );
        if ( ! $total_usage ) {
            $total_usage = 0;
        }
        update_user_meta( $user_id, 'rwp_ai_total_usage', $total_usage + $usage_count );
        
        // Track monthly usage
        $current_month = date( 'Y-m' );
        $monthly_usage = get_user_meta( $user_id, "rwp_ai_usage_{$current_month}", true );
        if ( ! $monthly_usage ) {
            $monthly_usage = 0;
        }
        update_user_meta( $user_id, "rwp_ai_usage_{$current_month}", $monthly_usage + $usage_count );
        
        // Track feature-specific usage
        $feature_key = "rwp_ai_usage_{$feature}_{$current_month}";
        $feature_usage = get_user_meta( $user_id, $feature_key, true );
        if ( ! $feature_usage ) {
            $feature_usage = 0;
        }
        update_user_meta( $user_id, $feature_key, $feature_usage + $usage_count );
    }
    
    /**
     * Get client IP address for guest rate limiting.
     */
    private function get_client_ip() {
        // Check for various headers that might contain the real IP
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ( $ip_headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip_list = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ip_list[0] );
                
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Log errors securely with fallback.
     */
    private function log_error( $message ) {
        if ( class_exists( 'RWP_Creator_Suite_Error_Logger' ) ) {
            RWP_Creator_Suite_Error_Logger::log( $message );
        } else {
            // Fallback to WordPress error logging
            error_log( 'RWP Creator Suite - ' . $message );
        }
    }
}