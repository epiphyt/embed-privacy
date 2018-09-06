<?php
namespace epiphyt\Two_Click_Embed;

/**
 * Two click embed main class.
 * 
 * @author		Epiphyt
 * @license		GPL2
 * @package		epiphyt\Two_Click_Embed
 * @version		0.1
 */
class Two_Click_Embed {
	/**
	 * @var		array The supported media providers
	 */
	public $embed_providers = [
		'animoto.com' => 'Animoto',
		'cloudup.com' => 'Cloudup',
		'collegehumor.com' => 'CollegeHumor',
		'dailymotion.com' => 'DailyMotion',
		'facebook.com' => 'Facebook',
		'flickr.com' => 'Flickr',
		'funnyordie.com' => 'Funny Or Die',
		'hulu.com' => 'Hulu',
		'imgur.com' => 'Imgur',
		'instagram.com' => 'Instagram',
		'issuu.com' => 'Issuu',
		'kickstarter.com' => 'Kickstarter',
		'meetup.com' => 'Meetup',
		'mixcloud.com' => 'Mixcloud',
		'photobucket.com' => 'Photobucket',
		'photobucket.com' => 'Photobucket',
		'polldaddy.com' => 'Polldaddy.com',
		'reddit.com' => 'Reddit',
		'reverbnation.com' => 'ReverbNation',
		'scribd.com' => 'Scribd',
		'sketchfab.com' => 'Sketchfab',
		'slideshare.com' => 'SlideShare',
		'smugmug.com' => 'SmugMug',
		'soundcloud.com' => 'SoundCloud',
		'speakerdeck.com' => 'Speaker Deck',
		'spotify.com' => 'Spotify',
		'ted.com' => 'TED',
		'tumblr.com' => 'Tumblr',
		'twitter.com' => 'Twitter',
		'videopress.com' => 'VideoPres',
		'vimeo.com' => 'Vimeo',
		'wordpress.org/plugins' => 'WordPress.org',
		'wordpress.tv' => 'WordPress.tv',
		'youtu.be' => 'YouTube',
		'youtube.com' => 'YouTube',
	];
	
	/**
	 * Two Click Embed constructor.
	 */
	public function __construct() {
		// actions
		\add_action( 'init', [ $this, 'load_textdomain' ] );
		\add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// filters
		\add_filter( 'oembed_result', [ $this, 'replace_embeds' ], 10, 3 );
	}
	
	/**
	 * Enqueue our assets for the frontend.
	 */
	public function enqueue_assets() {
		$suffix = ( \defined( 'DEBUG_MODE' ) && \DEBUG_MODE ? '' : '.min' );
		$css_file = \EPI_TWO_CLICK_EMBED_BASE . 'assets/style/two-click-embed' . $suffix . '.css';
		$css_file_url = \EPI_TWO_CLICK_EMBED_URL . 'assets/style/two-click-embed' . $suffix . '.css';
		
		\wp_enqueue_style( 'two-click-embed', $css_file_url, [], \filemtime( $css_file ) );
		
		$js_file = \EPI_TWO_CLICK_EMBED_BASE . 'assets/js/two-click-embed' . $suffix . '.js';
		$js_file_url = \EPI_TWO_CLICK_EMBED_URL . 'assets/js/two-click-embed' . $suffix . '.js';
		
		\wp_enqueue_script( 'two-click_embed', $js_file_url, [], \filemtime( $js_file ) );
	}
	
	public function load_textdomain() {
		\load_plugin_textdomain( 'two-click-embed', false, \EPI_TWO_CLICK_EMBED_BASE . 'languages' );
	}
	
	/**
	 * Replace embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @param	string		$output
	 * @param	string		$url
	 * @param	array		$args
	 * @return	string
	 */
	public function replace_embeds( $output, $url, $args ) {
		// don't do anything in admin
		if ( \is_admin() ) return $output;
		
		$embed_provider = '';
		
		// get embed provider name
		foreach ( $this->embed_providers as $url_part => $name ) {
			// save name of provider and stop loop
			if ( \strpos( $url, $url_part ) !== false ) {
				$embed_provider = $name;
				break;
			}
		}
		
		// add two click to markup
		$height = ( ! empty( $args['height'] ) && $args['height'] <= $args['width'] ? 'height: ' . $args['height'] . 'px;' : 'height: 300px;' );
		$width = ( ! empty( $args['width'] ) ? 'width: ' . $args['width'] . 'px;' : '' );
		$markup = '<div class="embed-container">';
		$markup .= '<div class="embed-overlay" style="' . $height . $width . '">';
		$markup .= '<h3>';
		
		if ( ! empty( $embed_provider ) ) {
			/* translators: the embed provider */
			$markup .= \sprintf( \esc_html__( 'Click here to display content from %s', 'two-click-embed' ), $embed_provider );
		}
		else {
			$markup .= \esc_html__( 'Click here to display content from external service', 'two-click-embed' );
		}
		
		$markup .= '</h3>';
		$markup .= '</div>';
		$markup .= '<div class="embed-content"><!-- ' . $output . ' --></div>';
		$markup .= '</div>';
		
		return $markup;
	}
}
