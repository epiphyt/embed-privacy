/**
 * Embed Privacy JavaScript functions.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
document.addEventListener( 'DOMContentLoaded', function() {
	var checkboxes = document.querySelectorAll( '.embed-privacy-inner .embed-privacy-input' );
	var labels = document.querySelectorAll( '.embed-privacy-inner .embed-privacy-label' );
	var overlays = document.querySelectorAll( '.embed-privacy-overlay' );
	var overlayLinks = document.querySelectorAll( '.embed-privacy-overlay a' );
	
	initOverlays( overlays, overlayLinks, checkboxes, labels );
	initMutationObserver();
	optOut();
	setMinHeight();
	
	window.addEventListener( 'resize', function() {
		setMinHeight();
	} );
	
	/**
	 * Clicking on a checkbox to always enable/disable an embed provider.
	 * 
	 * @since	1.2.0
	 * 
	 * @param	{HTMLElement}	target Target element
	 */
	function checkboxActivation( target ) {
		const container = target.closest( '.embed-privacy-container' );
		var embedProvider = target.getAttribute( 'data-embed-provider' );
		var cookie = ( get_cookie( 'embed-privacy' ) ? JSON.parse( get_cookie( 'embed-privacy' ) ) : '' );
		
		if ( target.checked ) {
			// add|update the cookie's value
			if ( cookie !== null && Object.keys( cookie ).length !== 0 && cookie.constructor === Object ) {
				cookie[ embedProvider ] = true;
				
				set_cookie( 'embed-privacy', JSON.stringify( cookie ), 365 );
			}
			else {
				set_cookie( 'embed-privacy', '{"' + embedProvider + '":true}', 365 );
			}
			
			enableAlwaysActiveProviders( document.querySelectorAll( '.embed-privacy-overlay' ) );
			
			// focus first element in container, but not for opt-out shortcode
			if ( container && document.activeElement !== container ) {
				container.querySelector( '.embed-privacy-content > :first-child' ).focus();
			}
		}
		else if ( cookie !== null ) {
			delete cookie[ embedProvider ];
			
			if ( Object.keys( cookie ).length !== 0 ) {
				set_cookie( 'embed-privacy', JSON.stringify( cookie ), 365 );
			}
			else {
				remove_cookie( 'embed-privacy' );
			}
		}
	}
	
	/**
	 * Check whether to enable an always active provider by default.
	 * 
	 * @since	1.2.0
	 * 
	 * @param {HTMLElement[]} currentOverlays List of current overlays
	 */
	function enableAlwaysActiveProviders( currentOverlays ) {
		const activeElement = document.activeElement;
		var cookie = ( get_cookie( 'embed-privacy' ) ? JSON.parse( get_cookie( 'embed-privacy' ) ) : '' );
		
		if ( ! currentOverlays ) {
			currentOverlays = overlays;
		}
		
		if ( cookie === null || ! Object.keys( cookie ).length ) {
			return;
		}
		
		var providers = Object.keys( cookie );
		
		for ( var i = 0; i < currentOverlays.length; i++ ) {
			var provider = currentOverlays[ i ].parentNode.getAttribute( 'data-embed-provider' );
			
			if ( providers.includes( provider ) ) {
				currentOverlays[ i ].click();
			}
		}
		
		// focus previously active element
		setTimeout( () => activeElement.focus(), 50 );
	}
	
	/**
	 * Get always active providers from cookie.
	 * 
	 * @since	1.4.8
	 * 
	 * @return	{string[]} List of always active providers
	 */
	function getAlwaysActiveProviders() {
		const cookie = ( get_cookie( 'embed-privacy' ) ? JSON.parse( get_cookie( 'embed-privacy' ) ) : '' );
		
		if ( ! cookie ) {
			return [];
		}
		
		return Object.keys( cookie );
	}
	
	/**
	 * Initialize overlay functionality.
	 * 
	 * @since	1.11.0
	 * 
	 * @param {HTMLElement[]} embedOverlays List of embed overlays
	 * @param {HTMLElement[]} overlayLinks  List of embed overlay links
	 * @param {HTMLElement[]} checkboxes    List of embed overlay checkbox inputs
	 * @param {HTMLElement[]} labels        List of embed overlay labels
	 */
	function initOverlays( embedOverlays, overlayLinks, checkboxes, labels ) {
		for ( var i = 0; i < embedOverlays.length; i++ ) {
			embedOverlays[ i ].addEventListener( 'click', function( event ) {
				if ( event.currentTarget.tagName !== 'INPUT' ) {
					overlayClick( event.currentTarget, true );
				}
			} );
			
			const button = embedOverlays[ i ].parentNode.querySelector( '.embed-privacy-enable' );
			
			if ( ! button ) {
				continue;
			}
			
			button.addEventListener( 'click', function( event ) {
				overlayClick( event.currentTarget.parentNode.querySelector( '.embed-privacy-overlay' ) );
				event.currentTarget.parentNode.removeChild( event.currentTarget ); // IE11 doesn't support .remove()
			} );
			button.addEventListener( 'keypress', function( event ) {
				if ( event.code === 'Enter' || event.code === 'Space' ) {
					event.preventDefault(); // prevent space from scrolling the page
					overlayClick( event.currentTarget.parentNode.querySelector( '.embed-privacy-overlay' ) );
					event.currentTarget.parentNode.removeChild( event.currentTarget ); // IE11 doesn't support .remove()
				}
			} );
		}
		
		for ( var i = 0; i < overlayLinks.length; i++ ) {
			overlayLinks[ i ].addEventListener( 'click', function( event ) {
				// don't trigger the overlays click
				event.stopPropagation();
			} );
		}
		
		for ( var i = 0; i < checkboxes.length; i++ ) {
			checkboxes[ i ].addEventListener( 'click', function( event ) {
				// don't trigger the overlays click
				event.stopPropagation();
				
				checkboxActivation( event.currentTarget, [ event.currentTarget.closest( '.embed-privacy-overlay' ) ] );
			} );
		}
		
		for ( var i = 0; i < labels.length; i++ ) {
			labels[ i ].addEventListener( 'click', function( event ) {
				// don't trigger the overlays click
				event.stopPropagation();
			} );
		}
		
		enableAlwaysActiveProviders( embedOverlays );
	}
	
	/**
	 * Initialize a MutationObserver to allow working embeds in dynamic content.
	 * 
	 * @since	1.12.0
	 */
	function initMutationObserver() {
		const observer = new MutationObserver( ( mutations ) => {
			for ( const mutation of mutations ) {
				for ( const newNodes of mutation.addedNodes ) {
					const overlays = newNodes.querySelectorAll( '.embed-privacy-overlay' );
					
					if ( overlays.length ) {
						const checkboxes = newNodes.querySelectorAll( '.embed-privacy-inner .embed-privacy-input' );
						const labels = newNodes.querySelectorAll( '.embed-privacy-inner .embed-privacy-label' );
						const overlayLinks = newNodes.querySelectorAll( '.embed-privacy-overlay a' );
						
						initOverlays( overlays, overlayLinks, checkboxes, labels );
					}
				}
			}
		} );
		
		observer.observe( document.body, {
			childList: true,
		} );
	}
	
	/**
	 * Opting in/out for embed providers.
	 * 
	 * @since	1.2.0
	 */
	function optOut() {
		const optOutContainer = document.querySelector( '.embed-privacy-opt-out' );
		
		if ( ! optOutContainer ) {
			return;
		}
		
		var optOutCheckboxes = optOutContainer.querySelectorAll( '.embed-privacy-opt-out-input' );
		const showAll = optOutContainer.getAttribute( 'data-show-all' ) === '1';
		
		if ( ! optOutCheckboxes ) {
			return;
		}
		
		const alwaysActiveProviders = getAlwaysActiveProviders();
		
		for ( var i = 0; i < optOutCheckboxes.length; i++ ) {
			if ( alwaysActiveProviders.indexOf( optOutCheckboxes[ i ].getAttribute( 'data-embed-provider' ) ) !== -1 ) {
				optOutCheckboxes[ i ].checked = true;
			}
			else if ( ! showAll ) {
				optOutCheckboxes[ i ].parentNode.parentNode.classList.add( 'is-hidden' );
				
				continue;
			}
			
			optOutCheckboxes[ i ].addEventListener( 'click', function( event ) {
				var currentTarget = event.currentTarget;
				
				if ( ! currentTarget ) {
					return;
				}
				
				checkboxActivation( currentTarget );
			} );
		}
		
		const nonHiddenProviders = optOutContainer.querySelectorAll( '.embed-privacy-provider:not(.is-hidden)' );
		
		// remove the container completely if there is no provider to display
		if ( ! showAll && ! nonHiddenProviders.length ) {
			optOutContainer.remove();
		}
	}
	
	/**
	 * Clicking on an overlay.
	 * 
	 * @since	1.2.0
	 * @since	1.12.2 Added parameter isSynthetic
	 * 
	 * @param	{element}	target Target element
	 * @param	{boolean}	isSynthetic Whether the click event is synthetic
	 */
	function overlayClick( target, isSynthetic = false ) {
		var embedContainer = target.parentNode;
		var embedContent = target.nextElementSibling;
		
		embedContainer.classList.remove( 'is-disabled' );
		embedContainer.classList.add( 'is-enabled' );
		// hide the embed overlay
		target.style.display = 'none';
		// get stored content from JavaScript
		var embedObject = JSON.parse( window[ '_' + target.parentNode.getAttribute( 'data-embed-id' ) ] );
		
		embedContent.innerHTML = htmlentities_decode( embedObject.embed );
		
		// reset wrapper inline CSS set in setMinHeight()
		var wrapper = embedContainer.parentNode;
		
		if ( wrapper.classList.contains( 'wp-block-embed__wrapper' ) ) {
			wrapper.style.removeProperty( 'height' );
		}
		
		// get all script tags inside the embed
		var scriptTags = embedContent.querySelectorAll( 'script' );
		
		// insert every script tag inside the embed as a new script
		// to execute it
		for ( var n = 0; n < scriptTags.length; n++ ) {
			var element = document.createElement( 'script' );
			
			if ( scriptTags[ n ].src ) {
				// if script tag has a src attribute
				element.src = scriptTags[ n ].src;
			}
			else {
				// if script tag has content
				element.innerHTML = scriptTags[ n ].innerHTML;
			}
			
			// append it to body
			embedContent.appendChild( element );
		}
		
		// focus first element in container
		if ( ! isSynthetic ) {
			embedContainer.querySelector( '.embed-privacy-content > :first-child' ).focus();
		}
		
		if ( typeof jQuery !== 'undefined' ) {
			const videoShortcode = jQuery( '.wp-video-shortcode' );
			
			if ( videoShortcode.length ) {
				videoShortcode.mediaelementplayer();
			}
		}
		
		if ( embedContainer.getAttribute( 'data-embed-provider' ) === 'instagram' ) {
			const interval = setInterval( () => {
				if ( window.instgrm ) {
					window.instgrm.Embeds.process();
					clearInterval( interval );
				}
			}, 100 );
		}
	}
	
	/**
	 * Calculate min height of the embed wrapper depending of the overlay content.
	 */
	function setMinHeight() {
		for ( var i = 0; i < overlays.length; i++ ) {
			var wrapper = overlays[ i ].parentNode.parentNode;
			
			if ( ! wrapper.classList.contains( 'wp-block-embed__wrapper' ) ) {
				continue;
			}
			
			wrapper.style.removeProperty( 'height' );
			
			if ( wrapper.offsetHeight < overlays[ i ].offsetHeight ) {
				wrapper.style.height = overlays[ i ].offsetHeight + 'px';
			}
		}
	}
} );

