/**
 * Instagram Follower Analyzer App
 *
 * Client-side application for analyzing Instagram follower relationships.
 */

class InstagramAnalyzer {
	constructor( containerId, config = {} ) {
		this.container = document.getElementById( containerId );
		if ( ! this.container ) {
			console.error(
				'Instagram Analyzer container not found:',
				containerId
			);
			return;
		}

		this.config = {
			isLoggedIn: config.isLoggedIn || false,
			currentUserId: config.currentUserId || 0,
			ajaxUrl: config.ajaxUrl || '',
			nonce: config.nonce || '',
			strings: config.strings || {},
			...config,
		};

		this.state = {
			isProcessing: false,
			uploadProgress: 0,
			analysisData: null,
			whitelist: [],
		};

		// Initialize state manager
		this.stateManager = new StateManager( {
			storagePrefix: 'rwp_instagram_analyzer_',
			maxDataAge: 24 * 60 * 60 * 1000, // 24 hours
		} );

		this.init();
	}

	async init() {
		this.createUploadInterface();
		this.bindEvents();
		this.restoreFormState();
		await this.loadStoredData();
		await this.loadServerData();
	}

	createUploadInterface() {
		const uploadInterface = `
            <div class="blk-upload-container">
                <div class="blk-upload-zone" id="upload-zone">
                    <div class="blk-upload-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10,9 9,9 8,9"></polyline>
                        </svg>
                    </div>
                    <h3 class="blk-upload-title">${
						this.config.strings.uploadPrompt ||
						'Upload Instagram Data'
					}</h3>
                    <p class="blk-upload-description">
                        Drag and drop your Instagram data export ZIP file here, or click to browse.
                    </p>
                    <button type="button" class="blk-upload-button" id="file-browse-btn">
                        Browse Files
                    </button>
                    <input type="file" id="file-input" accept=".zip" style="display: none;" />
                    <div class="blk-upload-progress" id="upload-progress" style="display: none;">
                        <div class="blk-progress-bar">
                            <div class="blk-progress-fill" id="progress-fill"></div>
                        </div>
                        <div class="blk-progress-text" id="progress-text">0%</div>
                    </div>
                </div>
                <div class="blk-error-message" id="error-message" style="display: none;"></div>
            </div>
            <div class="blk-results-container" id="results-container" style="display: none;">
                <!-- Results will be populated here -->
            </div>
        `;

		this.container.innerHTML = uploadInterface;
	}

	bindEvents() {
		const uploadZone = this.container.querySelector( '#upload-zone' );
		const fileInput = this.container.querySelector( '#file-input' );
		const browseBtnContainer =
			this.container.querySelector( '#file-browse-btn' );

		// Drag and drop events
		uploadZone.addEventListener( 'dragover', ( e ) => {
			e.preventDefault();
			uploadZone.classList.add( 'blk-upload-zone--dragover' );
		} );

		uploadZone.addEventListener( 'dragleave', ( e ) => {
			e.preventDefault();
			uploadZone.classList.remove( 'blk-upload-zone--dragover' );
		} );

		uploadZone.addEventListener( 'drop', ( e ) => {
			e.preventDefault();
			uploadZone.classList.remove( 'blk-upload-zone--dragover' );

			const files = e.dataTransfer.files;
			if ( files.length > 0 ) {
				this.handleFileUpload( files[ 0 ] );
			}
		} );

		// Browse button click
		browseBtnContainer.addEventListener( 'click', () => {
			fileInput.click();
		} );

		// File input change
		fileInput.addEventListener( 'change', ( e ) => {
			if ( e.target.files.length > 0 ) {
				this.handleFileUpload( e.target.files[ 0 ] );
			}
		} );
	}

	async handleFileUpload( file ) {
		if ( ! this.validateFile( file ) ) {
			return;
		}

		this.state.isProcessing = true;
		this.showProgress( 0 );
		this.hideError();
		this.saveFormState();

		try {
			// Update progress
			this.showProgress( 25 );

			// Process the ZIP file
			const analysisData = await this.processZipFile( file );

			this.showProgress( 100 );
			this.state.analysisData = analysisData;

			// Store data locally
			this.saveDataLocally( analysisData );

			// Display results
			await this.displayResults( analysisData );
		} catch ( error ) {
			console.error( 'File processing error:', error );
			this.showError(
				"Failed to process the uploaded file. Please ensure it's a valid Instagram data export."
			);
		} finally {
			this.state.isProcessing = false;
			this.saveFormState();
			this.stateManager.clearFormState(); // Clear since processing is complete
			setTimeout( () => this.hideProgress(), 1000 );
		}
	}

