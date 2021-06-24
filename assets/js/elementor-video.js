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
				let url = settings.youtube_url.replace( 'watch?v=', 'embed/' ) + '?';
				
				if ( settings.controls ) {
					url += 'controls=1&';
				}
				
				if ( settings.autoplay ) {
					url += 'autoplay=1&playsinline=1&';
				}
				
				// build iframe to replace embed video div
				iframe.src = url;
				iframe.allowFullscreen = 1;
				iframe.class = 'elementor-video';
				iframe.style.maxHeight = '332px';
				iframe.style.maxWidth = '100%';
				iframe.style.height = 360;
				iframe.style.width = 640;
				
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
