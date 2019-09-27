<?php
namespace epiphyt\Embed_Privacy;
use function add_action;
use function add_filter;
use function addslashes;
use function apply_filters;
use function defined;
use function dirname;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function file_exists;
use function filemtime;
use function get_sites;
use function is_admin;
use function is_plugin_active_for_network;
use function json_decode;
use function load_plugin_textdomain;
use function md5;
use function plugin_basename;
use function plugin_dir_path;
use function plugin_dir_url;
use function preg_match_all;
use function sanitize_text_field;
use function sanitize_title;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function switch_to_blog;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;
use const DEBUG_MODE;
use const EPI_EMBED_PRIVACY_BASE;
use const EPI_EMBED_PRIVACY_URL;

/**
 * Two click embed main class.
 * 
 * @author		Epiphyt
 * @license		GPL2
 * @package		epiphyt\Embed_Privacy
 * @version		1.1.0
 */
class Embed_Privacy {
	/**
	 * @since	1.1.0
	 */
	const IFRAME_REGEX = '/<iframe([^>])+>([^<])*<\/iframe>/';
	
	/**
	 * @var		\epiphyt\Embed_Privacy\Embed_Privacy
	 */
	public static $instance;
	
	/**
	 * @var		string The full path to the main plugin file
	 */
	public $plugin_file = '';
	
	/**
	 * @var		bool Determine if we use the cache
	 */
	private $usecache = false;
	
