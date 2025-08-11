/**
 * Hashtag Analysis Admin Settings
 * 
 * JavaScript for admin settings page functionality.
 */

(function($) {
    'use strict';

    const config = window.rwpHashtagAnalysisAdmin || {};

    $(document).ready(function() {
        initializeSettings();
    });

    function initializeSettings() {
        // Test API connections button
        $('#test-api-connections').on('click', testApiConnections);
        
        // Auto-save form data to localStorage
        $('form input, form select').on('change', saveFormData);
        
        // Load saved form data
        loadFormData();
        
        // Show/hide credential fields based on configuration
        toggleCredentialFields();
        
        // Add field validation
        addFieldValidation();
    }

    function testApiConnections() {
        const $button = $('#test-api-connections');
        const $results = $('#api-test-results');
        
        // Show loading state
        $button.prop('disabled', true).text(config.strings.testing || 'Testing...');
        $results.show().html('<div class="notice notice-info"><p>' + (config.strings.testing || 'Testing connections...') + '</p></div>');
        
        // Make AJAX request
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'test_hashtag_analysis_apis',
                nonce: config.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayTestResults(response.data);
                } else {
                    showError('Test failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showError('Connection error: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).text('Test API Connections');
            }
        });
    }

    function displayTestResults(data) {
        const $results = $('#api-test-results');
        const results = data.results || {};
        const details = data.details || {};
        const hasAnyConnection = data.has_any_connection || false;
        
        let html = '<div class="rwp-test-results">';
        
        // TikTok API Test - handle sandbox detection
        let tiktokClass = 'error';
        let tiktokStatus = '✗ Not Connected';
        
        if (results.tiktok) {
            if (details.tiktok && details.tiktok.includes('Sandbox')) {
                tiktokClass = 'sandbox';
                tiktokStatus = '⚠ Sandbox Mode';
            } else {
                tiktokClass = 'success';
                tiktokStatus = '✓ Connected';
            }
        }
        
        html += '<div class="test-result ' + tiktokClass + '">';
        html += '<h4>TikTok API</h4>';
        html += '<span class="status">' + tiktokStatus + '</span>';
        if (details.tiktok) {
            html += '<p class="test-detail">' + details.tiktok + '</p>';
        }
        html += '</div>';
        
        // Aggregators Test
        html += '<div class="test-result ' + (results.aggregators ? 'success' : 'error') + '">';
        html += '<h4>Aggregator Services</h4>';
        html += '<span class="status">' + (results.aggregators ? '✓ Connected' : '✗ Not Connected') + '</span>';
        if (details.aggregators) {
            html += '<p class="test-detail">' + details.aggregators + '</p>';
        }
        html += '</div>';
        
        html += '</div>';
        
        // Overall status with more specific messaging
        let noticeClass, message;
        
        if (hasAnyConnection) {
            noticeClass = 'notice-success';
            message = 'At least one API service is connected and working.';
        } else {
            noticeClass = 'notice-warning';
            message = 'No API connections configured. The plugin will use demo mode with mock data.';
        }
        
        // Add configuration reminder if no connections
        if (!hasAnyConnection) {
            message += ' Configure at least one API service above to enable real data.';
        }
        
        html = '<div class="notice ' + noticeClass + '"><p>' + message + '</p></div>' + html;
        
        $results.html(html);
    }

    function showError(message) {
        const $results = $('#api-test-results');
        $results.show().html('<div class="notice notice-error"><p>' + message + '</p></div>');
    }

    function saveFormData() {
        const formData = {};
        $('form input, form select').each(function() {
            const $field = $(this);
            if ($field.attr('type') !== 'password') { // Don't save passwords
                formData[$field.attr('name')] = $field.val();
            }
        });
        
        localStorage.setItem('rwp_hashtag_analysis_form_data', JSON.stringify(formData));
    }

    function loadFormData() {
        try {
            const savedData = localStorage.getItem('rwp_hashtag_analysis_form_data');
            if (savedData) {
                const formData = JSON.parse(savedData);
                Object.keys(formData).forEach(function(fieldName) {
                    const $field = $('input[name="' + fieldName + '"], select[name="' + fieldName + '"]');
                    if ($field.length && $field.attr('type') !== 'password') {
                        $field.val(formData[fieldName]);
                    }
                });
            }
        } catch (e) {
            console.warn('Could not load saved form data:', e);
        }
    }

    function toggleCredentialFields() {
        // Show/hide credential field values (password fields)
        $('.regular-text[type="password"]').each(function() {
            const $field = $(this);
            const $container = $field.closest('td');
            
            // Add toggle button
            if (!$container.find('.toggle-password').length) {
                const $toggleBtn = $('<button type="button" class="button button-small toggle-password">Show</button>');
                $toggleBtn.insertAfter($field);
                
                $toggleBtn.on('click', function() {
                    const isPassword = $field.attr('type') === 'password';
                    $field.attr('type', isPassword ? 'text' : 'password');
                    $(this).text(isPassword ? 'Hide' : 'Show');
                });
            }
        });
    }

    function addFieldValidation() {
        // Validate numeric fields
        $('input[type="number"]').on('input', function() {
            const $field = $(this);
            const min = parseInt($field.attr('min'));
            const max = parseInt($field.attr('max'));
            const value = parseInt($field.val());
            
            if (isNaN(value)) return;
            
            if (value < min) {
                $field.val(min);
                showValidationMessage($field, 'Minimum value is ' + min);
            } else if (value > max) {
                $field.val(max);
                showValidationMessage($field, 'Maximum value is ' + max);
            } else {
                clearValidationMessage($field);
            }
        });
        
        // Validate required fields on form submit
        $('form').on('submit', function() {
            let isValid = true;
            
            // Check that at least one API is configured
            const hasTikTok = $('#rwp_tiktok_app_id').val() && $('#rwp_tiktok_access_token').val();
            const hasApify = $('#rwp_apify_api_token').val();
            const hasData365 = $('#rwp_data365_api_key').val();
            
            if (!hasTikTok && !hasApify && !hasData365) {
                alert('Please configure at least one API service (TikTok or an aggregator service).');
                isValid = false;
            }
            
            return isValid;
        });
    }

    function showValidationMessage($field, message) {
        const $container = $field.closest('td');
        let $message = $container.find('.validation-message');
        
        if (!$message.length) {
            $message = $('<div class="validation-message notice notice-warning inline"></div>');
            $container.append($message);
        }
        
        $message.html('<p>' + message + '</p>').show();
        
        setTimeout(function() {
            clearValidationMessage($field);
        }, 3000);
    }

    function clearValidationMessage($field) {
        const $container = $field.closest('td');
        $container.find('.validation-message').remove();
    }

})(jQuery);