/**
 * Consent Manager
 * 
 * Handles the analytics consent banner and user preference management.
 */

class RWPConsentManager {
    constructor() {
        this.apiUrl = rwpConsentManager.apiUrl;
        this.nonce = rwpConsentManager.nonce;
        this.strings = rwpConsentManager.strings;
        this.banner = null;
        this.hasShownBanner = false;
        
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.checkAndShowBanner());
        } else {
            this.checkAndShowBanner();
        }
    }

    async checkAndShowBanner() {
        try {
            const response = await fetch(`${this.apiUrl}consent`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.nonce
                }
            });

            const data = await response.json();
            
            if (data.show_banner && !this.hasShownBanner) {
                this.showBanner();
                this.hasShownBanner = true;
                
                // Mark banner as shown
                this.markBannerShown();
            }
        } catch (error) {
            console.error('Error checking consent status:', error);
        }
    }

    showBanner() {
        this.banner = document.getElementById('rwp-consent-banner');
        
        if (!this.banner) {
            console.warn('Consent banner element not found');
            return;
        }

        // Set text content
        const title = this.banner.querySelector('.rwp-consent-banner-title');
        const message = this.banner.querySelector('.rwp-consent-banner-message');
        const learnMoreLink = this.banner.querySelector('#rwp-consent-learn-more');

        if (title) title.textContent = this.strings.title;
        if (message) message.textContent = this.strings.message;
        if (learnMoreLink) {
            learnMoreLink.textContent = this.strings.learnMoreButton;
            learnMoreLink.href = this.strings.learnMoreUrl;
        }

        // Bind event listeners
        this.bindBannerEvents();

        // Show banner with animation
        this.banner.style.display = 'block';
        setTimeout(() => {
            this.banner.classList.add('rwp-consent-banner-visible');
        }, 100);
    }

    bindBannerEvents() {
        const acceptButton = document.getElementById('rwp-consent-accept');
        const declineButton = document.getElementById('rwp-consent-decline');
        const learnMoreLink = document.getElementById('rwp-consent-learn-more');

        if (acceptButton) {
            acceptButton.addEventListener('click', () => this.setConsent(true));
        }

        if (declineButton) {
            declineButton.addEventListener('click', () => this.setConsent(false));
        }

        if (learnMoreLink) {
            learnMoreLink.addEventListener('click', (e) => {
                e.preventDefault();
                this.openPrivacyInfo();
            });
        }

        // Close banner on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.banner && this.banner.classList.contains('rwp-consent-banner-visible')) {
                this.setConsent(false);
            }
        });
    }

    async setConsent(consent) {
        try {
            const response = await fetch(`${this.apiUrl}consent`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    consent: consent
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.hideBanner();
                
                // Show confirmation message
                if (data.message) {
                    this.showConfirmationMessage(data.message, consent);
                }
                
                // Trigger custom event for other scripts
                document.dispatchEvent(new CustomEvent('rwpConsentChanged', {
                    detail: { consented: consent }
                }));
            }
        } catch (error) {
            console.error('Error setting consent:', error);
            this.showErrorMessage('Failed to save your preference. Please try again.');
        }
    }

    hideBanner() {
        if (!this.banner) return;

        this.banner.classList.remove('rwp-consent-banner-visible');
        
        setTimeout(() => {
            this.banner.style.display = 'none';
        }, 300);
    }

    async markBannerShown() {
        try {
            await fetch(`${this.apiUrl}consent/banner-shown`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.nonce
                }
            });
        } catch (error) {
            console.error('Error marking banner as shown:', error);
        }
    }

    showConfirmationMessage(message, consent) {
        const confirmationDiv = document.createElement('div');
        confirmationDiv.className = `rwp-consent-confirmation ${consent ? 'rwp-consent-positive' : 'rwp-consent-neutral'}`;
        confirmationDiv.innerHTML = `
            <div class="rwp-consent-confirmation-content">
                <span class="rwp-consent-confirmation-icon">${consent ? '✓' : 'ℹ'}</span>
                <span class="rwp-consent-confirmation-text">${message}</span>
                <button class="rwp-consent-confirmation-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        // Insert at top of page
        document.body.insertBefore(confirmationDiv, document.body.firstChild);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (confirmationDiv.parentNode) {
                confirmationDiv.remove();
            }
        }, 5000);
    }

    showErrorMessage(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'rwp-consent-error';
        errorDiv.innerHTML = `
            <div class="rwp-consent-error-content">
                <span class="rwp-consent-error-icon">⚠</span>
                <span class="rwp-consent-error-text">${message}</span>
                <button class="rwp-consent-error-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        document.body.insertBefore(errorDiv, document.body.firstChild);

        // Auto-remove after 8 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 8000);
    }

    openPrivacyInfo() {
        // Open privacy policy in new tab
        if (this.strings.learnMoreUrl) {
            window.open(this.strings.learnMoreUrl, '_blank', 'noopener,noreferrer');
        }
    }

    // Public API for manual consent management
    async getConsentStatus() {
        try {
            const response = await fetch(`${this.apiUrl}consent`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.nonce
                }
            });

            return await response.json();
        } catch (error) {
            console.error('Error getting consent status:', error);
            return null;
        }
    }

    async updateConsent(consent) {
        return await this.setConsent(consent);
    }

    // Show consent banner manually (for settings pages)
    showConsentSettings() {
        const settingsContainer = document.createElement('div');
        settingsContainer.className = 'rwp-consent-settings';
        settingsContainer.innerHTML = `
            <h4>${this.strings.title}</h4>
            <p>${this.strings.message}</p>
            <div class="rwp-consent-settings-buttons">
                <button id="rwp-consent-settings-accept" class="button button-primary">
                    ${this.strings.acceptButton}
                </button>
                <button id="rwp-consent-settings-decline" class="button">
                    ${this.strings.declineButton}
                </button>
            </div>
            <p>
                <a href="${this.strings.learnMoreUrl}" target="_blank">
                    ${this.strings.learnMoreButton}
                </a>
            </p>
        `;

        // Bind events for settings version
        const acceptBtn = settingsContainer.querySelector('#rwp-consent-settings-accept');
        const declineBtn = settingsContainer.querySelector('#rwp-consent-settings-decline');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', () => this.setConsent(true));
        }

        if (declineBtn) {
            declineBtn.addEventListener('click', () => this.setConsent(false));
        }

        return settingsContainer;
    }
}

// Initialize when script loads
if (typeof rwpConsentManager !== 'undefined') {
    window.rwpConsentManagerInstance = new RWPConsentManager();
    
    // Make public methods available globally
    window.rwpConsent = {
        getStatus: () => window.rwpConsentManagerInstance.getConsentStatus(),
        setConsent: (consent) => window.rwpConsentManagerInstance.updateConsent(consent),
        showSettings: () => window.rwpConsentManagerInstance.showConsentSettings()
    };
}