	validateFile( file ) {
		// Check file type
		if ( ! file.name.toLowerCase().endsWith( '.zip' ) ) {
			this.showError(
				'Please upload a ZIP file containing your Instagram data export.'
			);
			return false;
		}

		// Check file size (max 100MB)
		const maxSize = 100 * 1024 * 1024;
		if ( file.size > maxSize ) {
			this.showError(
				'File size is too large. Please ensure your ZIP file is under 100MB.'
			);
			return false;
		}

		return true;
	}

	async processZipFile( file ) {
		return new Promise( async ( resolve, reject ) => {
			try {
				// Check if JSZip is available
				if ( typeof JSZip === 'undefined' ) {
					throw new Error( 'JSZip library not loaded' );
				}

				this.showProgress( 25 );

				// Load ZIP file
				const zip = new JSZip();
				const zipContent = await zip.loadAsync( file );

				this.showProgress( 50 );

				// Extract followers and following data
				const followersData =
					await this.extractFollowersData( zipContent );
				const followingData =
					await this.extractFollowingData( zipContent );

				this.showProgress( 75 );

				// Analyze the data
				const analysisResult = this.analyzeFollowerData(
					followersData,
					followingData
				);

				this.showProgress( 100 );

				resolve( analysisResult );
			} catch ( error ) {
				console.error( 'ZIP processing error:', error );
				reject( error );
			}
		} );
	}

	async extractFollowersData( zipContent ) {
		const followers = [];
		const followersFiles = [];

		// Look for followers files (followers.html, followers_1.html, followers_2.html, etc.)
		zipContent.forEach( ( relativePath, zipEntry ) => {
			const filename = relativePath.toLowerCase();
			const basename = filename.split( '/' ).pop(); // Get just the filename without path

			if (
				filename.endsWith( '.html' ) &&
				( basename === 'followers.html' ||
					basename.startsWith( 'followers_' ) )
			) {
				followersFiles.push( zipEntry );
			}
		} );

		// Process each followers file
		for ( const file of followersFiles ) {
			try {
				const content = await file.async( 'text' );
				const fileFollowers = this.parseInstagramHTML(
					content,
					'followers'
				);
				followers.push( ...fileFollowers );
			} catch ( error ) {
				console.warn(
					'Error processing followers file:',
					file.name,
					error
				);
			}
		}

		return followers;
	}

	async extractFollowingData( zipContent ) {
		const following = [];
		const followingFiles = [];

		// Look for following files (following.html, following_1.html, following_2.html, etc.)
		zipContent.forEach( ( relativePath, zipEntry ) => {
			const filename = relativePath.toLowerCase();
			const basename = filename.split( '/' ).pop(); // Get just the filename without path

			if (
				filename.endsWith( '.html' ) &&
				( basename === 'following.html' ||
					basename.startsWith( 'following_' ) )
			) {
				followingFiles.push( zipEntry );
			}
		} );

		// Process each following file
		for ( const file of followingFiles ) {
			try {
				const content = await file.async( 'text' );
				const fileFollowing = this.parseInstagramHTML(
					content,
					'following'
				);
				following.push( ...fileFollowing );
			} catch ( error ) {
				console.warn(
					'Error processing following file:',
					file.name,
					error
				);
			}
		}

		return following;
	}

	parseInstagramHTML( htmlContent, type ) {
		const accounts = [];

		try {
			// Create a DOM parser
			const parser = new DOMParser();
			const doc = parser.parseFromString( htmlContent, 'text/html' );

			// Instagram export HTML structure varies, but generally contains lists with account info
			// Look for common patterns in Instagram data exports
			const accountElements = this.findAccountElements( doc, type );

			accountElements.forEach( ( element ) => {
				const account = this.extractAccountInfo( element );
				if ( account ) {
					accounts.push( account );
				}
			} );
		} catch ( error ) {
			console.error( 'Error parsing Instagram HTML:', error );
		}

		return accounts;
	}