/**
 * Get a cookie.
 * 
 * @link	https://stackoverflow.com/a/24103596/3461955
 * 
 * @param	{string}	name The name of the cookie
 */
function get_cookie( name ) {
	var nameEQ = name + '=';
	var ca = document.cookie.split( ';' );
	for ( var i = 0; i < ca.length; i++ ) {
		var c = ca[ i ];
		while ( c.charAt( 0 ) == ' ' ) c = c.substring( 1, c.length );
		if ( c.indexOf( nameEQ ) == 0 ) return c.substring( nameEQ.length, c.length );
	}
	return null;
}

/**
 * Decode a string with HTML entities.
 * 
 * @param	{string}	content The content to decode
 * @return	{string} The decoded content
 */
function htmlentities_decode( content ) {
	var textarea = document.createElement( 'textarea' );
	
	textarea.innerHTML = content;
	
	return textarea.value;
}

/**
 * Remove a cookie.
 * 
 * @link	https://stackoverflow.com/a/24103596/3461955
 * 
 * @param	{string}	name The name of the cookie
 */
function remove_cookie( name ) {
	document.cookie = name + '=; expires=0; path=/';
}

/**
 * Set a cookie.
 * 
 * @link	https://stackoverflow.com/a/24103596/3461955
 * 
 * @param	{string}	name The name of the cookie
 * @param	{string}	value The value of the cookie
 * @param	{number}	days The expiration in days
 */
function set_cookie( name, value, days ) {
	var expires = '';
	if ( days ) {
		var date = new Date();
		date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
		expires = '; expires=' + date.toUTCString();
	}
	document.cookie = name + '=' + ( value || '' ) + expires + '; path=/';
}
