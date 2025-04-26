/* global embedPrivacyAdminSettings */
document.addEventListener( 'DOMContentLoaded', () => {
	const actionElements = document.querySelectorAll( '.embed-privacy__support-data--copy-to-clipboard' );
	
	for ( const actionElement of actionElements ) {
		actionElement.addEventListener( 'click', ( event ) => clipboardAction( event.currentTarget ) );
	}
	
	/**
	 * Clipboard action.
	 * 
	 * @param {HTMLElement} element Element the action has been started from
	 */
	function clipboardAction( element ) {
		const copyElement = document.querySelector( '.' + element.getAttribute( 'data-copy' ) );
		const statusElement = document.querySelector( '.' + element.getAttribute( 'data-status' ) );
		
		if ( ! navigator.clipboard ) {
			statusElement.classList.add( 'is-error' );
			statusElement.classList.remove( 'is-success' );
			statusElement.textContent = embedPrivacyAdminSettings.supportDataCopiedToClipboardFailure;
			removeNotice( statusElement );
			console.error( 'Copy to clipboard not supported.' );
			
			return;
		}
		
		navigator.clipboard.writeText( copyElement.textContent ).then( () => {
			statusElement.classList.add( 'is-success' );
			statusElement.classList.remove( 'is-error' );
			statusElement.textContent = embedPrivacyAdminSettings.supportDataCopiedToClipboardSuccess;
			removeNotice( statusElement );
		} ).catch( ( error ) => {
			statusElement.classList.add( 'is-error' );
			statusElement.classList.remove( 'is-success' );
			statusElement.textContent = embedPrivacyAdminSettings.supportDataCopiedToClipboardFailure;
			removeNotice( statusElement );
			console.error( error );
		} );
	}
	
	/**
	 * Remove notice visually.
	 * 
	 * @param {HTMLElement} element Notice element
	 */
	function removeNotice( element ) {
		setTimeout( () => {
			element.classList.remove( 'is-error', 'is-success' );
			element.textContent = '';
		}, 10000 );
	}
} );
