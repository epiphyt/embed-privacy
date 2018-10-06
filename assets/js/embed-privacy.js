/**
 * Embed Privacy JavaScript functions.
 * 
 * @author		Epiphyt
 * @license		GPL2
 * @package		epiphyt\Embed_Privacy
 * @version		1.0.0
 */
document.addEventListener( 'DOMContentLoaded', function() {
	var overlays = document.querySelectorAll( '.embed-overlay' );
	
	for ( var i = 0; i < overlays.length; i++ ) {
		overlays[ i ].addEventListener( 'click', function( event ) {
			var current_target = event.currentTarget;
			var embed_content = current_target.nextElementSibling;
			
			// hide the embed overlay
			current_target.style.display = 'none';
			// remove the HTML comments from the embed content
			embed_content.innerHTML = embed_content.innerHTML.replace( /<!--/, '' ).replace( /-->/, '' );
			
			// get all script tags inside the embed
			var script_tags = embed_content.querySelectorAll( 'script' );
			
			// insert every script tag inside the embed as a new script
			// to execute it
			for ( var n = 0; n < script_tags.length; n++ ) {
				var element = document.createElement( 'script' );
				
				if ( script_tags[ n ].src ) {
					// if script tag has a src attribute
					element.src = script_tags[ n ].src;
				}
				else {
					// if script tag has content
					element.innerHTML = script_tags[ n ].innerHTML;
				}
				
				// append it to body
				embed_content.appendChild( element );
			}
		} )
	}
} );