	findAccountElements( doc, type ) {
		// Try multiple selectors as Instagram export format can vary
		const selectors = [
			// Common patterns in Instagram exports
			'div[role="cell"]',
			'.x1i10hfl',
			'a[href*="instagram.com"]',
			'div:has(a[href*="instagram.com"])',
			// Fallback to any div containing links
			'div a[href]',
		];

		let elements = [];

		for ( const selector of selectors ) {
			try {
				const found = doc.querySelectorAll( selector );
				if ( found.length > 0 ) {
					elements = Array.from( found );
					break;
				}
			} catch ( error ) {
				// Continue to next selector if this one fails
				continue;
			}
		}

		// If no structured elements found, try parsing text content
		if ( elements.length === 0 ) {
			elements = this.parseTextContent( doc );
		}

		return elements;
	}

	extractAccountInfo( element ) {
		let username = '';
		let profileUrl = '';
		let timestamp = '';

		try {
			// Try to find username from link
			const link =
				element.querySelector( 'a[href*="instagram.com"]' ) ||
				( element.href && element.href.includes( 'instagram.com' )
					? element
					: null );

			if ( link ) {
				profileUrl = link.href;
				// Extract username from URL
				const urlMatch = profileUrl.match( /instagram\.com\/([^/?]+)/ );
				if ( urlMatch ) {
					username = urlMatch[ 1 ];
				}
			}

			// Try to find username from text content if not found in URL
			if ( ! username ) {
				const textContent =
					element.textContent || element.innerText || '';
				const usernameMatch = textContent.match( /@?([a-zA-Z0-9_.]+)/ );
				if ( usernameMatch ) {
					username = usernameMatch[ 1 ];
					// Construct profile URL if not found
					if ( ! profileUrl ) {
						profileUrl = `https://instagram.com/${ username }`;
					}
				}
			}

			// Try to find timestamp
			const timeElement =
				element.querySelector( 'time' ) ||
				element.querySelector( '[datetime]' ) ||
				element.querySelector( '.timestamp' );

			if ( timeElement ) {
				timestamp =
					timeElement.getAttribute( 'datetime' ) ||
					timeElement.textContent ||
					timeElement.getAttribute( 'title' );
			}

			// Clean up username (remove @ if present)
			username = username.replace( /^@/, '' );

			// Only return if we have a valid username
			if ( username && username.length > 0 ) {
				return {
					username: this.sanitizeString( username ),
					profileUrl:
						profileUrl || `https://instagram.com/${ username }`,
					timestamp: timestamp || new Date().toISOString(),
				};
			}
		} catch ( error ) {
			console.warn( 'Error extracting account info:', error );
		}

		return null;
	}

	parseTextContent( doc ) {
		const elements = [];
		const textContent = doc.body.textContent || doc.body.innerText || '';

		// Look for Instagram username patterns in text
		const usernameRegex = /@?([a-zA-Z0-9_.]{1,30})/g;
		let match;

		while ( ( match = usernameRegex.exec( textContent ) ) !== null ) {
			const username = match[ 1 ];

			// Skip very short or very common words
			if (
				username.length < 3 ||
				/^(the|and|for|you|are|with|com|www)$/i.test( username )
			) {
				continue;
			}

			// Create a pseudo-element for consistent processing
			const pseudoElement = {
				textContent: `@${ username }`,
				querySelector: () => null,
			};

			elements.push( pseudoElement );
		}

		return elements;
	}