	/**
	 * @var		array The supported media providers
	 */
	public $embed_providers = [
		'.amazon.' => 'Amazon Kindle',
		'.amzn.' => 'Amazon Kindle',
		'a.co' => 'Amazon Kindle',
		'z.cn' => 'Amazon Kindle',
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
		'slideshare.net' => 'SlideShare',
		'smugmug.com' => 'SmugMug',
		'soundcloud.com' => 'SoundCloud',
		'speakerdeck.com' => 'Speaker Deck',
		'spotify.com' => 'Spotify',
		'ted.com' => 'TED',
		'tumblr.com' => 'Tumblr',
		'twitter.com' => 'Twitter',
		'videopress.com' => 'VideoPress',
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
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// assign variables
		$this->usecache = ! is_admin();
		
		// filters
		if ( ! $this->usecache ) {
			// set ttl to 0 in admin
			add_filter( 'oembed_ttl', function( $time ) {
				return 0;
			}, 10, 1 );
		}
		
		add_filter( 'do_shortcode_tag', [ $this, 'replace_google_maps' ] );
		add_filter( 'embed_oembed_html', [ $this, 'replace_embeds' ], 10, 3 );
		add_filter( 'embed_privacy_widget_output', [ $this, 'replace_google_maps' ] );
		add_filter( 'the_content', [ $this, 'replace_google_maps' ] );
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
		
		if ( is_plugin_active_for_network( 'embed-privacy/embed-privacy.php' ) ) {
			// on networks we need to iterate through every site
			$sites = get_sites( 99999 );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site );
				
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
		$suffix = ( defined( 'DEBUG_MODE' ) && DEBUG_MODE ? '' : '.min' );
		$css_file = EPI_EMBED_PRIVACY_BASE . 'assets/style/embed-privacy' . $suffix . '.css';
		$css_file_url = EPI_EMBED_PRIVACY_URL . 'assets/style/embed-privacy' . $suffix . '.css';
		
		wp_enqueue_style( 'embed-privacy', $css_file_url, [], filemtime( $css_file ) );
		
		if ( ! $this->is_amp() ) {
			$js_file = EPI_EMBED_PRIVACY_BASE . 'assets/js/embed-privacy' . $suffix . '.js';
			$js_file_url = EPI_EMBED_PRIVACY_URL . 'assets/js/embed-privacy' . $suffix . '.js';
			
			wp_enqueue_script( 'embed-privacy', $js_file_url, [], filemtime( $js_file ) );
		}
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @since	1.1.0
	 * 
	 * @return	\epiphyt\Embed_Privacy\Embed_Privacy The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Determine whether this is an AMP response.
	 * Note that this must only be called after the parse_query action.
	 * 
	 * @return	bool True if the current page is an AMP page, false otherwise
	 */
	private function is_amp() {
		return function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
	}
	
	/**
	 * Load the translation files.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'embed-privacy', false, dirname( plugin_basename( $this->plugin_file ) ) . '/languages' );
	}
	
	/**
	 * Replace embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @version	1.0.1
	 * 
	 * @param	string		$output The original output
	 * @param	string		$url The URL to the embed
	 * @param	array		$args Additional arguments of the embed
	 * @return	string The updated embed code
	 */
	public function replace_embeds( $output, $url, $args ) {
		// don't do anything in admin
		if ( ! $this->usecache ) return $output;
		
		$embed_provider = '';
		$embed_provider_lowercase = '';
		
		// get embed provider name
		foreach ( $this->embed_providers as $url_part => $name ) {
			// save name of provider and stop loop
			if ( strpos( $url, $url_part ) !== false ) {
				$embed_provider = $name;
				$embed_provider_lowercase = str_replace( [ ' ', '.' ], '-', strtolower( $name ) );
				break;
			}
		}
		
		// replace youtube.com to youtube-nocookie.com
		$output = str_replace( 'youtube.com', 'youtube-nocookie.com', $output );
		
		// check if cookie is set
		if ( $this->is_always_active_provider( $embed_provider_lowercase ) ) {
			return $output;
		}
		
		// add two click to markup
		return $this->get_output_template( $embed_provider, $embed_provider_lowercase, $output, $args );
	}
	
	/**
	 * Replace Google Maps iframes.
	 * 
	 * @since	1.1.0
	 * 
	 * @param	string		$content The post content
	 * @return	string The post content
	 */
	public function replace_google_maps( $content ) {
		preg_match_all( self::IFRAME_REGEX, $content, $matches );
		
		if ( empty( $matches ) || empty( $matches[0] ) ) {
			return $content;
		}
		
		$embed_provider = 'Google Maps';
		$embed_provider_lowercase = 'google-maps';
		
		// check if cookie is set
		if ( $this->is_always_active_provider( $embed_provider_lowercase ) ) {
			return $content;
		}
		
		foreach ( $matches[0] as $match ) {
			if ( strpos( $match, 'google.com/maps' ) === false ) {
				continue;
			}
			
			$overlay_output = $this->get_output_template( $embed_provider, $embed_provider_lowercase, $match );
			$content = str_replace( $match, $overlay_output, $content );
		}
		
		return $content;
	}
	
	/**
	 * Get the Embed Privacy cookie.
	 * 
	 * @return array|mixed|object|string The content of the cookie
	 */
	private function get_cookie() {
		if ( empty( $_COOKIE['embed-privacy'] ) ) {
			return '';
		}
		
		$object = json_decode( sanitize_text_field( wp_unslash( $_COOKIE['embed-privacy'] ) ) );
		
		return $object;
	}
	
	/**
	 * Output a complete template of the overlay.
	 * 
	 * @since	1.1.0
	 * 
	 * @param	string		$embed_provider The embed provider
	 * @param	string		$embed_provider_lowercase The embed provider without spaces and in lowercase
	 * @param	string		$output The output before replacing it
	 * @param	array		$args Additional arguments
	 * @return	string The overlay template
	 */
	public function get_output_template( $embed_provider, $embed_provider_lowercase, $output, $args = [] ) {
		// add two click to markup
		$embed_class = ' embed-' . ( ! empty( $embed_provider ) ? sanitize_title( $embed_provider ) : 'default' );
		
		if ( ! empty( $args['align'] ) ) {
			$embed_class .= ' align' . $args['align'];
		}
		
		$embed_md5 = md5( $output );
		$width = ( ! empty( $args['width'] ) ? 'width: ' . $args['width'] . 'px;' : '' );
		$markup = '<div class="embed-privacy-container' . esc_attr( $embed_class ) . '" id="oembed_' . esc_attr( $embed_md5 ) . '">';
		$markup .= '<div class="embed-privacy-overlay" style="' . esc_attr( $width ) . '">';
		$markup .= '<div class="embed-privacy-inner">';
		$markup .= '<div class="embed-privacy-logo"></div>';
		$content = '<p>';
		
		if ( ! empty( $embed_provider ) ) {
			/* translators: the embed provider */
			$content .= sprintf( esc_html__( 'Click here to display content from %s', 'embed-privacy' ), esc_html( $embed_provider ) );
		}
		else {
			$content .= esc_html__( 'Click here to display content from external service', 'embed-privacy' );
		}
		
		$content .= '</p>';
		
		$checkbox_id = 'embed-privacy-store-' . $embed_provider_lowercase . '-' . $embed_md5;
		/* translators: the embed provider */
		$content .= '<p><label for="' . esc_attr( $checkbox_id ) . '" class="embed-privacy-label" data-embed-provider="' . esc_attr( $embed_provider_lowercase ) . '"><input id="' . esc_attr( $checkbox_id ) . '" type="checkbox" value="1"> ' . sprintf( esc_html__( 'Always display content from %s', 'embed-privacy' ), esc_html( $embed_provider ) ) . '</label></p>';
		
		/**
		 * Filter the content of the embed overlay.
		 * 
		 * @param	string		$content The content
		 * @param	string		$embed_provider The embed provider of this embed
		 */
		$content = apply_filters( 'embed_privacy_content', $content, $embed_provider );
		
		$markup .= $content;
		$markup .= '</div>';
		$markup .= '</div>';
		$markup .= '<div class="embed-privacy-content"><script>var _oembed_' . $embed_md5 . ' = \'' . addslashes( wp_json_encode( [ 'embed' => $output ] ) ) . '\';</script></div>';
		$markup .= '</div>';
		
		// display embed provider logo
		$background_path = plugin_dir_path( $this->plugin_file ) . 'assets/images/embed-' . $embed_provider_lowercase . '.png';
		$background_url = plugin_dir_url( $this->plugin_file ) . 'assets/images/embed-' . $embed_provider_lowercase . '.png';
		
		// display only if file exists
		if ( file_exists( $background_path ) ) {
			$version = filemtime( $background_path );
			$markup .= '
			<style>
				.embed-' . $embed_provider_lowercase . ' .embed-privacy-logo {
					background-image: url(' . $background_url . '?v=' . $version . ');
				}
			</style>
			';
		}
		
		return $markup;
	}
	
	/**
	 * Check if a provider is always active.
	 * 
	 * @since	1.1.0
	 * 
	 * @param	string		$provider The embed provider in lowercase
	 * @return	bool True if provider is always active, false otherwise
	 */
	public function is_always_active_provider( $provider ) {
		$cookie = $this->get_cookie();
		
		if ( isset( $cookie->{$provider} ) && $cookie->{$provider} === true ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Set the plugin file.
	 * 
	 * @since	1.1.0
	 * 
	 * @param	string	$file The path to the file
	 */
	public function set_plugin_file( $file ) {
		if ( file_exists( $file ) ) {
			$this->plugin_file = $file;
		}
	}
}
