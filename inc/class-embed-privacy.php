<?php
namespace epiphyt\Embed_Privacy;

/**
 * Two click embed main class.
 * 
 * @author		Epiphyt
 * @license		GPL2
 * @package		epiphyt\Embed_Privacy
 * @version		1.0.0
 */
class Embed_Privacy {
	/**
	 * @var		bool Determine if we use the cache
	 */
	private $usecache = false;
	
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
	 * Embed Privacy constructor.
	 */
	public function __construct() {
		// actions
		\add_action( 'init', [ $this, 'load_textdomain' ] );
		\add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		$this->usecache = ! \is_admin();
		
		// filters
		if ( ! $this->usecache ) {
			// set ttl to 0 in admin
			\add_filter( 'oembed_ttl', function( $time ) {
				return 0;
			}, 10, 1 );
		}
		
		\add_filter( 'embed_oembed_html', [ $this, 'replace_embeds' ], 10, 3 );
	}
	
	/**
	 * Embeds are cached in the postmeta database table and need to be removed
	 * whenever the plugin will be enabled or disabled.
	 */
	public function clear_embed_cache() {
		global $wpdb;
		
		// the query to delete cache
		$query = "DELETE FROM		" . $wpdb->get_blog_prefix() . "postmeta
				WHERE				meta_key LIKE '%_oembed_%'";
		
		if ( \is_plugin_active_for_network( 'embed-privacy/embed-privacy.php' ) ) {
			// on networks we need to iterate through every site
			$sites = \get_sites( 99999 );
			
			foreach ( $sites as $site ) {
				\switch_to_blog( $site );
				
				$wpdb->query( $query );
			}
		}
		else {
			$wpdb->query( $query );
		}
	}
	
	/**
	 * Enqueue our assets for the frontend.
	 */
	public function enqueue_assets() {
		$suffix = ( \defined( 'DEBUG_MODE' ) && \DEBUG_MODE ? '' : '.min' );
		$css_file = \EPI_EMBED_PRIVACY_BASE . 'assets/style/embed-privacy' . $suffix . '.css';
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/embed-privacy' . $suffix . '.css';
		
		\wp_enqueue_style( 'embed-privacy', $css_file_url, [], \filemtime( $css_file ) );
		
		$js_file = \EPI_EMBED_PRIVACY_BASE . 'assets/js/embed-privacy' . $suffix . '.js';
		$js_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/embed-privacy' . $suffix . '.js';
		
		\wp_enqueue_script( 'embed-privacy', $js_file_url, [], \filemtime( $js_file ) );
	}
	
	/**
	 * Load the translation files.
	 */
	public function load_textdomain() {
		\load_plugin_textdomain( 'embed-privacy', false, \EPI_EMBED_PRIVACY_BASE . 'languages' );
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
		if ( ! $this->usecache ) return $output;
		
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
		$embed_class = '  embed-' . ( ! empty( $embed_provider ) ? \sanitize_title( $embed_provider ) : 'default' );
		$height = ( ! empty( $args['height'] ) && $args['height'] <= $args['width'] ? 'height: ' . $args['height'] . 'px;' : 'height: 300px;' );
		$width = ( ! empty( $args['width'] ) ? 'width: ' . $args['width'] . 'px;' : '' );
		$markup = '<div class="embed-container' . $embed_class . '">';
		$markup .= '<div class="embed-overlay" style="' . $height . ' ' . $width . '">';
		$markup .= '<h3>';
		
		if ( ! empty( $embed_provider ) ) {
			/* translators: the embed provider */
			$markup .= \sprintf( \esc_html__( 'Click here to display content from %s', 'embed-privacy' ), $embed_provider );
		}
		else {
			$markup .= \esc_html__( 'Click here to display content from external service', 'embed-privacy' );
		}
		
		$markup .= '</h3>';
		$markup .= '</div>';
		$markup .= '<div class="embed-content"><!-- ' . $output . ' --></div>';
		$markup .= '</div>';
		
		return $markup;
	}
}
