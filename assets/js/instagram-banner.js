/**
 * Instagram Banner Creator App
 *
 * Client-side application for creating Instagram banner images from uploaded images.
 * Splits a 3248x1440 image into three 1080x1440 images with 4px gaps.
 */

class InstagramBannerCreator {
	constructor( containerId, config = {} ) {
		this.container = document.getElementById( containerId );
		if ( ! this.container ) {
			console.error(
				'Instagram Banner Creator container not found:',
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

		// Canvas and image specifications
		this.specs = {
			targetWidth: 3248,
			targetHeight: 1440,
			outputWidth: 1080,
			outputHeight: 1440,
			gapSize: 4,
			maxFileSize: 10 * 1024 * 1024, // 10MB
			supportedFormats: [ 'image/jpeg', 'image/png', 'image/webp' ],
		};

		this.state = {
			uploadedImage: null,
			croppedImageData: null,
			currentStep: 'upload', // upload, crop, preview, download
			isProcessing: false,
		};

		// Initialize state manager
		this.stateManager = new StateManager( {
			storagePrefix: 'rwp_instagram_banner_',
			maxDataAge: 24 * 60 * 60 * 1000, // 24 hours
		} );

		this.init();
	}

	async init() {
		this.createInterface();
		this.bindEvents();
		await this.restoreState();
	}

	createInterface() {
		const interfaceHTML = `
            <div class="blk-banner-creator">
                <div class="blk-creator-step" data-step="upload">
                    ${ this.createUploadInterface() }
                </div>
                <div class="blk-creator-step" data-step="crop" style="display: none;">
                    ${ this.createCropInterface() }
                </div>
                <div class="blk-creator-step" data-step="preview" style="display: none;">
                    ${ this.createPreviewInterface() }
                </div>
            </div>
        `;

		this.container.innerHTML = interfaceHTML;
	}

	createUploadInterface() {
		return `
            <div class="blk-upload-section">
                <div class="blk-upload-zone" id="upload-zone">
                    <div class="blk-upload-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="9" cy="9" r="2"/>
                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                        </svg>
                    </div>
                    <div class="blk-upload-content">
                        <h3 class="blk-upload-title">Drop your image here</h3>
                        <p class="blk-upload-subtitle">or click to browse files</p>
                        <div class="blk-file-info">
                            Supports: JPEG, PNG, WebP • Max size: 10MB
                        </div>
                    </div>
                    <input type="file" id="file-input" accept="image/jpeg,image/png,image/webp" style="display: none;" />
                </div>
                
                <div class="blk-upload-progress" id="upload-progress" style="display: none;">
                    <div class="blk-progress-bar">
                        <div class="blk-progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="blk-progress-text" id="progress-text">0%</div>
                </div>
                
                <div class="blk-error-message" id="error-message" style="display: none;"></div>
            </div>
        `;
	}

	createCropInterface() {
		return `
            <div class="blk-crop-section">
                <div class="blk-crop-container">
                    <div class="blk-crop-canvas-container" id="crop-canvas-container">
                        <canvas id="crop-canvas" class="blk-crop-canvas"></canvas>
                        <div class="blk-crop-frame" id="crop-frame">
                            <div class="blk-frame-border"></div>
                        </div>
                    </div>
                    
                    <div class="blk-crop-controls">
                        <div class="blk-control-group">
                            <label class="blk-control-label">Aspect Ratio</label>
                            <div class="blk-aspect-info">3248 × 1440 (locked)</div>
                        </div>
                        
                        <div class="blk-control-actions">
                            <button type="button" class="blk-button blk-button--secondary" id="reset-crop-btn">
                                Reset Position
                            </button>
                            <button type="button" class="blk-button blk-button--secondary" id="back-to-upload-btn">
                                Back
                            </button>
                            <button type="button" class="blk-button blk-button--primary" id="continue-to-preview-btn">
                                Continue to Preview
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
	}

	createPreviewInterface() {
		return `
            <div class="blk-preview-section">
                <div class="blk-preview-container">
                    <div class="blk-banner-preview" id="banner-preview">
                        <div class="blk-banner-image" id="banner-image-1">
                            <canvas id="preview-canvas-1"></canvas>
                            <div class="blk-image-label">Image 1</div>
                        </div>
                        <div class="blk-banner-gap"></div>
                        <div class="blk-banner-image" id="banner-image-2">
                            <canvas id="preview-canvas-2"></canvas>
                            <div class="blk-image-label">Image 2</div>
                        </div>
                        <div class="blk-banner-gap"></div>
                        <div class="blk-banner-image" id="banner-image-3">
                            <canvas id="preview-canvas-3"></canvas>
                            <div class="blk-image-label">Image 3</div>
                        </div>
                    </div>
                    
                    ${
						this.config.isLoggedIn
							? this.createDownloadInterface()
							: this.createGuestInterface()
					}
                </div>
                
                <div class="blk-preview-controls">
                    <button type="button" class="blk-button blk-button--secondary" id="back-to-crop-btn">
                        Back to Crop
                    </button>
                    <button type="button" class="blk-button blk-button--secondary" id="start-over-btn">
                        Start Over
                    </button>
                </div>
            </div>
        `;
	}

	createDownloadInterface() {
		return `
            <div class="blk-download-section">
                <div class="blk-download-controls">
                    <button type="button" class="blk-button blk-button--primary blk-button--large" id="download-all-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7,10 12,15 17,10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        ${
							this.config.strings.download ||
							'Download All Images'
						}
                    </button>
                    
                    <div class="blk-individual-downloads">
                        <button type="button" class="blk-button blk-button--small" data-image="1">
                            Download Image 1
                        </button>
                        <button type="button" class="blk-button blk-button--small" data-image="2">
                            Download Image 2
                        </button>
                        <button type="button" class="blk-button blk-button--small" data-image="3">
                            Download Image 3
                        </button>
                    </div>
                </div>
            </div>
        `;
	}

	createGuestInterface() {
		return `
            <div class="blk-teaser-results">
                <div class="blk-teaser-overlay">
                    <div class="blk-teaser-content">
                        <div class="blk-teaser-icon">🔒</div>
                        <h3>Create Account to Download</h3>
                        <p>Your banner images are ready! <strong>Create a free account</strong> to download them.</p>
                        
                        <div class="blk-teaser-benefits">
                            <div class="blk-benefit-item">
                                <span class="blk-benefit-icon">✅</span>
                                <span>Download high-quality images</span>
                            </div>
                            <div class="blk-benefit-item">
                                <span class="blk-benefit-icon">✅</span>
                                <span>Save your work automatically</span>
                            </div>
                            <div class="blk-benefit-item">
                                <span class="blk-benefit-icon">✅</span>
                                <span>Access all creator tools</span>
                            </div>
                        </div>
                        
                        <div class="blk-teaser-cta">
                            <a href="${ this.getRegistrationUrl() }" class="blk-button blk-button--primary blk-button--large">
                                Create Free Account
                            </a>
                            <p class="blk-teaser-note">
                                Already have an account? <a href="${ this.getLoginUrl() }">Login here</a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="blk-teaser-preview">
                    <div class="blk-banner-preview" id="banner-preview">
                        <div class="blk-banner-image" id="banner-image-1">
                            <canvas id="preview-canvas-1"></canvas>
                            <div class="blk-image-label">Image 1</div>
                        </div>
                        <div class="blk-banner-gap"></div>
                        <div class="blk-banner-image" id="banner-image-2">
                            <canvas id="preview-canvas-2"></canvas>
                            <div class="blk-image-label">Image 2</div>
                        </div>
                        <div class="blk-banner-gap"></div>
                        <div class="blk-banner-image" id="banner-image-3">
                            <canvas id="preview-canvas-3"></canvas>
                            <div class="blk-image-label">Image 3</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
	}

	bindEvents() {
		// Upload events
		const uploadZone = this.container.querySelector( '#upload-zone' );
		const fileInput = this.container.querySelector( '#file-input' );

		if ( uploadZone && fileInput ) {
			// Drag and drop events
			uploadZone.addEventListener( 'dragover', ( e ) =>
				this.handleDragOver( e )
			);
			uploadZone.addEventListener( 'dragleave', ( e ) =>
				this.handleDragLeave( e )
			);
			uploadZone.addEventListener( 'drop', ( e ) =>
				this.handleDrop( e )
			);
			uploadZone.addEventListener( 'click', () => fileInput.click() );

			// File input change
			fileInput.addEventListener( 'change', ( e ) =>
				this.handleFileSelect( e )
			);
		}

		// Crop events
		this.bindCropEvents();

		// Preview and download events
		this.bindPreviewEvents();

		// Navigation events
		this.bindNavigationEvents();
	}

	bindCropEvents() {
		// Crop area dragging and resizing will be implemented in the crop component
		const resetCropBtn = this.container.querySelector( '#reset-crop-btn' );
		const continueBtn = this.container.querySelector(
			'#continue-to-preview-btn'
		);

		if ( resetCropBtn ) {
			resetCropBtn.addEventListener( 'click', () => this.resetCrop() );
		}

		if ( continueBtn ) {
			continueBtn.addEventListener( 'click', () =>
				this.processCroppedImage()
			);
		}
	}

	bindPreviewEvents() {
		const downloadAllBtn =
			this.container.querySelector( '#download-all-btn' );
		const individualBtns =
			this.container.querySelectorAll( '[data-image]' );

		if ( downloadAllBtn ) {
			downloadAllBtn.addEventListener( 'click', () =>
				this.downloadAllImages()
			);
		}

		individualBtns.forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				const imageIndex = parseInt( e.target.dataset.image );
				this.downloadSingleImage( imageIndex );
			} );
		} );
	}

	bindNavigationEvents() {
		const backToUploadBtn = this.container.querySelector(
			'#back-to-upload-btn'
		);
		const backToCropBtn =
			this.container.querySelector( '#back-to-crop-btn' );
		const startOverBtn = this.container.querySelector( '#start-over-btn' );

		if ( backToUploadBtn ) {
			backToUploadBtn.addEventListener( 'click', async () => {
				await this.goToStep( 'upload' );
			} );
		}

		if ( backToCropBtn ) {
			backToCropBtn.addEventListener( 'click', async () => {
				await this.goToStep( 'crop' );
			} );
		}

		if ( startOverBtn ) {
			startOverBtn.addEventListener( 'click', async () => {
				await this.startOver();
			} );
		}
	}

	// Event handlers
	handleDragOver( e ) {
		e.preventDefault();
		e.currentTarget.classList.add( 'blk-upload-zone--dragover' );
	}

	handleDragLeave( e ) {
		e.preventDefault();
		e.currentTarget.classList.remove( 'blk-upload-zone--dragover' );
	}

	handleDrop( e ) {
		e.preventDefault();
		e.currentTarget.classList.remove( 'blk-upload-zone--dragover' );

		const files = e.dataTransfer.files;
		if ( files.length > 0 ) {
			this.handleImageUpload( files[ 0 ] );
		}
	}

	handleFileSelect( e ) {
		if ( e.target.files.length > 0 ) {
			this.handleImageUpload( e.target.files[ 0 ] );
		}
	}

	// Core functionality methods
	async handleImageUpload( file ) {
		if ( ! this.validateFile( file ) ) {
			return;
		}

		this.state.isProcessing = true;
		this.showProgress( 0 );
		this.hideError();

		try {
			this.showProgress( 25 );

			// Load the image
			const imageData = await this.loadImageFile( file );
			this.state.uploadedImage = imageData;

			this.showProgress( 50 );

			// Move to crop step first
			await this.goToStep( 'crop' );

			this.showProgress( 75 );

			// Initialize crop interface after step transition
			await this.initializeCropInterface( imageData );

			this.showProgress( 100 );

			// Hide progress after short delay
			setTimeout( () => {
				this.hideProgress();
			}, 300 );
		} catch ( error ) {
			console.error( 'Image upload error:', error );
			this.showError(
				'Failed to process the uploaded image. Please try a different image.'
			);
		} finally {
			this.state.isProcessing = false;
		}
	}

	validateFile( file ) {
		// Check file type
		if ( ! this.specs.supportedFormats.includes( file.type ) ) {
			this.showError( 'Please upload a JPEG, PNG, or WebP image.' );
			return false;
		}

		// Check file size
		if ( file.size > this.specs.maxFileSize ) {
			this.showError(
				'File size is too large. Please upload an image under 10MB.'
			);
			return false;
		}

		return true;
	}

	loadImageFile( file ) {
		return new Promise( ( resolve, reject ) => {
			const reader = new FileReader();

			reader.onload = ( e ) => {
				const img = new Image();

				img.onload = () => {
					resolve( {
						src: e.target.result,
						width: img.width,
						height: img.height,
						image: img,
					} );
				};

				img.onerror = reject;
				img.src = e.target.result;
			};

			reader.onerror = reject;
			reader.readAsDataURL( file );
		} );
	}

	async initializeCropInterface( imageData ) {
		// Wait for the crop interface to be rendered
		await new Promise( ( resolve ) => setTimeout( resolve, 200 ) );

		const canvas = this.container.querySelector( '#crop-canvas' );
		if ( ! canvas ) {
			console.error(
				'Crop canvas not found in container:',
				this.container
			);
			throw new Error( 'Crop canvas not found' );
		}

		const ctx = canvas.getContext( '2d' );

		// Set canvas size based on aspect ratio (3248x1440)
		const containerWidth = canvas.parentElement.clientWidth || 800;
		const aspectRatio = this.specs.targetWidth / this.specs.targetHeight; // 3248/1440
		const canvasWidth = containerWidth;
		const canvasHeight = canvasWidth / aspectRatio;

		// Set canvas to exact aspect ratio
		canvas.width = canvasWidth;
		canvas.height = canvasHeight;

		// For restored images (from page refresh), use the same scaling logic as original uploads
		// so users can still drag the image around to adjust the crop
		if ( imageData.isRestored && this.imagePosition ) {
			// Use the same scaling logic as original uploads to ensure draggability
			const imageAspect = imageData.width / imageData.height;
			const canvasAspect = canvas.width / canvas.height;

			// Make image 150% of canvas size so there's room to drag
			const scaleFactor = 1.5;
			let drawWidth, drawHeight;

			if ( imageAspect > canvasAspect ) {
				// Scale by height
				drawHeight = canvas.height * scaleFactor;
				drawWidth = drawHeight * imageAspect;
			} else {
				// Scale by width
				drawWidth = canvas.width * scaleFactor;
				drawHeight = drawWidth / imageAspect;
			}

			// Use restored position, or center if not available
			if ( ! this.imagePosition ) {
				this.imagePosition = {
					x: ( canvas.width - drawWidth ) / 2,
					y: ( canvas.height - drawHeight ) / 2,
				};
			}

			// Store image info for dragging and cropping
			this.cropImageInfo = {
				originalImage: imageData.image,
				drawWidth,
				drawHeight,
				originalWidth: imageData.width,
				originalHeight: imageData.height,
				scaleX: drawWidth / imageData.width,
				scaleY: drawHeight / imageData.height,
			};
		} else {
			// Original upload flow - make image larger for cropping
			const imageAspect = imageData.width / imageData.height;
			const canvasAspect = canvas.width / canvas.height;

			// Make image 150% of canvas size so there's room to drag
			const scaleFactor = 1.5;
			let drawWidth, drawHeight;

			if ( imageAspect > canvasAspect ) {
				// Scale by height
				drawHeight = canvas.height * scaleFactor;
				drawWidth = drawHeight * imageAspect;
			} else {
				// Scale by width
				drawWidth = canvas.width * scaleFactor;
				drawHeight = drawWidth / imageAspect;
			}

			// Initial position (centered)
			this.imagePosition = {
				x: ( canvas.width - drawWidth ) / 2,
				y: ( canvas.height - drawHeight ) / 2,
			};

			// Store image info for dragging and cropping
			this.cropImageInfo = {
				originalImage: imageData.image,
				drawWidth,
				drawHeight,
				originalWidth: imageData.width,
				originalHeight: imageData.height,
				scaleX: drawWidth / imageData.width,
				scaleY: drawHeight / imageData.height,
			};
		}

		// Draw the image at initial position
		this.redrawCropCanvas();

		// Make image draggable - users should be able to adjust crop position even for restored images
		this.makeImageDraggable( canvas );
	}

	redrawCropCanvas() {
		const canvas = this.container.querySelector( '#crop-canvas' );
		if ( ! canvas || ! this.cropImageInfo ) {
			return;
		}

		const ctx = canvas.getContext( '2d' );

		// Clear canvas
		ctx.clearRect( 0, 0, canvas.width, canvas.height );

		// Draw image at current position
		ctx.drawImage(
			this.cropImageInfo.originalImage,
			this.imagePosition.x,
			this.imagePosition.y,
			this.cropImageInfo.drawWidth,
			this.cropImageInfo.drawHeight
		);
	}

	makeImageDraggable( canvas ) {
		let isDragging = false;
		const dragStart = { x: 0, y: 0 };
		const initialPosition = { x: 0, y: 0 };

		// Set cursor style
		canvas.style.cursor = 'grab';

		canvas.addEventListener( 'mousedown', ( e ) => {
			isDragging = true;
			dragStart.x = e.clientX;
			dragStart.y = e.clientY;
			initialPosition.x = this.imagePosition.x;
			initialPosition.y = this.imagePosition.y;

			canvas.style.cursor = 'grabbing';
			e.preventDefault();
		} );

		document.addEventListener( 'mousemove', ( e ) => {
			if ( ! isDragging ) {
				return;
			}

			const deltaX = e.clientX - dragStart.x;
			const deltaY = e.clientY - dragStart.y;

			// Calculate new position
			let newX = initialPosition.x + deltaX;
			let newY = initialPosition.y + deltaY;

			// Constrain image movement so it can't be dragged completely outside the canvas
			const minX = canvas.width - this.cropImageInfo.drawWidth;
			const maxX = 0;
			const minY = canvas.height - this.cropImageInfo.drawHeight;
			const maxY = 0;

			newX = Math.max( minX, Math.min( newX, maxX ) );
			newY = Math.max( minY, Math.min( newY, maxY ) );

			// Update position and redraw
			this.imagePosition.x = newX;
			this.imagePosition.y = newY;

			this.redrawCropCanvas();
		} );

		document.addEventListener( 'mouseup', () => {
			if ( isDragging ) {
				isDragging = false;
				canvas.style.cursor = 'grab';
				// Save the updated position to localStorage
				this.saveState();
			}
		} );
	}

	resetCrop() {
		if ( this.state.uploadedImage && this.cropImageInfo ) {
			// Reset image to center position
			const canvas = this.container.querySelector( '#crop-canvas' );
			this.imagePosition = {
				x: ( canvas.width - this.cropImageInfo.drawWidth ) / 2,
				y: ( canvas.height - this.cropImageInfo.drawHeight ) / 2,
			};
			this.redrawCropCanvas();
		}
	}

	async processCroppedImage() {
		this.state.isProcessing = true;
		this.showProgress( 0 );

		try {
			const canvas = this.container.querySelector( '#crop-canvas' );

			// The entire canvas is the crop area now
			const cropRect = {
				x: 0,
				y: 0,
				width: canvas.width,
				height: canvas.height,
			};

			this.showProgress( 25 );

			// Create cropped image data
			const croppedImageData = await this.createCroppedImage( cropRect );
			this.state.croppedImageData = croppedImageData;

			this.showProgress( 50 );

			// Generate preview images
			await this.generatePreviewImages( croppedImageData );

			this.showProgress( 100 );

			// Save state
			this.saveState();

			// Move to preview step
			setTimeout( async () => {
				await this.goToStep( 'preview' );
				this.hideProgress();
			}, 500 );
		} catch ( error ) {
			console.error( 'Crop processing error:', error );
			this.showError(
				'Failed to process the cropped image. Please try again.'
			);
		} finally {
			this.state.isProcessing = false;
		}
	}

	createCroppedImage( cropRect ) {
		return new Promise( ( resolve ) => {
			// Create a new canvas for the cropped image
			const croppedCanvas = document.createElement( 'canvas' );
			croppedCanvas.width = this.specs.targetWidth;
			croppedCanvas.height = this.specs.targetHeight;

			const ctx = croppedCanvas.getContext( '2d' );

			// Calculate which part of the original image is visible in the canvas
			// The canvas shows a specific region of the original image based on position and scaling

			// Convert canvas coordinates to original image coordinates
			const canvas = this.container.querySelector( '#crop-canvas' );

			// The visible area of the original image (what we want to crop)
			const visibleX = -this.imagePosition.x / this.cropImageInfo.scaleX;
			const visibleY = -this.imagePosition.y / this.cropImageInfo.scaleY;
			const visibleWidth = canvas.width / this.cropImageInfo.scaleX;
			const visibleHeight = canvas.height / this.cropImageInfo.scaleY;

			// Ensure we don't go outside original image bounds
			const clampedX = Math.max(
				0,
				Math.min( visibleX, this.cropImageInfo.originalWidth )
			);
			const clampedY = Math.max(
				0,
				Math.min( visibleY, this.cropImageInfo.originalHeight )
			);
			const clampedWidth = Math.min(
				visibleWidth,
				this.cropImageInfo.originalWidth - clampedX
			);
			const clampedHeight = Math.min(
				visibleHeight,
				this.cropImageInfo.originalHeight - clampedY
			);

			// Draw the visible portion scaled to target size
			ctx.drawImage(
				this.cropImageInfo.originalImage,
				clampedX,
				clampedY,
				clampedWidth,
				clampedHeight,
				0,
				0,
				this.specs.targetWidth,
				this.specs.targetHeight
			);

			resolve( {
				canvas: croppedCanvas,
				dataUrl: croppedCanvas.toDataURL( 'image/jpeg', 0.9 ),
			} );
		} );
	}

	async generatePreviewImages( croppedImageData ) {
		const canvas1 = this.container.querySelector( '#preview-canvas-1' );
		const canvas2 = this.container.querySelector( '#preview-canvas-2' );
		const canvas3 = this.container.querySelector( '#preview-canvas-3' );

		// Set canvas sizes
		[ canvas1, canvas2, canvas3 ].forEach( ( canvas ) => {
			canvas.width = this.specs.outputWidth;
			canvas.height = this.specs.outputHeight;
		} );

		// Split the image into 3 parts
		const ctx1 = canvas1.getContext( '2d' );
		const ctx2 = canvas2.getContext( '2d' );
		const ctx3 = canvas3.getContext( '2d' );

		// Image 1: 0 to 1080px
		ctx1.drawImage(
			croppedImageData.canvas,
			0,
			0,
			this.specs.outputWidth,
			this.specs.targetHeight,
			0,
			0,
			this.specs.outputWidth,
			this.specs.outputHeight
		);

		// Image 2: 1084 to 2164px (accounting for 4px gap)
		ctx2.drawImage(
			croppedImageData.canvas,
			this.specs.outputWidth + this.specs.gapSize,
			0,
			this.specs.outputWidth,
			this.specs.targetHeight,
			0,
			0,
			this.specs.outputWidth,
			this.specs.outputHeight
		);

		// Image 3: 2168 to 3248px (accounting for 4px gap)
		ctx3.drawImage(
			croppedImageData.canvas,
			( this.specs.outputWidth + this.specs.gapSize ) * 2,
			0,
			this.specs.outputWidth,
			this.specs.targetHeight,
			0,
			0,
			this.specs.outputWidth,
			this.specs.outputHeight
		);

		// Apply blur effect for guest users
		if ( ! this.config.isLoggedIn ) {
			this.applyGuestBlur( [ canvas1, canvas2, canvas3 ] );
		}
	}

	applyGuestBlur( canvases ) {
		canvases.forEach( ( canvas ) => {
			const ctx = canvas.getContext( '2d' );
			const imageData = ctx.getImageData(
				0,
				0,
				canvas.width,
				canvas.height
			);
			const data = imageData.data;

			// Apply a simple blur effect by reducing color intensity
			for ( let i = 0; i < data.length; i += 4 ) {
				data[ i ] = data[ i ] * 0.6; // Red
				data[ i + 1 ] = data[ i + 1 ] * 0.6; // Green
				data[ i + 2 ] = data[ i + 2 ] * 0.6; // Blue
				// Alpha stays the same
			}

			ctx.putImageData( imageData, 0, 0 );

			// Add blur filter
			canvas.style.filter = 'blur(2px)';
		} );
	}

	downloadAllImages() {
		for ( let i = 1; i <= 3; i++ ) {
			this.downloadSingleImage( i );
		}
	}

	downloadSingleImage( index ) {
		const canvas = this.container.querySelector(
			`#preview-canvas-${ index }`
		);
		if ( ! canvas ) {
			return;
		}

		const link = document.createElement( 'a' );
		link.download = `instagram-banner-${ index }.jpg`;
		link.href = canvas.toDataURL( 'image/jpeg', 0.9 );
		link.click();
	}

	// Navigation methods
	async goToStep( step ) {
		// Hide all steps
		this.container
			.querySelectorAll( '.blk-creator-step' )
			.forEach( ( stepEl ) => {
				stepEl.style.display = 'none';
			} );

		// Show target step
		const targetStep = this.container.querySelector(
			`[data-step="${ step }"]`
		);
		if ( targetStep ) {
			targetStep.style.display = 'block';
			this.state.currentStep = step;
		}

		// Special handling for crop step - initialize crop interface if needed
		if (
			step === 'crop' &&
			this.state.uploadedImage &&
			! this.cropImageInfo
		) {
			try {
				await this.initializeCropInterface( this.state.uploadedImage );
			} catch ( error ) {
				console.error( 'Failed to initialize crop interface:', error );
				this.showError(
					'Failed to initialize crop interface. Please start over.'
				);
			}
		}

		// Update state
		this.saveState();
	}

	async startOver() {
		if (
			confirm(
				'Are you sure you want to start over? This will clear your current work.'
			)
		) {
			this.state = {
				uploadedImage: null,
				croppedImageData: null,
				currentStep: 'upload',
				isProcessing: false,
			};

			this.clearState();
			await this.goToStep( 'upload' );

			// Reset file input
			const fileInput = this.container.querySelector( '#file-input' );
			if ( fileInput ) {
				fileInput.value = '';
			}
		}
	}

	// State management
	saveState() {
		const stateData = {
			currentStep: this.state.currentStep,
			hasUploadedImage: !! this.state.uploadedImage,
			hasCroppedImage: !! this.state.croppedImageData,
			timestamp: Date.now(),
		};

		this.stateManager.saveFormState( stateData );

		// Save image data using the analyzer pattern
		if ( this.state.croppedImageData ) {
			this.saveBannerData();
		}
	}

	async restoreState() {
		const savedState = this.stateManager.getFormState();
		if ( savedState && savedState.timestamp > Date.now() - 3600000 ) {
			// 1 hour

			// Try to restore saved banner data first
			await this.loadStoredBannerData();

			// Then navigate to the saved step
			await this.goToStep( savedState.currentStep || 'upload' );
		}
	}

	clearState() {
		this.stateManager.clearFormState();
		this.stateManager.removeItem( 'banner_data' );
	}

	saveBannerData() {
		if ( ! this.state.croppedImageData ) {
			return false;
		}

		const bannerData = {
			croppedImageDataUrl: this.state.croppedImageData.dataUrl,
			originalImageDataUrl: this.state.uploadedImage
				? this.state.uploadedImage.src
				: null,
			originalImageWidth: this.state.uploadedImage
				? this.state.uploadedImage.width
				: null,
			originalImageHeight: this.state.uploadedImage
				? this.state.uploadedImage.height
				: null,
			imagePosition: this.imagePosition || null,
			timestamp: Date.now(),
			currentStep: this.state.currentStep,
		};

		const success = this.stateManager.setItem( 'banner_data', bannerData );
		if ( ! success ) {
			console.warn(
				'Could not save banner data. Results may not persist.'
			);
		}
		return success;
	}

	async loadStoredBannerData() {
		try {
			const storedData = this.stateManager.getItem( 'banner_data' );
			if ( storedData && storedData.croppedImageDataUrl ) {
				// Recreate the cropped image data from stored data URL
				this.state.croppedImageData = {
					dataUrl: storedData.croppedImageDataUrl,
					canvas: await this.createCanvasFromDataUrl(
						storedData.croppedImageDataUrl
					),
				};

				// Recreate the uploaded image state from the original image data (if available)
				// This is needed for the crop interface to work properly after page refresh
				if ( storedData.originalImageDataUrl ) {
					const originalImage = new Image();
					await new Promise( ( resolve, reject ) => {
						originalImage.onload = resolve;
						originalImage.onerror = reject;
						originalImage.src = storedData.originalImageDataUrl;
					} );

					this.state.uploadedImage = {
						src: storedData.originalImageDataUrl,
						width:
							storedData.originalImageWidth ||
							originalImage.width,
						height:
							storedData.originalImageHeight ||
							originalImage.height,
						image: originalImage,
						isRestored: true, // Flag to indicate this is restored from storage
					};

					// Restore image position if available
					if ( storedData.imagePosition ) {
						this.imagePosition = storedData.imagePosition;
					}
				} else {
					// Fallback to cropped image if original isn't available (backward compatibility)
					const croppedImage = new Image();
					await new Promise( ( resolve, reject ) => {
						croppedImage.onload = resolve;
						croppedImage.onerror = reject;
						croppedImage.src = storedData.croppedImageDataUrl;
					} );

					this.state.uploadedImage = {
						src: storedData.croppedImageDataUrl,
						width: croppedImage.width,
						height: croppedImage.height,
						image: croppedImage,
						isRestored: true, // Flag to indicate this is restored from storage
					};
				}

				// If we're on the preview step, regenerate the preview images
				if ( storedData.currentStep === 'preview' ) {
					await this.generatePreviewImages(
						this.state.croppedImageData
					);
				}

				return true;
			}
		} catch ( error ) {
			console.warn( 'Could not load stored banner data:', error );
		}
		return false;
	}

	createCanvasFromDataUrl( dataUrl ) {
		return new Promise( ( resolve, reject ) => {
			const img = new Image();
			img.onload = () => {
				const canvas = document.createElement( 'canvas' );
				canvas.width = this.specs.targetWidth;
				canvas.height = this.specs.targetHeight;
				const ctx = canvas.getContext( '2d' );
				ctx.drawImage( img, 0, 0 );
				resolve( canvas );
			};
			img.onerror = reject;
			img.src = dataUrl;
		} );
	}

	// Utility methods
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

	showProgress( percentage ) {
		const progressContainer =
			this.container.querySelector( '#upload-progress' );
		const progressFill = this.container.querySelector( '#progress-fill' );
		const progressText = this.container.querySelector( '#progress-text' );

		if ( progressContainer ) {
			progressContainer.style.display = 'block';
		}
		if ( progressFill ) {
			progressFill.style.width = `${ percentage }%`;
		}
		if ( progressText ) {
			progressText.textContent = `${ Math.round( percentage ) }%`;
		}
	}

	hideProgress() {
		const progressContainer =
			this.container.querySelector( '#upload-progress' );
		if ( progressContainer ) {
			progressContainer.style.display = 'none';
		}
	}

	showError( message ) {
		const errorContainer = this.container.querySelector( '#error-message' );
		if ( errorContainer ) {
			errorContainer.textContent = message;
			errorContainer.style.display = 'block';
		}
	}

	hideError() {
		const errorContainer = this.container.querySelector( '#error-message' );
		if ( errorContainer ) {
			errorContainer.style.display = 'none';
		}
	}
}

// Initialize the app when the DOM is ready
document.addEventListener( 'DOMContentLoaded', function () {
	// Check if we're on a page with the Instagram Banner block
	const container = document.getElementById( 'instagram-banner-app' );
	if ( container && typeof rwpInstagramBanner !== 'undefined' ) {
		new InstagramBannerCreator(
			'instagram-banner-app',
			rwpInstagramBanner
		);
	}
} );
