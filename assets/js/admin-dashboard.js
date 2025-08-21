/**
 * RWP Creator Tools Admin Dashboard JavaScript
 * @param $
 */

( function ( $ ) {
	'use strict';

	/**
	 * Initialize the admin dashboard
	 */
	function initAdminDashboard() {
		// Add any interactive functionality here
		// For now, this is a placeholder for future enhancements

		// Example: Add click tracking for action buttons
		$( '.rwp-action-buttons .button' ).on( 'click', function () {
			const buttonText = $( this ).text().trim();
			console.log( 'Action button clicked:', buttonText );
		} );

		// Example: Add hover effects for tool items
		$( '.rwp-tool-item' )
			.on( 'mouseenter', function () {
				$( this ).css( 'transform', 'translateY(-2px)' );
				$( this ).css( 'transition', 'transform 0.2s ease' );
			} )
			.on( 'mouseleave', function () {
				$( this ).css( 'transform', 'translateY(0)' );
			} );

		// Console log to confirm script loading
		console.log( 'RWP Creator Tools Admin Dashboard initialized' );
	}

	// Initialize when document is ready
	$( document ).ready( function () {
		if ( typeof rwpAdminDashboard !== 'undefined' ) {
			initAdminDashboard();
		}
	} );
} )( jQuery );
