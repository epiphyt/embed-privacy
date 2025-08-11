/**
 * Elementor functions for YouTube videos.
 * 
 * @since	1.3.5
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
document.addEventListener( 'DOMContentLoaded', function() {
	replaceYouTubeEmbeds();
	
	/**
	 * Replace YouTube video container with the embed iframe.
	 */
	function replaceYouTubeEmbeds() {
		const youTubeEmbeds = document.querySelectorAll( '.embed-privacy-container.embed-youtube' );
		
		if ( ! youTubeEmbeds ) {
			return;
		}
		
		// check for changed YouTube embeds
		const observeEmbeds = new MutationObserver( function( records, observer ) {
			const embeds = document.querySelectorAll( '.embed-privacy-container.embed-youtube .embed-privacy-content > .elementor-element' );
			
			if ( ! embeds.length ) {
				return;
			}
			
			for ( let i = 0; i < embeds.length; i++ ) {
				// get the video element to replace later
				const embedVideo = embeds[ i ].querySelector( 'div.elementor-video' );
				
				if ( ! embedVideo ) {
					continue;
				}
				
				// get the video settings
				const settings = JSON.parse( embeds[ i ].getAttribute( 'data-settings' ) );
				
				if ( ! settings ) {
					continue;
				}
				
				const iframe = document.createElement( 'iframe' );
				const divider = settings.youtube_url.indexOf( '?' ) !== -1 ? '&' : '?';
				let url = settings.youtube_url.replace( 'watch?v=', 'embed/' ) + divider;
				
				if ( settings.youtube_url.indexOf( 'youtu.be' ) !== -1 ) {
					const urlObject = new URL( settings.youtube_url );
					
					url = 'https://www.youtube-nocookie.com/embed' + urlObject.pathname + '?';
				}
				
				const properties = {
					autoplay: settings.autoplay ? 1 : 0,
					cc_load_policy: settings.cc_load_policy ? 1 : 0,
					controls: settings.controls ? 1 : 0,
					end: settings.end,
					playsinline: settings.play_on_mobile ? 1 : 0,
					rel: settings.rel ? 1 : 0,
					start: settings.start,
				};
				
				for ( const property in properties ) {
					if ( properties[ property ] === undefined ) {
						continue;
					}
					
					url += property + '=' + properties[ property ] + '&';
					
					if ( property === 'autoplay' && properties[ property ] === 1 && ! properties.playsinline ) {
						url += 'playsinline=1&';
					}
				}
				
				// build iframe to replace embed video div
				iframe.src = url.replace( /&$/, '' );
				iframe.allowFullscreen = 1;
				iframe.class = 'elementor-video';
				iframe.style.height = '100%';
				iframe.style.width = '100%';
				
				// replace the video element with the iframe
				embedVideo.parentNode.replaceChild( iframe, embedVideo );
			}
		} );
		
		observeEmbeds.observe( document, {
			childList: true,
			subtree: true,
		} );
	}
} );