	sanitizeString( str ) {
		// Remove potentially dangerous characters and trim
		return str
			.replace( /[<>\"'&]/g, '' )
			.trim()
			.substring( 0, 50 );
	}

	analyzeFollowerData( followers, following ) {
		// Remove duplicates and create lookup maps
		const uniqueFollowers = this.removeDuplicates( followers );
		const uniqueFollowing = this.removeDuplicates( following );

		// Create a Set of follower usernames for fast lookup
		const followerSet = new Set(
			uniqueFollowers.map( ( account ) => account.username.toLowerCase() )
		);

		// Find accounts you're following that don't follow you back
		const notFollowingBack = uniqueFollowing.filter( ( account ) => {
			return ! followerSet.has( account.username.toLowerCase() );
		} );

		// Sort accounts by username for consistent display
		uniqueFollowers.sort( ( a, b ) =>
			a.username.localeCompare( b.username )
		);
		uniqueFollowing.sort( ( a, b ) =>
			a.username.localeCompare( b.username )
		);
		notFollowingBack.sort( ( a, b ) =>
			a.username.localeCompare( b.username )
		);

		return {
			followers: uniqueFollowers,
			following: uniqueFollowing,
			notFollowingBack: notFollowingBack,
			stats: {
				totalFollowers: uniqueFollowers.length,
				totalFollowing: uniqueFollowing.length,
				notFollowingBackCount: notFollowingBack.length,
				mutualCount: uniqueFollowing.length - notFollowingBack.length,
				followerToFollowingRatio:
					uniqueFollowers.length > 0
						? (
								uniqueFollowing.length / uniqueFollowers.length
						  ).toFixed( 2 )
						: 0,
			},
		};
	}

	removeDuplicates( accounts ) {
		const seen = new Set();
		const unique = [];

		for ( const account of accounts ) {
			const key = account.username.toLowerCase();
			if ( ! seen.has( key ) ) {
				seen.add( key );
				unique.push( account );
			}
		}

		return unique;
	}

	async displayResults( data ) {
		const resultsContainer =
			this.container.querySelector( '#results-container' );
		const uploadContainer = this.container.querySelector(
			'.blk-upload-container'
		);

		// Hide upload interface
		uploadContainer.style.display = 'none';

		// Ensure whitelist is up-to-date for logged-in users
		if ( this.config.isLoggedIn ) {
			await this.loadServerData();
		}

		// Show results
		resultsContainer.style.display = 'block';
		resultsContainer.innerHTML = this.createResultsHTML( data );

		// Bind events and restore preferences
		this.bindResultsEvents();
		this.restoreUserPreferences();
	}

	createResultsHTML( data ) {
		const stats = data.stats;

		return `
            <div class="blk-results-header">
                <h2 class="blk-results-title">Analysis Complete</h2>
                <div class="blk-stats-grid">
                    <div class="blk-stat-card blk-stat-card--primary">
                        <div class="blk-stat-number">${
							stats.notFollowingBackCount
						}</div>
                        <div class="blk-stat-label">Not Following Back</div>
                    </div>
                </div>
                <div class="blk-stats-secondary">
                    <div class="blk-stat-card blk-stat-card--small">
                        <div class="blk-stat-number">${
							stats.totalFollowers
						}</div>
                        <div class="blk-stat-label">Followers</div>
                    </div>
                    <div class="blk-stat-card blk-stat-card--small">
                        <div class="blk-stat-number">${
							stats.totalFollowing
						}</div>
                        <div class="blk-stat-label">Following</div>
                    </div>
                </div>
            </div>
            
            <div class="blk-results-body">
                ${
					this.config.isLoggedIn
						? this.createFullResults( data )
						: this.createTeaserResults( data )
				}
            </div>
            
            <div class="blk-results-footer">
                <button type="button" class="blk-button blk-button--secondary" id="reset-analyzer-btn">
                    Upload New File
                </button>
            </div>
        `;
	}

	createFullResults( data ) {
		// Sort accounts alphabetically with whitelisted accounts at the bottom
		const sortedAccounts = [ ...data.notFollowingBack ].sort( ( a, b ) => {
			const aWhitelisted = this.state.whitelist.includes( a.username );
			const bWhitelisted = this.state.whitelist.includes( b.username );

			// If whitelist status is different, non-whitelisted comes first
			if ( aWhitelisted !== bWhitelisted ) {
				return aWhitelisted ? 1 : -1;
			}

			// If same whitelist status, sort alphabetically
			return a.username.localeCompare( b.username );
		} );

		const nonMutualList = sortedAccounts
			.map( ( account ) => this.createAccountItem( account ) )
			.join( '' );

		return `
            <div class="blk-full-results">
                <div class="blk-section-header">
                    <h3>Accounts Not Following You Back (${
						data.stats.notFollowingBackCount
					})</h3>
                    <div class="blk-filter-controls">
                        <input 
                            type="text" 
                            id="account-search" 
                            placeholder="Search accounts..." 
                            class="blk-search-input"
                        />
                    </div>
                </div>
                
                ${
					data.stats.notFollowingBackCount > 0
						? `
                    <div class="blk-accounts-list" id="non-mutual-list">
                        ${ nonMutualList }
                    </div>
                    ${
						data.stats.notFollowingBackCount > 20
							? `
                        <div class="blk-pagination">
                            <button class="blk-button blk-button--secondary" id="load-more-btn">
                                Load More
                            </button>
                        </div>
                    `
							: ''
					}
                `
						: `
                    <div class="blk-empty-state">
                        <p>🎉 Great news! All the accounts you follow also follow you back.</p>
                    </div>
                `
				}
                
            </div>
        `;
	}

	createAccountItem( account ) {
		const isWhitelisted = this.state.whitelist.includes( account.username );

		return `
            <div class="blk-account-item ${
				isWhitelisted ? 'blk-account-item--whitelisted' : ''
			}" data-username="${ account.username }">
                <div class="blk-account-avatar"></div>
                <div class="blk-account-info">
                    <div class="blk-account-username">
                        <a href="${
							account.profileUrl
						}" target="_blank" rel="noopener noreferrer">
                            ${ account.username }
                        </a>
                        ${
							isWhitelisted
								? '<span class="blk-whitelist-badge">Whitelisted</span>'
								: ''
						}
                    </div>
                    <div class="blk-account-meta">
                        ${
							account.timestamp
								? `<span class="blk-timestamp">Added ${ this.formatDate(
										account.timestamp
								  ) }</span>`
								: ''
						}
                    </div>
                </div>
                <div class="blk-account-actions">
                    ${
						this.config.isLoggedIn
							? `
                        <button class="blk-button blk-button--small ${
							isWhitelisted ? 'blk-button--secondary' : ''
						} blk-whitelist-btn" data-username="${
							account.username
						}">
                            ${
								isWhitelisted
									? 'Remove from Whitelist'
									: 'Add to Whitelist'
							}
                        </button>
                    `
							: ''
					}
                    <a href="${
						account.profileUrl
					}" target="_blank" class="blk-button blk-button--small blk-button--secondary">
                        View Profile
                    </a>
                </div>
            </div>
        `;
	}

	formatDate( dateString ) {
		try {
			const date = new Date( dateString );
			return date.toLocaleDateString();
		} catch ( error ) {
			return 'Unknown';
		}
	}

	createTeaserResults( data ) {
		const sampleCount = Math.min( data.stats.notFollowingBackCount, 5 );
		const hiddenCount = data.stats.notFollowingBackCount - sampleCount;

		return `
            <div class="blk-teaser-results">
                <div class="blk-teaser-overlay">
                    <div class="blk-teaser-content">
                        <div class="blk-teaser-icon">🔒</div>
                        <h3>Unlock Full Results</h3>
                        <p>You found <strong>${
							data.stats.notFollowingBackCount
						}</strong> accounts that don't follow you back!</p>
                        <div class="blk-teaser-benefits">
                            <div class="blk-benefit-item">
                                <span class="blk-benefit-icon">✅</span>
                                <span>See all ${
									data.stats.notFollowingBackCount
								} account names</span>
                            </div>
                            <div class="blk-benefit-item">
                                <span class="blk-benefit-icon">✅</span>
                                <span>Create a custom whitelist</span>
                            </div>
                        </div>
                        <div class="blk-teaser-cta">
                            <a href="${ this.getRegistrationUrl() }" class="blk-button blk-button--primary blk-button--large">
                                Create Free Account
                            </a>
                            <p class="blk-teaser-note">Already have an account? <a href="${ this.getLoginUrl() }">Login here</a></p>
                        </div>
                    </div>
                </div>
                <div class="blk-teaser-preview">
                    <h3>Preview: First ${ sampleCount } of ${
						data.stats.notFollowingBackCount
					} accounts</h3>
                    <div class="blk-accounts-list blk-accounts-list--blurred">
                        ${ this.createTeaserAccountItems(
							data.notFollowingBack.slice( 0, sampleCount )
						) }
                        ${
							hiddenCount > 0
								? `
                            <div class="blk-hidden-count">
                                <div class="blk-hidden-indicator">
                                    + ${ hiddenCount } more accounts hidden
                                </div>
                            </div>
                        `
								: ''
						}
                    </div>
                    <div class="blk-teaser-footer">
                        <a href="${ this.getLoginUrl() }" class="blk-button blk-button--primary">
                            Login to See All Results
                        </a>
                    </div>
                </div>
            </div>
        `;
	}

	createTeaserAccountItems( accounts ) {
		return accounts
			.map(
				( account ) => `
            <div class="blk-account-item blk-account-item--preview">
                <div class="blk-account-avatar"></div>
                <div class="blk-account-info">
                    <div class="blk-account-username">${ this.maskUsername(
						account.username
					) }</div>
                </div>
                <div class="blk-account-actions">
                    <span class="blk-masked-action">•••</span>
                </div>
            </div>
        `
			)
			.join( '' );
	}

	maskUsername( username ) {
		if ( username.length <= 3 ) {
			return '•'.repeat( username.length );
		}
		return username.substring( 0, 2 ) + '•'.repeat( username.length - 2 );
	}

	getLoginUrl() {
		return (
			window.location.origin +
			'/wp-login.php?redirect_to=' +
			encodeURIComponent( window.location.href )
		);
	}

	getRegistrationUrl() {
		return (
			window.location.origin +
			'/wp-login.php?action=register&redirect_to=' +
			encodeURIComponent( window.location.href )
		);
	}

	saveDataLocally( data ) {
		const success = this.stateManager.saveAnalysisData( data );
		if ( ! success ) {
			this.showError(
				'Could not save analysis data. Results may not persist.'
			);
		}
		return success;
	}

	async loadStoredData() {
		try {
			const storedData = this.stateManager.getAnalysisData();
			if ( storedData ) {
				this.state.analysisData = storedData;
				this.state.whitelist = this.stateManager.getWhitelist();
				await this.displayResults( storedData );
				return true;
			}
		} catch ( error ) {
			console.warn( 'Could not load stored data:', error );
		}
		return false;
	}

	saveFormState() {
		// Save current form state for recovery
		const formState = {
			hasData: !! this.state.analysisData,
			uploadInProgress: this.state.isProcessing,
			timestamp: Date.now(),
		};

		this.stateManager.saveFormState( formState );
	}

	restoreFormState() {
		const formState = this.stateManager.getFormState();
		if ( formState ) {
			// If upload was interrupted, show option to retry
			if ( formState.uploadInProgress ) {
				this.showUploadInterruptionMessage();
			}
		}
	}

	showUploadInterruptionMessage() {
		const message = document.createElement( 'div' );
		message.className = 'blk-interruption-notice';
		message.innerHTML = `
            <div class="blk-notice-content">
                <p>It looks like your previous upload was interrupted. Would you like to try uploading again?</p>
                <button class="blk-button blk-button--primary" onclick="this.parentElement.parentElement.remove()">
                    Try Again
                </button>
                <button class="blk-button blk-button--secondary" onclick="this.parentElement.parentElement.remove()">
                    Dismiss
                </button>
            </div>
        `;

		this.container.insertBefore( message, this.container.firstChild );
	}

	bindResultsEvents() {
		// Search functionality
		const searchInput = this.container.querySelector( '#account-search' );
		if ( searchInput ) {
			searchInput.addEventListener( 'input', ( e ) => {
				this.filterAccounts( e.target.value );
			} );
		}

		// Load more functionality
		const loadMoreBtn = this.container.querySelector( '#load-more-btn' );
		if ( loadMoreBtn ) {
			loadMoreBtn.addEventListener( 'click', () => {
				this.loadMoreAccounts();
			} );
		}

		// Whitelist buttons
		const whitelistBtns =
			this.container.querySelectorAll( '.blk-whitelist-btn' );
		whitelistBtns.forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				this.toggleWhitelist( e.target.dataset.username );
			} );
		} );

		// Whitelist management buttons
		const clearWhitelistBtn = this.container.querySelector(
			'#clear-whitelist-btn'
		);
		if ( clearWhitelistBtn ) {
			clearWhitelistBtn.addEventListener( 'click', async () => {
				await this.clearWhitelist();
			} );
		}

		const removeWhitelistBtns = this.container.querySelectorAll(
			'.blk-remove-whitelist-btn'
		);
		removeWhitelistBtns.forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				this.toggleWhitelist( e.target.dataset.username );
			} );
		} );

		// Reset analyzer button
		const resetBtn = this.container.querySelector( '#reset-analyzer-btn' );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', () => {
				this.resetAnalyzer();
			} );
		}
	}

	filterAccounts( searchTerm ) {
		const accountItems =
			this.container.querySelectorAll( '.blk-account-item' );
		const normalizedSearch = searchTerm.toLowerCase().trim();

		let visibleCount = 0;
		accountItems.forEach( ( item ) => {
			const username = item.dataset.username.toLowerCase();
			const isVisible = username.includes( normalizedSearch );
			item.style.display = isVisible ? 'flex' : 'none';
			if ( isVisible ) visibleCount++;
		} );

		// Update results count
		this.updateResultsCount( visibleCount, searchTerm );

		// Save search preference
		const preferences = this.stateManager.getUserPreferences();
		preferences.lastSearch = searchTerm;
		this.stateManager.saveUserPreferences( preferences );
	}

	updateResultsCount( count, searchTerm ) {
		let countDisplay = this.container.querySelector( '.blk-results-count' );
		if ( ! countDisplay ) {
			countDisplay = document.createElement( 'div' );
			countDisplay.className = 'blk-results-count';
			const header = this.container.querySelector(
				'.blk-section-header h3'
			);
			if ( header ) {
				header.parentNode.insertBefore(
					countDisplay,
					header.nextSibling
				);
			}
		}

		if ( searchTerm ) {
			countDisplay.textContent = `Showing ${ count } accounts matching "${ searchTerm }"`;
			countDisplay.style.display = 'block';
		} else {
			countDisplay.style.display = 'none';
		}
	}

	loadMoreAccounts() {
		const preferences = this.stateManager.getUserPreferences();
		preferences.itemsPerPage += 20;
		this.stateManager.saveUserPreferences( preferences );

		// Re-render results with new page size
		if ( this.state.analysisData ) {
			this.displayResults( this.state.analysisData );
		}
	}

	toggleWhitelist( username ) {
		let whitelist = this.stateManager.getWhitelist();
		const index = whitelist.indexOf( username );

		if ( index > -1 ) {
			whitelist.splice( index, 1 );
		} else {
			whitelist.push( username );
		}

		this.stateManager.saveWhitelist( whitelist );
		this.state.whitelist = whitelist;

		// Update UI
		this.updateWhitelistUI( username, index === -1 );

		// If user is logged in, sync with server
		if ( this.config.isLoggedIn ) {
			this.syncWhitelistWithServer();
		}
	}

	updateWhitelistUI( username, isWhitelisted ) {
		const accountItem = this.container.querySelector(
			`[data-username="${ username }"]`
		);
		if ( accountItem ) {
			const btn = accountItem.querySelector( '.blk-whitelist-btn' );
			if ( btn ) {
				btn.textContent = isWhitelisted
					? 'Remove from Whitelist'
					: 'Add to Whitelist';
				btn.classList.toggle( 'blk-button--secondary', isWhitelisted );
			}

			// Add visual indicator
			accountItem.classList.toggle(
				'blk-account-item--whitelisted',
				isWhitelisted
			);
		}
	}

	async syncWhitelistWithServer() {
		const whitelist = this.stateManager.getWhitelist();

		try {
			const response = await fetch( this.config.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'rwp_sync_instagram_whitelist',
					nonce: this.config.nonce,
					whitelist: JSON.stringify( whitelist ),
				} ),
			} );

			const result = await response.json();
			if ( ! result.success ) {
				console.error( 'Failed to sync whitelist:', result.data );
			}
		} catch ( error ) {
			console.error( 'Network error syncing whitelist:', error );
		}
	}

	restoreUserPreferences() {
		const preferences = this.stateManager.getUserPreferences();

		// Restore search term
		if ( preferences.lastSearch ) {
			const searchInput =
				this.container.querySelector( '#account-search' );
			if ( searchInput ) {
				searchInput.value = preferences.lastSearch;
				this.filterAccounts( preferences.lastSearch );
			}
		}

		// Apply other preferences as needed
		if ( preferences.sortOrder !== 'username' ) {
			this.applySorting( preferences.sortOrder );
		}
	}

	applySorting( sortOrder ) {
		if ( ! this.state.analysisData ) return;

		const sortedData = { ...this.state.analysisData };

		// Always sort with whitelisted accounts at the bottom
		const sortWithWhitelistAtBottom = ( primarySort ) => {
			sortedData.notFollowingBack.sort( ( a, b ) => {
				const aWhitelisted = this.state.whitelist.includes(
					a.username
				);
				const bWhitelisted = this.state.whitelist.includes(
					b.username
				);

				// If whitelist status is different, non-whitelisted comes first
				if ( aWhitelisted !== bWhitelisted ) {
					return aWhitelisted ? 1 : -1;
				}

				// If same whitelist status, apply primary sort
				return primarySort( a, b );
			} );
		};

		switch ( sortOrder ) {
			case 'newest':
				sortWithWhitelistAtBottom(
					( a, b ) =>
						new Date( b.timestamp ) - new Date( a.timestamp )
				);
				break;
			case 'oldest':
				sortWithWhitelistAtBottom(
					( a, b ) =>
						new Date( a.timestamp ) - new Date( b.timestamp )
				);
				break;
			default: // username
				sortWithWhitelistAtBottom( ( a, b ) =>
					a.username.localeCompare( b.username )
				);
		}

		// Re-render with sorted data
		this.displayResults( sortedData );
	}

	async loadServerData() {
		if ( ! this.config.isLoggedIn ) {
			return;
		}

		try {
			// Load whitelist from server
			const response = await fetch( this.config.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'rwp_get_instagram_whitelist',
					nonce: this.config.nonce,
				} ),
			} );

			const result = await response.json();
			if ( result.success ) {
				this.state.whitelist = result.data || [];
				// Also save to local storage for consistency
				this.stateManager.saveWhitelist( this.state.whitelist );
			}
		} catch ( error ) {
			console.error( 'Failed to load server data:', error );
		}
	}

	async clearWhitelist() {
		if (
			! confirm(
				'Are you sure you want to clear your entire whitelist? This action cannot be undone.'
			)
		) {
			return;
		}

		this.state.whitelist = [];
		this.stateManager.saveWhitelist( [] );

		if ( this.config.isLoggedIn ) {
			this.syncWhitelistWithServer();
		}

		// Refresh results to update UI
		if ( this.state.analysisData ) {
			await this.displayResults( this.state.analysisData );
		}
	}

	resetAnalyzer() {
		// Clear current state
		this.state.analysisData = null;
		this.state.isProcessing = false;
		this.state.uploadProgress = 0;

		// Clear stored data (but keep whitelist unless user clears it)
		this.stateManager.removeItem( 'analysis_data' );
		this.stateManager.clearFormState();

		// Hide results and show upload interface
		const resultsContainer =
			this.container.querySelector( '#results-container' );
		const uploadContainer = this.container.querySelector(
			'.blk-upload-container'
		);

		if ( resultsContainer ) resultsContainer.style.display = 'none';
		if ( uploadContainer ) {
			uploadContainer.style.display = 'block';

			// Reset file input
			const fileInput = this.container.querySelector( '#file-input' );
			if ( fileInput ) fileInput.value = '';
		}

		this.hideError();
		this.hideProgress();
	}

	showProgress( percentage ) {
		const progressContainer =
			this.container.querySelector( '#upload-progress' );
		const progressFill = this.container.querySelector( '#progress-fill' );
		const progressText = this.container.querySelector( '#progress-text' );

		progressContainer.style.display = 'block';
		progressFill.style.width = `${ percentage }%`;
		progressText.textContent = `${ Math.round( percentage ) }%`;
	}

	hideProgress() {
		const progressContainer =
			this.container.querySelector( '#upload-progress' );
		progressContainer.style.display = 'none';
	}

	showError( message ) {
		const errorContainer = this.container.querySelector( '#error-message' );
		errorContainer.textContent = message;
		errorContainer.style.display = 'block';
	}

	hideError() {
		const errorContainer = this.container.querySelector( '#error-message' );
		errorContainer.style.display = 'none';
	}
}

// Initialize the app when the DOM is ready
document.addEventListener( 'DOMContentLoaded', function () {
	// Check if we're on a page with the Instagram Analyzer block
	const container = document.getElementById( 'instagram-analyzer-app' );
	if ( container && typeof rwpInstagramAnalyzer !== 'undefined' ) {
		new InstagramAnalyzer( 'instagram-analyzer-app', rwpInstagramAnalyzer );
	}
} );
