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
	 * Get the YouTube video ID from a URL.
	 *
	 * @param	{string} url The YouTube URL
	 * @return	{string|null} The video ID or null
	 */
	function getYouTubeVideoId( url ) {
		const regex = /^(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:(?:watch)?\?(?:.*&)?vi?=|(?:embed|v|vi|user|shorts)\/))([^?&"'>]+)/;
		const match = url.match( regex );

		return match ? match[ 1 ] : null;
	}

	/**
	 * Build an iframe element for a YouTube embed URL.
	 *
	 * @param	{string} url The embed base URL
	 * @param	{Object} properties The player properties as URL parameters
	 * @return	{HTMLIFrameElement} The iframe element
	 */
	function getYouTubeIframe( url, properties ) {
		url += url.indexOf( '?' ) !== -1 ? '&' : '?';

		for ( const property in properties ) {
			if ( properties[ property ] === undefined || properties[ property ] === '' ) {
				continue;
			}

			url += property + '=' + properties[ property ] + '&';

			if ( property === 'autoplay' && properties[ property ] === 1 && ! properties.playsinline ) {
				url += 'playsinline=1&';
			}
		}

		const iframe = document.createElement( 'iframe' );
		iframe.src = url.replace( /&$/, '' );
		iframe.allowFullscreen = 1;
		iframe.class = 'elementor-video';
		iframe.style.height = '100%';
		iframe.style.width = '100%';

		return iframe;
	}

	/**
	 * Replace YouTube video containers with the embed iframe once an overlay
	 * has been clicked and its original content got revealed.
	 */
	function replaceYouTubeEmbeds() {
		const youTubeEmbeds = document.querySelectorAll( '.embed-privacy-container.embed-youtube' );

		if ( ! youTubeEmbeds.length ) {
			return;
		}

		// check for changed YouTube embeds
		const observeEmbeds = new MutationObserver( function() {
			replaceClassicEmbeds();
			replaceAtomicEmbeds();
		} );

		observeEmbeds.observe( document, {
			childList: true,
			subtree: true,
		} );
	}

	/**
	 * Replace classic Elementor video widgets.
	 */
	function replaceClassicEmbeds() {
		const embeds = document.querySelectorAll( '.embed-privacy-container.embed-youtube .embed-privacy-content > .elementor-element' );

		for ( let i = 0; i < embeds.length; i++ ) {
			// get the video element to replace later
			const embedVideo = embeds[ i ].querySelector( 'div.elementor-video' );

			if ( ! embedVideo ) {
				continue;
			}

			// get the video settings
			let settings;

			try {
				settings = JSON.parse( embeds[ i ].getAttribute( 'data-settings' ) );
			}
			catch ( exception ) {
				continue;
			}

			if ( ! settings || ! settings.youtube_url ) {
				continue;
			}

			let url = settings.youtube_url.replace( 'watch?v=', 'embed/' );

			if ( settings.youtube_url.indexOf( 'youtu.be' ) !== -1 ) {
				const urlObject = new URL( settings.youtube_url );

				url = 'https://www.youtube-nocookie.com/embed' + urlObject.pathname;
			}

			const iframe = getYouTubeIframe( url, {
				autoplay: settings.autoplay ? 1 : 0,
				cc_load_policy: settings.cc_load_policy ? 1 : 0,
				controls: settings.controls ? 1 : 0,
				end: settings.end,
				playsinline: settings.play_on_mobile ? 1 : 0,
				rel: settings.rel ? 1 : 0,
				start: settings.start,
			} );

			// replace the video element with the iframe
			embedVideo.parentNode.replaceChild( iframe, embedVideo );
		}
	}

	/**
	 * Replace atomic YouTube widgets introduced in Elementor 4.0.
	 * Their container is rendered empty and the player is built by Elementor's
	 * own handler on page load, which never runs for a hidden overlay.
	 */
	function replaceAtomicEmbeds() {
		const embeds = document.querySelectorAll( '.embed-privacy-container.embed-youtube .embed-privacy-content div[data-e-type="e-youtube"]' );

		for ( let i = 0; i < embeds.length; i++ ) {
			// skip already replaced embeds
			if ( embeds[ i ].querySelector( 'iframe' ) ) {
				continue;
			}

			// get the video settings
			let settings;

			try {
				settings = JSON.parse( embeds[ i ].getAttribute( 'data-settings' ) );
			}
			catch ( exception ) {
				continue;
			}

			if ( ! settings || ! settings.source ) {
				continue;
			}

			const videoId = getYouTubeVideoId( settings.source );

			if ( ! videoId ) {
				continue;
			}

			const host = settings.privacy ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com';
			const properties = {
				autoplay: settings.autoplay ? 1 : 0,
				cc_load_policy: settings.cc_load_policy ? 1 : 0,
				controls: settings.controls ? 1 : 0,
				end: settings.end,
				mute: settings.mute ? 1 : 0,
				rel: settings.rel ? 0 : 1,
				start: settings.start,
			};

			if ( settings.loop ) {
				properties.loop = 1;
				properties.playlist = videoId;
			}

			const iframe = getYouTubeIframe( host + '/embed/' + videoId, properties );

			// the container is rendered empty, so append the iframe
			embeds[ i ].appendChild( iframe );
		}
	}
} );
