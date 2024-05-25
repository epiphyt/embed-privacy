/**
 * Divi functionality
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
document.addEventListener( 'DOMContentLoaded', () => {
	// Generate responsive video wrapper for Embed Privacy container
	if ( $.fn.fitVids ) {
		$( '#main-content' ).fitVids( {
			customSelector: ".embed-privacy-container"
		} );
	}
} );
