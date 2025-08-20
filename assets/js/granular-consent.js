/**
 * Granular Consent Management JavaScript
 * 
 * Handles the interactive granular consent form for GDPR compliance.
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initGranularConsent();
    });

    /**
     * Initialize granular consent functionality
     */
    function initGranularConsent() {
        // Handle save preferences button
        $('#save-consent-preferences').on('click', handleSavePreferences);
        
        // Handle accept all button
        $('#accept-all-consent').on('click', handleAcceptAll);
        
        // Handle reject all button  
        $('#reject-all-consent').on('click', handleRejectAll);
        
        // Load current consent status
        loadConsentStatus();
    }

    /**
     * Handle save preferences click
     */
    function handleSavePreferences() {
        const consentData = getConsentSelections();
        saveConsentPreferences(consentData);
    }

    /**
     * Handle accept all click
     */
    function handleAcceptAll() {
        $('input[name^="rwp_consent["]').prop('checked', true);
        const consentData = getConsentSelections();
        saveConsentPreferences(consentData);
    }

    /**
     * Handle reject all click
     */
    function handleRejectAll() {
        $('input[name^="rwp_consent["]').prop('checked', false);
        const consentData = getConsentSelections();
        saveConsentPreferences(consentData);
    }

    /**
     * Get current consent selections from form
     */
    function getConsentSelections() {
        const selections = {};
        $('input[name^="rwp_consent["]').each(function() {
            const name = $(this).attr('name');
            const category = name.match(/rwp_consent\[(.*?)\]/)[1];
            selections[category] = $(this).is(':checked');
        });
        return selections;
    }

    /**
     * Save consent preferences via API
     */
    function saveConsentPreferences(consentData) {
        if (!window.rwpGranularConsent) {
            console.error('RWP Granular Consent: Configuration not found');
            return;
        }

        // Show loading state
        const $button = $('#save-consent-preferences');
        const originalText = $button.text();
        $button.text(rwpGranularConsent.strings.processing || 'Processing...').prop('disabled', true);

        // Make API request
        wp.apiFetch({
            path: 'rwp-creator-suite/v1/granular-consent',
            method: 'POST',
            data: {
                consent_categories: consentData
            }
        }).then(function(response) {
            if (response.success) {
                showNotification(response.message || rwpGranularConsent.strings.success, 'success');
                
                // Update UI to reflect saved state
                updateConsentUI(consentData);
            } else {
                showNotification(response.message || rwpGranularConsent.strings.error, 'error');
            }
        }).catch(function(error) {
            console.error('Consent save error:', error);
            showNotification(rwpGranularConsent.strings.error, 'error');
        }).finally(function() {
            // Restore button state
            $button.text(originalText).prop('disabled', false);
        });
    }

    /**
     * Load current consent status
     */
    function loadConsentStatus() {
        if (!window.rwpGranularConsent) {
            return;
        }

        wp.apiFetch({
            path: 'rwp-creator-suite/v1/granular-consent',
            method: 'GET'
        }).then(function(response) {
            if (response.success && response.consents) {
                // Update checkboxes based on current consents
                for (const [category, consented] of Object.entries(response.consents)) {
                    $(`input[name="rwp_consent[${category}]"]`).prop('checked', consented);
                }
            }
        }).catch(function(error) {
            console.error('Failed to load consent status:', error);
        });
    }

    /**
     * Update consent UI after saving
     */
    function updateConsentUI(consentData) {
        // Add visual feedback for saved state
        $('.consent-category').each(function() {
            const $category = $(this);
            const $checkbox = $category.find('input[type="checkbox"]');
            const category = $checkbox.data('category');
            
            if (consentData[category]) {
                $category.addClass('consent-granted');
            } else {
                $category.removeClass('consent-granted');
            }
        });
    }

    /**
     * Show notification message
     */
    function showNotification(message, type) {
        // Create notification element
        const $notification = $(`
            <div class="rwp-consent-notification ${type}">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss</span>
                </button>
            </div>
        `);

        // Add to page
        $('.rwp-gdpr-consent-form').prepend($notification);

        // Handle dismiss
        $notification.find('.notice-dismiss').on('click', function() {
            $notification.fadeOut();
        });

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notification.fadeOut();
        }, 5000);
    }

})(jQuery);