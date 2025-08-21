/**
 * Account Manager App
 *
 * Handles the subscriber account interface with consent management
 */

class RWPAccountManager {
	constructor() {
		this.data = window.rwpAccountManager || {};
		this.stateManager = window.rwpStateManager;
		this.containers = [];
		this.currentView = 'dashboard';

		// Debug logging to help troubleshoot
		if ( typeof console !== 'undefined' && console.log ) {
			console.log(
				'RWPAccountManager initialized with data:',
				this.data
			);
		}

		// Only exit early if we have explicit data showing user is not logged in
		// The PHP template handles the guest view logic, so we should only exit if we have clear data
		if (
			this.data.hasOwnProperty( 'isLoggedIn' ) &&
			this.data.isLoggedIn === false
		) {
			console.log( 'Exiting early: user is not logged in' );
			return;
		}

		this.init();
	}

	init() {
		const initializeApp = () => {
			this.findContainers();
			this.setupEventListeners();
			this.render();
		};

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', initializeApp );
		} else {
			initializeApp();
		}
	}

	findContainers() {
		this.containers = document.querySelectorAll(
			'[id^="rwp-account-manager-"]'
		);
	}

	setupEventListeners() {
		// Handle tab navigation
		document.addEventListener( 'click', ( e ) => {
			if ( e.target.classList.contains( 'rwp-account-tab' ) ) {
				e.preventDefault();
				const view = e.target.dataset.view;
				// Find which container this tab belongs to
				const containerElement = e.target.closest(
					'[id^="rwp-account-manager-"]'
				);
				this.switchView( view, containerElement );
			}
		} );

		// Handle consent form submission
		document.addEventListener( 'submit', ( e ) => {
			if ( e.target.classList.contains( 'rwp-consent-form' ) ) {
				e.preventDefault();
				this.handleConsentUpdate( e.target );
			}

			if ( e.target.classList.contains( 'rwp-profile-form' ) ) {
				e.preventDefault();
				this.handleProfileUpdate( e.target );
			}
		} );
	}

	render() {
		if ( typeof console !== 'undefined' && console.log ) {
			console.log(
				'Rendering account manager with containers:',
				this.containers.length
			);
		}

		this.containers.forEach( ( container ) => {
			const config = JSON.parse( container.dataset.config || '{}' );
			this.currentView = config.viewType || 'dashboard';

			// Use data from localized script or fallback to defaults
			const isLoggedIn =
				this.data.isLoggedIn !== undefined
					? this.data.isLoggedIn
					: false;

			if ( ! isLoggedIn ) {
				this.renderLoginPrompt( container );
			} else {
				this.renderAccountInterface( container, config );
			}
		} );
	}

	renderLoginPrompt( container ) {
		const loginUrl =
			window.location.origin +
			'/wp-login.php?redirect_to=' +
			encodeURIComponent( window.location.href );
		const registerUrl =
			window.location.origin + '/wp-login.php?action=register';

		const strings = this.data.strings || {};
		container.innerHTML = `
            <div class="rwp-account-manager-container">
                <div class="rwp-login-prompt">
                    <h3>${
						strings.loginRequired || 'Account Access Required'
					}</h3>
                    <p>Please log in or register to access your account settings and manage your preferences.</p>
                    <div class="rwp-auth-buttons">
                        <button type="button" class="primary" onclick="window.location.href='${ loginUrl }'">
                            Log In
                        </button>
                        <button type="button" class="secondary" onclick="window.location.href='${ registerUrl }'">
                            Register
                        </button>
                    </div>
                </div>
            </div>
        `;
	}

	renderAccountInterface( container, config ) {
		const showConsent = config.showConsentSettings !== false;
		const strings = this.data.strings || {};

		container.innerHTML = `
            <div class="rwp-account-manager-container">
                <div class="rwp-account-header">
                    <nav class="rwp-account-tabs">
                        <button class="rwp-account-tab ${
							this.currentView === 'dashboard' ? 'active' : ''
						}" data-view="dashboard">
                            ${ strings.dashboard || 'Dashboard' }
                        </button>
                        ${
							showConsent
								? `
                            <button class="rwp-account-tab ${
								this.currentView === 'consent' ? 'active' : ''
							}" data-view="consent">
                                ${
									strings.consentSettings ||
									'Consent Settings'
								}
                            </button>
                        `
								: ''
						}
                        <button class="rwp-account-tab ${
							this.currentView === 'profile' ? 'active' : ''
						}" data-view="profile">
                            ${ strings.profileSettings || 'Profile Settings' }
                        </button>
                    </nav>
                </div>
                <div class="rwp-account-content">
                    ${ this.renderCurrentView( config ) }
                </div>
            </div>
        `;
	}

	renderCurrentView( config ) {
		switch ( this.currentView ) {
			case 'dashboard':
				return this.renderDashboard();
			case 'consent':
				return config.showConsentSettings !== false
					? this.renderConsentSettings()
					: this.renderDashboard();
			case 'profile':
				return this.renderProfileSettings();
			default:
				return this.renderDashboard();
		}
	}

	renderDashboard() {
		const consentStatus = this.data.currentConsent;
		const consentStatusText =
			consentStatus === true
				? this.data.strings.consentEnabled
				: consentStatus === false
				? this.data.strings.consentDisabled
				: 'Not set';

		return `
            <div class="rwp-dashboard-view">
                <h2>${ this.data.strings.dashboard }</h2>
                <div class="rwp-dashboard-cards">
                    <div class="rwp-dashboard-card">
                        <h3>Account Overview</h3>
                        <div class="rwp-dashboard-stat">
                            <label>User ID:</label>
                            <span>${ this.data.currentUserId }</span>
                        </div>
                        <div class="rwp-dashboard-stat">
                            <label>Advanced Analytics:</label>
                            <span class="rwp-consent-status ${
								consentStatus ? 'enabled' : 'disabled'
							}">${ consentStatusText }</span>
                        </div>
                    </div>
                    <div class="rwp-dashboard-card">
                        <h3>Quick Actions</h3>
                        <div class="rwp-quick-actions">
                            <button class="rwp-account-tab secondary" data-view="consent">
                                Manage Consent
                            </button>
                            <button class="rwp-account-tab secondary" data-view="profile">
                                Edit Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
	}

	renderConsentSettings() {
		const currentConsent = this.data.currentConsent;

		return `
            <div class="rwp-consent-view">
                <h2>${ this.data.strings.consentSettings }</h2>
                <div class="rwp-consent-card">
                    <h3>${ this.data.strings.consentTitle }</h3>
                    <p>${ this.data.strings.consentDescription }</p>
                    
                    <form class="rwp-consent-form">
                        <div class="rwp-consent-option">
                            <label class="rwp-toggle-label">
                                <input type="checkbox" 
                                       name="advanced_features_consent" 
                                       value="1" 
                                       ${
											currentConsent === true ||
											currentConsent === 1 ||
											currentConsent === '1'
												? 'checked'
												: ''
										}>
                                <span class="rwp-toggle-slider"></span>
                                <span class="rwp-toggle-text">
                                    Enable advanced analytics features for personalized insights
                                </span>
                            </label>
                        </div>
                        
                        <div class="rwp-consent-description">
                            <h4>What this enables:</h4>
                            <ul>
                                <li>Detailed content performance analytics</li>
                                <li>Personalized recommendations</li>
                                <li>Advanced reporting features</li>
                                <li>Trend analysis and insights</li>
                            </ul>
                        </div>
                        
                        <div class="rwp-consent-actions">
                            <button type="submit" class="primary">
                                ${ this.data.strings.updateConsent }
                            </button>
                        </div>
                        
                        <div class="rwp-consent-status hidden"></div>
                    </form>
                </div>
            </div>
        `;
	}

	renderProfileSettings() {
		return `
            <div class="rwp-profile-view">
                <h2>${ this.data.strings.profileSettings }</h2>
                <div class="rwp-profile-card">
                    <div class="rwp-profile-loading">
                        <div class="rwp-loading-spinner"></div>
                        <p>${ this.data.strings.loading }</p>
                    </div>
                    <form class="rwp-profile-form hidden">
                        <div class="rwp-form-group">
                            <label for="profile-display-name">Display Name</label>
                            <input type="text" id="profile-display-name" name="display_name" required>
                        </div>
                        <div class="rwp-form-group">
                            <label for="profile-email">Email Address</label>
                            <input type="email" id="profile-email" name="user_email" required>
                        </div>
                        <div class="rwp-profile-actions">
                            <button type="submit" class="primary">
                                ${
									this.data.strings.updateProfile ||
									'Update Profile'
								}
                            </button>
                        </div>
                        <div class="rwp-profile-status hidden"></div>
                    </form>
                </div>
            </div>
        `;
	}

	switchView( view, targetContainer = null ) {
		this.currentView = view;

		// If no specific container is provided, find the first one (legacy behavior)
		if ( ! targetContainer ) {
			targetContainer = document.querySelector(
				'[id^="rwp-account-manager-"]'
			);
		}

		if ( ! targetContainer ) {
			console.warn( 'No target container found for switchView' );
			return;
		}

		// Update active tab only within this container
		targetContainer
			.querySelectorAll( '.rwp-account-tab' )
			.forEach( ( tab ) => {
				tab.classList.toggle( 'active', tab.dataset.view === view );
			} );

		// Update content only within this container
		const contentContainer = targetContainer.querySelector(
			'.rwp-account-content'
		);
		if ( contentContainer ) {
			// Get the config from this specific container
			const config = JSON.parse( targetContainer.dataset.config || '{}' );
			contentContainer.innerHTML = this.renderCurrentView( config );

			// Load profile data if switching to profile view
			if ( view === 'profile' ) {
				this.loadProfileData( targetContainer );
			}
		}
	}

	async handleConsentUpdate( form ) {
		const formData = new FormData( form );
		const consent = formData.get( 'advanced_features_consent' ) === '1';

		const statusDiv = form.querySelector( '.rwp-consent-status' );
		const submitButton = form.querySelector( 'button[type="submit"]' );

		// Show loading state
		statusDiv.className = 'rwp-consent-status loading';
		statusDiv.textContent = this.data.strings.saving;
		submitButton.disabled = true;

		try {
			const response = await fetch(
				`${ this.data.restUrl }consent/update`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this.data.nonce,
					},
					body: JSON.stringify( {
						consent,
					} ),
				}
			);

			const result = await response.json();

			if ( result.success ) {
				// Update stored consent value
				this.data.currentConsent = consent;

				// Show success message
				statusDiv.className = 'rwp-consent-status success';
				statusDiv.textContent = this.data.strings.saved;

				// Hide status after 3 seconds
				setTimeout( () => {
					statusDiv.className = 'rwp-consent-status hidden';
				}, 3000 );

				// Update dashboard if it's visible
				if ( document.querySelector( '.rwp-dashboard-view' ) ) {
					const blockContainer = form.closest(
						'[id^="rwp-account-manager-"]'
					);
					if ( blockContainer ) {
						this.switchView( 'dashboard', blockContainer );
					}
				}
			} else {
				throw new Error( result.message || 'Update failed' );
			}
		} catch ( error ) {
			console.error( 'Consent update error:', error );
			statusDiv.className = 'rwp-consent-status error';
			statusDiv.textContent = this.data.strings.error;
		} finally {
			submitButton.disabled = false;
		}
	}

	async loadProfileData( containerElement ) {
		const loadingDiv = containerElement.querySelector(
			'.rwp-profile-loading'
		);
		const formDiv = containerElement.querySelector( '.rwp-profile-form' );

		if ( ! loadingDiv || ! formDiv ) {
			console.warn( 'Profile loading elements not found' );
			return;
		}

		try {
			const response = await fetch(
				`${ this.data.restUrl }account/profile`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this.data.nonce,
					},
					credentials: 'same-origin',
				}
			);

			const result = await response.json();

			if ( result.success && result.data ) {
				// Populate form fields
				const displayNameInput = formDiv.querySelector(
					'#profile-display-name'
				);
				const emailInput = formDiv.querySelector( '#profile-email' );

				if ( displayNameInput && emailInput ) {
					displayNameInput.value = result.data.display_name || '';
					emailInput.value = result.data.user_email || '';
				}

				// Show form, hide loading
				loadingDiv.style.display = 'none';
				formDiv.classList.remove( 'hidden' );
			} else {
				throw new Error(
					result.message || 'Failed to load profile data'
				);
			}
		} catch ( error ) {
			console.error( 'Profile data loading error:', error );
			loadingDiv.innerHTML = `
                <div class="rwp-error">
                    <p>Failed to load profile data. Please try again.</p>
                    <button type="button" onclick="window.location.reload()">Reload</button>
                </div>
            `;
		}
	}

	async handleProfileUpdate( form ) {
		const formData = new FormData( form );
		const displayName = formData.get( 'display_name' );
		const userEmail = formData.get( 'user_email' );

		const statusDiv = form.querySelector( '.rwp-profile-status' );
		const submitButton = form.querySelector( 'button[type="submit"]' );

		// Show loading state
		statusDiv.className = 'rwp-profile-status loading';
		statusDiv.textContent = this.data.strings.saving;
		submitButton.disabled = true;

		try {
			const response = await fetch(
				`${ this.data.restUrl }account/profile`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this.data.nonce,
					},
					body: JSON.stringify( {
						display_name: displayName,
						user_email: userEmail,
					} ),
				}
			);

			const result = await response.json();

			if ( result.success ) {
				// Show success message
				statusDiv.className = 'rwp-profile-status success';
				statusDiv.textContent = this.data.strings.saved;

				// Hide status after 3 seconds
				setTimeout( () => {
					statusDiv.className = 'rwp-profile-status hidden';
				}, 3000 );
			} else {
				throw new Error( result.message || 'Update failed' );
			}
		} catch ( error ) {
			console.error( 'Profile update error:', error );
			statusDiv.className = 'rwp-profile-status error';
			statusDiv.textContent = error.message || this.data.strings.error;
		} finally {
			submitButton.disabled = false;
		}
	}
}

// Initialize when DOM is ready
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		new RWPAccountManager();
	} );
} else {
	new RWPAccountManager();
}
