<?php
/**
 * Template Manager
 * 
 * Handles user template creation, storage, and management.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Template_Manager {

    /**
     * Save user template.
     */
    public function save_user_template( $user_id, $template_data ) {
        // Validate required fields
        $required_fields = array( 'name', 'category', 'template' );
        foreach ( $required_fields as $field ) {
            if ( empty( $template_data[ $field ] ) ) {
                return new WP_Error( 
                    'missing_field', 
                    sprintf( __( 'Missing required field: %s', 'rwp-creator-suite' ), $field ),
                    array( 'status' => 400 )
                );
            }
        }
        
        // Create template object
        $template = array(
            'id'         => wp_generate_uuid4(),
            'name'       => sanitize_text_field( $template_data['name'] ),
            'category'   => sanitize_text_field( $template_data['category'] ),
            'template'   => sanitize_textarea_field( $template_data['template'] ),
            'variables'  => $this->extract_template_variables( $template_data['template'] ),
            'platforms'  => isset( $template_data['platforms'] ) ? 
                           array_map( 'sanitize_text_field', $template_data['platforms'] ) : 
                           array( 'instagram' ),
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );
        
        // Get existing templates
        $templates = get_user_meta( $user_id, 'rwp_caption_templates', true );
        if ( ! is_array( $templates ) ) {
            $templates = array();
        }
        
        // Check for duplicate names
        foreach ( $templates as $existing_template ) {
            if ( $existing_template['name'] === $template['name'] ) {
                return new WP_Error( 
                    'duplicate_name', 
                    __( 'A template with this name already exists.', 'rwp-creator-suite' ),
                    array( 'status' => 409 )
                );
            }
        }
        
        // Add new template
        $templates[] = $template;
        
        // Limit to 50 templates per user
        if ( count( $templates ) > 50 ) {
            return new WP_Error( 
                'template_limit', 
                __( 'Maximum number of templates (50) reached. Please delete some templates first.', 'rwp-creator-suite' ),
                array( 'status' => 429 )
            );
        }
        
        // Save templates
        $result = update_user_meta( $user_id, 'rwp_caption_templates', $templates );
        
        if ( ! $result ) {
            return new WP_Error( 
                'save_failed', 
                __( 'Failed to save template. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        return $template['id'];
    }
    
    /**
     * Get user templates.
     */
    public function get_user_templates( $user_id, $category = null ) {
        $templates = get_user_meta( $user_id, 'rwp_caption_templates', true );
        if ( ! is_array( $templates ) ) {
            return array();
        }
        
        // Filter by category if specified
        if ( $category && $category !== 'all' ) {
            $templates = array_filter( $templates, function( $template ) use ( $category ) {
                return $template['category'] === $category;
            } );
        }
        
        // Sort by creation date (newest first)
        usort( $templates, function( $a, $b ) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        } );
        
        return array_values( $templates );
    }
    
    /**
     * Get single user template.
     */
    public function get_user_template( $user_id, $template_id ) {
        $templates = $this->get_user_templates( $user_id );
        
        foreach ( $templates as $template ) {
            if ( $template['id'] === $template_id ) {
                return $template;
            }
        }
        
        return null;
    }
    
    /**
     * Update user template.
     */
    public function update_user_template( $user_id, $template_id, $template_data ) {
        $templates = get_user_meta( $user_id, 'rwp_caption_templates', true );
        if ( ! is_array( $templates ) ) {
            return new WP_Error( 
                'template_not_found', 
                __( 'Template not found.', 'rwp-creator-suite' ),
                array( 'status' => 404 )
            );
        }
        
        $template_index = null;
        foreach ( $templates as $index => $template ) {
            if ( $template['id'] === $template_id ) {
                $template_index = $index;
                break;
            }
        }
        
        if ( $template_index === null ) {
            return new WP_Error( 
                'template_not_found', 
                __( 'Template not found.', 'rwp-creator-suite' ),
                array( 'status' => 404 )
            );
        }
        
        // Update template fields
        if ( isset( $template_data['name'] ) ) {
            $templates[ $template_index ]['name'] = sanitize_text_field( $template_data['name'] );
        }
        if ( isset( $template_data['category'] ) ) {
            $templates[ $template_index ]['category'] = sanitize_text_field( $template_data['category'] );
        }
        if ( isset( $template_data['template'] ) ) {
            $templates[ $template_index ]['template'] = sanitize_textarea_field( $template_data['template'] );
            $templates[ $template_index ]['variables'] = $this->extract_template_variables( $template_data['template'] );
        }
        if ( isset( $template_data['platforms'] ) ) {
            $templates[ $template_index ]['platforms'] = array_map( 'sanitize_text_field', $template_data['platforms'] );
        }
        
        $templates[ $template_index ]['updated_at'] = current_time( 'mysql' );
        
        $result = update_user_meta( $user_id, 'rwp_caption_templates', $templates );
        
        if ( ! $result ) {
            return new WP_Error( 
                'update_failed', 
                __( 'Failed to update template. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        return $templates[ $template_index ];
    }
    
    /**
     * Delete user template.
     */
    public function delete_user_template( $user_id, $template_id ) {
        $templates = get_user_meta( $user_id, 'rwp_caption_templates', true );
        if ( ! is_array( $templates ) ) {
            return new WP_Error( 
                'template_not_found', 
                __( 'Template not found.', 'rwp-creator-suite' ),
                array( 'status' => 404 )
            );
        }
        
        $original_count = count( $templates );
        $templates = array_filter( $templates, function( $template ) use ( $template_id ) {
            return $template['id'] !== $template_id;
        } );
        
        if ( count( $templates ) === $original_count ) {
            return new WP_Error( 
                'template_not_found', 
                __( 'Template not found.', 'rwp-creator-suite' ),
                array( 'status' => 404 )
            );
        }
        
        $result = update_user_meta( $user_id, 'rwp_caption_templates', array_values( $templates ) );
        
        if ( ! $result ) {
            return new WP_Error( 
                'delete_failed', 
                __( 'Failed to delete template. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        return true;
    }
    
    /**
     * Get built-in templates.
     */
    public function get_built_in_templates() {
        return array(
            array(
                'id' => 'product-launch',
                'name' => __( 'Product Launch', 'rwp-creator-suite' ),
                'category' => 'business',
                'template' => '🚀 Excited to introduce {product}!' . "\n\n" .
                             '{description}' . "\n\n" .
                             '✨ Key features:' . "\n" .
                             '• {feature1}' . "\n" .
                             '• {feature2}' . "\n" .
                             '• {feature3}' . "\n\n" .
                             'What do you think? Drop a 💭 below!' . "\n\n" .
                             '{hashtags}',
                'variables' => array( 'product', 'description', 'feature1', 'feature2', 'feature3', 'hashtags' ),
                'platforms' => array( 'instagram', 'facebook', 'linkedin' ),
                'is_built_in' => true,
            ),
            array(
                'id' => 'behind-scenes',
                'name' => __( 'Behind the Scenes', 'rwp-creator-suite' ),
                'category' => 'personal',
                'template' => 'Taking you behind the scenes of {activity} 🎬' . "\n\n" .
                             '{insight}' . "\n\n" .
                             'I never expected {surprise}!' . "\n\n" .
                             'What\'s something surprising about your work?' . "\n\n" .
                             '{hashtags}',
                'variables' => array( 'activity', 'insight', 'surprise', 'hashtags' ),
                'platforms' => array( 'instagram', 'tiktok', 'facebook' ),
                'is_built_in' => true,
            ),
            array(
                'id' => 'question-engage',
                'name' => __( 'Engagement Question', 'rwp-creator-suite' ),
                'category' => 'engagement',
                'template' => '{question} 🤔' . "\n\n" .
                             'A) {optionA}' . "\n" .
                             'B) {optionB}' . "\n" .
                             'C) {optionC}' . "\n\n" .
                             'Vote in the comments! I\'ll share the results in my stories.' . "\n\n" .
                             '{hashtags}',
                'variables' => array( 'question', 'optionA', 'optionB', 'optionC', 'hashtags' ),
                'platforms' => array( 'instagram', 'facebook', 'twitter' ),
                'is_built_in' => true,
            ),
            array(
                'id' => 'motivational-monday',
                'name' => __( 'Motivational Monday', 'rwp-creator-suite' ),
                'category' => 'engagement',
                'template' => '✨ Monday Motivation ✨' . "\n\n" .
                             '{inspirational_message}' . "\n\n" .
                             'Remember: {reminder}' . "\n\n" .
                             'What\'s motivating you this week? Share below! 👇' . "\n\n" .
                             '{hashtags}',
                'variables' => array( 'inspirational_message', 'reminder', 'hashtags' ),
                'platforms' => array( 'instagram', 'linkedin', 'facebook' ),
                'is_built_in' => true,
            ),
            array(
                'id' => 'user-generated-content',
                'name' => __( 'User Generated Content', 'rwp-creator-suite' ),
                'category' => 'engagement',
                'template' => 'Loving this amazing content from {username}! 📸' . "\n\n" .
                             '{content_description}' . "\n\n" .
                             'Tag someone who would love this too! 👇' . "\n\n" .
                             'Share your {content_type} using {branded_hashtag} for a chance to be featured!' . "\n\n" .
                             '{hashtags}',
                'variables' => array( 'username', 'content_description', 'content_type', 'branded_hashtag', 'hashtags' ),
                'platforms' => array( 'instagram', 'facebook', 'tiktok' ),
                'is_built_in' => true,
            ),
            array(
                'id' => 'tutorial-tip',
                'name' => __( 'Tutorial/Tip', 'rwp-creator-suite' ),
                'category' => 'business',
                'template' => '💡 Pro Tip: {tip_title}' . "\n\n" .
                             'Here\'s how to {action}:' . "\n\n" .
                             '1️⃣ {step1}' . "\n" .
                             '2️⃣ {step2}' . "\n" .
                             '3️⃣ {step3}' . "\n\n" .
                             'Save this post for later! What tips would you add?' . "\n\n" .
                             '{hashtags}',
                'variables' => array( 'tip_title', 'action', 'step1', 'step2', 'step3', 'hashtags' ),
                'platforms' => array( 'instagram', 'linkedin', 'facebook' ),
                'is_built_in' => true,
            ),
        );
    }
    
    /**
     * Extract template variables from template text.
     */
    private function extract_template_variables( $template_text ) {
        preg_match_all( '/\{([^}]+)\}/', $template_text, $matches );
        return array_unique( $matches[1] );
    }
    
    /**
     * Replace template variables with values.
     */
    public function replace_template_variables( $template_text, $variables ) {
        foreach ( $variables as $key => $value ) {
            $template_text = str_replace( '{' . $key . '}', $value, $template_text );
        }
        
        return $template_text;
    }
    
    /**
     * Validate template content.
     */
    public function validate_template( $template_data ) {
        $errors = array();
        
        // Check required fields
        if ( empty( $template_data['name'] ) ) {
            $errors[] = __( 'Template name is required.', 'rwp-creator-suite' );
        }
        
        if ( empty( $template_data['template'] ) ) {
            $errors[] = __( 'Template content is required.', 'rwp-creator-suite' );
        }
        
        if ( empty( $template_data['category'] ) ) {
            $errors[] = __( 'Template category is required.', 'rwp-creator-suite' );
        }
        
        // Check name length
        if ( mb_strlen( $template_data['name'] ) > 100 ) {
            $errors[] = __( 'Template name must be 100 characters or less.', 'rwp-creator-suite' );
        }
        
        // Check template length
        if ( mb_strlen( $template_data['template'] ) > 5000 ) {
            $errors[] = __( 'Template content must be 5000 characters or less.', 'rwp-creator-suite' );
        }
        
        // Validate category
        $valid_categories = array( 'business', 'personal', 'engagement', 'other' );
        if ( ! in_array( $template_data['category'], $valid_categories, true ) ) {
            $errors[] = __( 'Invalid template category.', 'rwp-creator-suite' );
        }
        
        // Validate platforms
        if ( isset( $template_data['platforms'] ) && is_array( $template_data['platforms'] ) ) {
            $valid_platforms = array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' );
            $invalid_platforms = array_diff( $template_data['platforms'], $valid_platforms );
            if ( ! empty( $invalid_platforms ) ) {
                $errors[] = sprintf( 
                    __( 'Invalid platforms: %s', 'rwp-creator-suite' ), 
                    implode( ', ', $invalid_platforms ) 
                );
            }
        }
        
        return empty( $errors ) ? true : $errors;
    }
}