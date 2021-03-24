<?php
namespace epiphyt\Embed_Privacy;
use DOMXPath;
use Elementor\Plugin;
use DOMDocument;
use WP_Post;
use function __;
use function add_action;
use function add_filter;
use function add_shortcode;
use function addslashes;
use function apply_filters;
use function array_keys;
use function array_merge;
use function checked;
use function defined;
use function dirname;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_x;
use function esc_url;
use function file_exists;
use function filemtime;
use function function_exists;
use function get_attached_file;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_post_thumbnail_id;
use function get_posts;
use function get_sites;
use function get_the_ID;
use function get_the_post_thumbnail_url;
use function has_shortcode;
use function home_url;
use function htmlentities;
use function in_array;
use function is_a;
use function is_admin;
use function is_numeric;
use function is_plugin_active;
use function is_plugin_active_for_network;
use function is_string;
use function json_decode;
use function libxml_use_internal_errors;
use function load_plugin_textdomain;
use function mb_convert_encoding;
use function md5;
use function microtime;
use function plugin_basename;
use function plugin_dir_path;
use function plugin_dir_url;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function register_activation_hook;
use function register_deactivation_hook;
use function register_post_type;
use function sanitize_text_field;
use function sanitize_title;
use function shortcode_atts;
use function sprintf;
use function str_replace;
use function stripos;
use function strpos;
use function strtotime;
use function trim;
use function wp_date;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_generate_uuid4;
use function wp_get_attachment_url;
use function wp_get_theme;
use function wp_json_encode;
use function wp_kses;
use function wp_localize_script;
use function wp_parse_url;
use function wp_register_script;
use function wp_register_style;
use function wp_unslash;
use const DEBUG_MODE;
use const EPI_EMBED_PRIVACY_BASE;
use const EPI_EMBED_PRIVACY_URL;
use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;
use const PHP_EOL;
use const PHP_URL_HOST;

/**
 * Two click embed main class.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Embed_Privacy {
	/**
	 * @deprecated	1.2.0
	 * @since		1.1.0
	 */
	const IFRAME_REGEX = '/<iframe(.*?)src="([^"]+)"([^>]*)>((?!<\/iframe).)*<\/iframe>/ms';
	
	/**
	 * @since	1.3.0
	 * @var		array An array of embed providers
	 */
	public $embeds = [];
	
	/**
	 * @since	1.3.0
	 * @var		bool Whether the current request has any embed processed by Embed Privacy
	 */
	public $has_embed = false;
	
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
	private $usecache;
	
	/**
	 * @deprecated	1.2.0
	 * @var			array The supported media providers
	 */
	public $embed_providers = [
		'.amazon.' => 'Amazon Kindle',
		'.amzn.' => 'Amazon Kindle',
		'a.co' => 'Amazon Kindle',
		'z.cn' => 'Amazon Kindle',
		'animoto.com' => 'Animoto',
		'cloudup.com' => 'Cloudup',
		'crowdsignal.com' => 'Crowdsignal',
		'dailymotion.com' => 'DailyMotion',
		'facebook.com' => 'Facebook',
		'flickr.com' => 'Flickr',
		'funnyordie.com' => 'Funny Or Die',
		'imgur.com' => 'Imgur',
		'instagram.com' => 'Instagram',
		'issuu.com' => 'Issuu',
		'kickstarter.com' => 'Kickstarter',
		'meetup.com' => 'Meetup',
		'mixcloud.com' => 'Mixcloud',
		'photobucket.com' => 'Photobucket',
		'poll.fm' => 'Crowdsignal',
		'polldaddy.com' => 'Crowdsignal',
		'reddit.com' => 'Reddit',
		'reverbnation.com' => 'ReverbNation',
		'scribd.com' => 'Scribd',
		'sketchfab.com' => 'Sketchfab',
		'slideshare.net' => 'SlideShare',
		'smugmug.com' => 'SmugMug',
		'soundcloud.com' => 'SoundCloud',
		'speakerdeck.com' => 'Speaker Deck',
		'spotify.com' => 'Spotify',
		'survey.fm' => 'Crowdsignal',
		'tiktok.com' => 'TikTok',
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
		// assign variables
		$this->usecache = ! is_admin();
	}
	
	/**
	 * Initialize the class.
	 * 
	 * @since	1.2.0
	 */
	public function init() {
		// actions
		add_action( 'init', [ $this, 'load_textdomain' ], 0 );
		add_action( 'init', [ $this, 'set_post_type' ], 5 );
		add_action( 'wp', [ $this, 'get_elementor_filters' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// filters
		if ( ! $this->usecache ) {
			// set ttl to 0 in admin
			add_filter( 'oembed_ttl', '__return_zero' );
		}
		
		add_filter( 'do_shortcode_tag', [ $this, 'replace_embeds' ] );
		add_filter( 'embed_oembed_html', [ $this, 'replace_embeds_oembed' ], 10, 3 );
		add_filter( 'embed_privacy_widget_output', [ $this, 'replace_embeds' ], 10, 2 );
		add_filter( 'et_builder_get_oembed', [ $this, 'replace_embeds_divi' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'replace_embeds' ] );
		
		add_shortcode( 'embed_privacy_opt_out', [ $this, 'shortcode_opt_out' ] );
		
		register_activation_hook( $this->plugin_file, [ $this, 'clear_embed_cache' ] );
		register_deactivation_hook( $this->plugin_file, [ $this, 'clear_embed_cache' ] );
		
		Admin::get_instance()->init();
		Fields::get_instance()->init();
		Migration::get_instance()->init();
	}
	
	/**
	 * Embeds are cached in the postmeta database table and need to be removed
	 * whenever the plugin will be enabled or disabled.
	 */
	public function clear_embed_cache() {
		global $wpdb;
		
		if ( is_plugin_active_for_network( 'embed-privacy/embed-privacy.php' ) ) {
			// on networks we need to iterate through every site
			$sites = get_sites( [ 'number' => 99999 ] );
			
			foreach ( $sites as $site ) {
				$wpdb->query(
					"DELETE FROM	" . $wpdb->get_blog_prefix( $site->blog_id ) . "postmeta
					WHERE			meta_key LIKE '%_oembed_%'"
				 );
			}
		}
		else {
			$wpdb->query(
				"DELETE FROM	$wpdb->postmeta
				WHERE			meta_key LIKE '%_oembed_%'"
			);
		}
	}
	
	/**
	 * Enqueue our assets for the frontend.
	 */
	public function enqueue_assets() {
		$suffix = ( defined( 'WP_DEBUG' ) && WP_DEBUG ? '' : '.min' );
		$css_file = EPI_EMBED_PRIVACY_BASE . 'assets/style/embed-privacy' . $suffix . '.css';
		$css_file_url = EPI_EMBED_PRIVACY_URL . 'assets/style/embed-privacy' . $suffix . '.css';
		
		wp_register_style( 'embed-privacy', $css_file_url, [], filemtime( $css_file ) );
		
		if ( ! $this->is_amp() ) {
			$js_file = EPI_EMBED_PRIVACY_BASE . 'assets/js/embed-privacy' . $suffix . '.js';
			$js_file_url = EPI_EMBED_PRIVACY_URL . 'assets/js/embed-privacy' . $suffix . '.js';
			
			wp_register_script( 'embed-privacy', $js_file_url, [], filemtime( $js_file ) );
		}
		
		// Astra is too greedy at its CSS selectors
		// see https://github.com/epiphyt/embed-privacy/issues/33
		if ( wp_get_theme()->get( 'Name' ) === 'Astra' || wp_get_theme()->get( 'Template' ) === 'Astra' ) {
			$css_file = EPI_EMBED_PRIVACY_BASE . 'assets/style/astra' . $suffix . '.css';
			$css_file_url = EPI_EMBED_PRIVACY_URL . 'assets/style/astra' . $suffix . '.css';
			
			wp_enqueue_style( 'embed-privacy-astra', $css_file_url, [], filemtime( $css_file ) );
		}
		
		global $post;
		
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'embed_privacy_opt_out' ) ) {
			$this->print_assets();
		}
	}
	
	/**
	 * Get the Embed Privacy cookie.
	 * 
	 * @return	mixed The content of the cookie
	 */
	private function get_cookie() {
		if ( empty( $_COOKIE['embed-privacy'] ) ) {
			return '';
		}
		
		return json_decode( sanitize_text_field( wp_unslash( $_COOKIE['embed-privacy'] ) ) );
	}
	
	/**
	 * Get filters for Elementor.
	 * 
	 * @since	1.3.0
	 */
	public function get_elementor_filters() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		
		if (
			! is_plugin_active( 'elementor/elementor.php' )
			|| ! get_the_ID()
			|| ! Plugin::$instance->db->is_built_with_elementor( get_the_ID() )
		) {
			return;
		}
		
		// doesn't currently run with YouTube
		// see https://github.com/elementor/elementor/issues/14276
		add_filter( 'oembed_result', [ $this, 'replace_embeds_oembed' ], 10, 3 );
	}
	
	/**
	 * Get a specific type of embeds.
	 * 
	 * @since	1.3.0
	 * 
	 * @param	string	$type The embed type
	 * @return	array A list of embeds
	 */
	public function get_embeds( $type = 'all' ) {
		if ( ! empty( $this->embeds ) && isset( $this->embeds[ $type ] ) ) {
			return $this->embeds[ $type ];
		}
		
		if ( $type === 'all' && isset( $this->embeds['custom'] ) && isset( $this->embeds['oembed'] ) ) {
			$this->embeds[ $type ] = array_merge( $this->embeds['custom'], $this->embeds['oembed'] );
			
			return $this->embeds[ $type ];
		} 
		
		switch ( $type ) {
			case 'custom':
				$custom_providers = (array) get_posts( [
					'meta_query' => [
						'relation' => 'OR',
						[
							'compare' => 'NOT EXISTS',
							'key' => 'is_system',
							'value' => 'yes',
						],
						[
							'compare' => '!=',
							'key' => 'is_system',
							'value' => 'yes',
						],
					],
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
				] );
				$google_provider = (array) get_posts( [
					'meta_key' => 'is_system',
					'meta_value' => 'yes',
					'name' => 'google-maps',
					'post_type' => 'epi_embed',
				] );
				$this->embeds['custom'] = array_merge( $custom_providers, $google_provider );
				
				return $this->embeds['custom'];
			case 'oembed':
				$embed_providers = get_posts( [
					'meta_key' => 'is_system',
					'meta_value' => 'yes',
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
				] );
				$this->embeds['oembed'] = $embed_providers;
				
				return $this->embeds['oembed'];
			case 'all':
			default:
				$this->embeds['all'] = (array) get_posts( [
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
				] );
				
				return $this->embeds['all'];
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
	 * Transform a tweet into a local one.
	 * 
	 * @since	1.3.0
	 * 
	 * @param	string	$html Embed code
	 * @return	string Local embed
	 */
	private function get_local_tweet( $html ) {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding(
				'<html>' . $html . '</html>',
				'HTML-ENTITIES',
				'UTF-8'
			),
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		
		// remove script tag
		foreach ( $dom->getElementsByTagName( 'script' ) as $script ) {
			$script->parentNode->removeChild( $script );
		}
		
		$xpath = new DOMXPath( $dom );
		
		// get text node, which represents the author name
		// and give it a span with class
		foreach ( $xpath->query( '//blockquote/text()' ) as $node ) {
			$author_node = $dom->createElement( 'span', $node->nodeValue );
			$author_node->setAttribute( 'class', 'embed-privacy-author-meta' );
			$node->parentNode->replaceChild( $author_node, $node );
		}
		
		// wrap author name by a meta div
		foreach ( $dom->getElementsByTagName( 'span' ) as $node ) {
			if ( $node->getAttribute( 'class' ) !== 'embed-privacy-author-meta' ) {
				continue;
			}
			
			// create meta div
			$parent_node = $dom->createElement( 'div' );
			$parent_node->setAttribute( 'class', 'embed-privacy-tweet-meta' );
			// append created div to blockquote
			$node->parentNode->appendChild( $parent_node );
			// move author meta inside meta div
			$parent_node->appendChild( $node );
		}
		
		foreach ( $dom->getElementsByTagName( 'a' ) as $link ) {
			if ( ! preg_match( '/https?:\/\/twitter.com\/([^\/]+)\/status\/(\d+)/', $link->getAttribute( 'href' ) ) ) {
				continue;
			}
			
			// modify date in link to tweet
			$l10n_date = wp_date( get_option( 'date_format' ), strtotime( $link->nodeValue ) );
			
			if ( is_string( $l10n_date ) ) {
				$link->nodeValue = $l10n_date;
			}
			
			// move link inside meta div
			if ( is_a( $parent_node, 'DOMElement' ) ) {
				$parent_node->appendChild( $link );
			}
		}
		
		$content = $dom->saveHTML( $dom->documentElement );
		
		return str_replace( [ '<html>', '</html>' ], [ '<div class="embed-privacy-local-tweet">', '</div>' ], $content );
	}
	
	/**
	 * Output a complete template of the overlay.
	 * 
	 * @since	1.1.0
	 * 
	 * @param	string	$embed_provider The embed provider
	 * @param	string	$embed_provider_lowercase The embed provider without spaces and in lowercase
	 * @param	string	$output The output before replacing it
	 * @param	array	$args Additional arguments
	 * @return	string The overlay template
	 */
	public function get_output_template( $embed_provider, $embed_provider_lowercase, $output, $args = [] ) {
		if ( ! empty( $args['post_id'] ) ) {
			$embed_post = get_post( $args['post_id'] );
			
			// if provider is disabled, to nothing
			if ( get_post_meta( $embed_post->ID, 'is_disabled', true ) === 'yes' ) {
				return $output;
			}
		}
		else {
			$embed_post = null;
		}
		
		$embed_provider_lowercase = sanitize_title( $embed_provider_lowercase );
		$embed_class = 'embed-' . ( ! empty( $embed_provider_lowercase ) ? $embed_provider_lowercase : 'default' );
		$embed_classes = $embed_class;
		
		$background_path = '';
		$background_url = '';
		$logo_path = '';
		$logo_url = '';
		
		if ( ! empty( $args['align'] ) ) {
			$embed_classes .= ' align' . $args['align'];
		}
		
		// display embed provider background image and logo
		if ( $embed_post ) {
			$background_image_id = get_post_meta( $embed_post->ID, 'background_image', true );
			$thumbnail_id = get_post_thumbnail_id( $embed_post );
		}
		else {
			$background_image_id = null;
			$thumbnail_id = null;
		}
		
		
		if ( $background_image_id ) {
			$background_path = get_attached_file( $background_image_id );
			$background_url = wp_get_attachment_url( $background_image_id );
		}
		
		if ( $thumbnail_id ) {
			$logo_path = get_attached_file( $thumbnail_id );
			$logo_url = get_the_post_thumbnail_url( $args['post_id'] );
		}
		else if ( file_exists( plugin_dir_path( $this->plugin_file ) . 'assets/images/embed-' . $embed_provider_lowercase . '.png' ) ) {
			$logo_path = plugin_dir_path( $this->plugin_file ) . 'assets/images/embed-' . $embed_provider_lowercase . '.png';
			$logo_url = plugin_dir_url( $this->plugin_file ) . 'assets/images/embed-' . $embed_provider_lowercase . '.png';
		}
		
		/**
		 * Filter the path to the background image.
		 * 
		 * @param	string	$background_path The default background path
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$background_path = apply_filters( "embed_privacy_background_path_{$embed_provider_lowercase}", $background_path, $embed_provider_lowercase );
		
		/**
		 * Filter the URL to the background image.
		 * 
		 * @param	string	$background_url The default background URL
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$background_url = apply_filters( "embed_privacy_background_url_{$embed_provider_lowercase}", $background_url, $embed_provider_lowercase );
		
		/**
		 * Filter the path to the logo.
		 * 
		 * @param	string	$logo_path The default background path
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$logo_path = apply_filters( "embed_privacy_logo_path_{$embed_provider_lowercase}", $logo_path, $embed_provider_lowercase );
		
		/**
		 * Filter the URL to the logo.
		 * 
		 * @param	string	$logo_url The default background URL
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$logo_url = apply_filters( "embed_privacy_logo_url_{$embed_provider_lowercase}", $logo_url, $embed_provider_lowercase );
		
		$embed_md5 = md5( $output . wp_generate_uuid4() );
		$markup = '<div class="embed-privacy-container is-disabled ' . esc_attr( $embed_classes ) . '" id="oembed_' . esc_attr( $embed_md5 ) . '" data-embed-provider="' . esc_attr( $embed_provider_lowercase ) . '">';
		$markup .= '<div class="embed-privacy-overlay">';
		$markup .= '<div class="embed-privacy-inner">';
		$markup .= ( file_exists( $logo_path ) ? '<div class="embed-privacy-logo"></div>' : '' );
		$content = '<p>';
		
		if ( ! empty( $embed_provider ) ) {
			if ( $embed_post ) {
				$allowed_tags = [
					'a' => [
						'href',
						'target',
					],
				];
				$content .= $embed_post->post_content;
				$privacy_policy = get_post_meta( $embed_post->ID, 'privacy_policy_url', true );
				
				if ( $privacy_policy ) {
					/* translators: 1: the embed provider, 2: opening <a> tag to the privacy policy, 3: closing </a> */
					$content .= '<br>' . sprintf( wp_kses( __( 'Learn more in %1$s’s %2$sprivacy policy%3$s.', 'embed-privacy' ), $allowed_tags ), esc_html( $embed_provider ), '<a href="' . esc_url( $privacy_policy ) . '" target="_blank">', '</a>' );
				}
			}
			else {
				/* translators: the embed provider */
				$content .= sprintf( esc_html__( 'Click here to display content from %s', 'embed-privacy' ), esc_html( $embed_provider ) );
			}
		}
		else {
			$content .= esc_html__( 'Click here to display content from an external service.', 'embed-privacy' );
		}
		
		$content .= '</p>';
		
		$checkbox_id = 'embed-privacy-store-' . $embed_provider_lowercase . '-' . $embed_md5;
		
		if ( $embed_provider_lowercase !== 'default' ) {
			/* translators: the embed provider */
			$content .= '<p><input id="' . esc_attr( $checkbox_id ) . '" type="checkbox" value="1" class="embed-privacy-input" data-embed-provider="' . esc_attr( $embed_provider_lowercase ) . '"><label for="' . esc_attr( $checkbox_id ) . '" class="embed-privacy-label" data-embed-provider="' . esc_attr( $embed_provider_lowercase ) . '">' . sprintf( esc_html__( 'Always display content from %s', 'embed-privacy' ), esc_html( $embed_provider ) ) . '</label></p>';
		}
		
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
		$markup .= '<div class="embed-privacy-content"><script>var _oembed_' . $embed_md5 . ' = \'' . addslashes( wp_json_encode( [ 'embed' => htmlentities( $output ) ] ) ) . '\';</script></div>';
		
		$markup .= '<style>' . PHP_EOL;
		
		// display only if file exists
		if ( file_exists( $background_path ) ) {
			$version = filemtime( $background_path );
			$markup .= '.' . $embed_class . ' {
	background-image: url(' . $background_url . '?v=' . $version . ');
}' . PHP_EOL;
		}
		
		// display only if file exists
		if ( file_exists( $logo_path ) ) {
			$version = filemtime( $logo_path );
			$markup .= '.' . $embed_class . ' .embed-privacy-logo {
	background-image: url(' . $logo_url . '?v=' . $version . ');
}' . PHP_EOL;
		}
		
		$markup .= '</style>';
		$markup .= '</div>';
		
		/**
		 * Filter the complete markup of the embed.
		 * 
		 * @param	string	$markup The markup
		 * @param	string	$embed_provider The embed provider of this embed
		 */
		$markup = apply_filters( 'embed_privacy_markup', $markup, $embed_provider );
		
		return $markup;
	}
	
	/**
	 * Get a single overlay for all matching embeds.
	 * 
	 * @since	1.2.0
	 * 
	 * @param	string	$content The original content
	 * @param	string	$embed_provider The embed provider
	 * @param	string	$embed_provider_lowercase The embed provider without spaces and in lowercase
	 * @param	array	$args Additional arguments
	 * @return	string The updated content
	 */
	public function get_single_overlay( $content, $embed_provider, $embed_provider_lowercase, $args ) {
		if ( empty( $content ) ) {
			return $content;
		}
		
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding(
				// adding root element, see https://github.com/epiphyt/embed-privacy/issues/22
				'<html>' . $content . '</html>',
				'HTML-ENTITIES',
				'UTF-8'
			),
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		$is_empty_provider = ( empty( $embed_provider ) );
		$template_dom = new DOMDocument();
		
		if ( $is_empty_provider ) {
			$providers = $this->get_embeds();
		}
		$parsed_url = wp_parse_url( home_url() );
		$domain = $host = $parsed_url['host'];		
		if ((!filter_var($host,FILTER_VALIDATE_IP)) && ($host !== 'localhost')) {
		    // neither IP address or "localhost"

		    $domain_array = explode(".", str_replace('www.', '', $host));
		    $count = count($domain_array);
		    if( $count>=3 && strlen($domain_array[$count-2])==2 ) {
			// SLD (example.co.uk)
			$domain = implode('.', array_splice($domain_array, $count-3,3));
		    } else if( $count>=2 ) {
			// TLD (example.com)
			$domain = implode('.', array_splice($domain_array, $count-2,2));
		    }
		}
	
		
		foreach ( [ 'embed', 'iframe', 'object' ] as $tag ) {
			$replacements = [];
			
			if ( $tag !== 'object' ) {
				$attribute = 'src';
			}
			else {
				$attribute = 'data';
			}
			
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				
				
				// ignore embeds from the same (sub-)domain - also if we are on a (sub-)domain too.				
				if ( strpos( $element->getAttribute( $attribute ), $domain ) !== false ) {
					continue;
				}
				
				// if  $parsed_url['host'] itself is a subdomain
				
				if ( ! empty ( $args['regex'] ) && ! preg_match( $args['regex'], $element->getAttribute( $attribute ) ) ) {
					continue;
				}
				
				if ( $is_empty_provider ) {
					$parsed_url = wp_parse_url( $element->getAttribute( $attribute ) );
					
					// embeds with relative paths have no host
					// and they are local by definition, so do nothing
					// see https://github.com/epiphyt/embed-privacy/issues/27
					if ( empty( $parsed_url['host'] ) ) {
						return $content;
					}
					
					$embed_provider = $parsed_url['host'];
					$embed_provider_lowercase = sanitize_title( $parsed_url['host'] );
					
					// check URL for available provider
					foreach ( $providers as $provider ) {
						$regex = trim( get_post_meta( $provider->ID, 'regex_default', true ), '/' );
						
						if ( ! empty( $regex ) ) {
							$regex = '/' . $regex . '/';
						}
						else {
							continue;
						}
						
						if ( preg_match( $regex, $element->getAttribute( $attribute ) ) ) {
							return $content;
						}
					}
				}
				
				// get overlay template as DOM element
				$template_dom->loadHTML(
					mb_convert_encoding(
						$this->get_output_template( $embed_provider, $embed_provider_lowercase, $dom->saveHTML( $element ), $args ),
						'HTML-ENTITIES',
						'UTF-8'
					),
					LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
				);
				$overlay = null;
				
				foreach ( $template_dom->getElementsByTagName( 'div' ) as $div ) {
					if ( stripos( $div->getAttribute( 'class' ), 'embed-privacy-container' ) !== false ) {
						$overlay = $div;
						break;
					}
				}
				
				// store the elements to replace (see regressive loop down below)
				$replacements[] = [
					'element' => $element,
					'replace' => $dom->importNode( $overlay, true ),
				];
				
				// reset embed provider name
				if ( $is_empty_provider ) {
					$embed_provider = '';
					$embed_provider_lowercase = '';
				}
			}
			
			if ( ! empty( $replacements ) ) {
				$this->has_embed = true;
				$elements = $dom->getElementsByTagName( $tag );
				$i = $elements->length - 1;
				
				// use regressive loop for replaceChild()
				// see: https://www.php.net/manual/en/domnode.replacechild.php#50500
				while ( $i > -1 ) {
					$element = $elements->item( $i );
					
					foreach ( $replacements as $replacement ) {
						if ( $replacement['element'] === $element ) {
							$element->parentNode->replaceChild( $replacement['replace'], $replacement['element'] );
						}
					}
					
					$i--;
				}
				
				$content = $dom->saveHTML( $dom->documentElement );
			}
		}
		
		libxml_use_internal_errors( false );
		
		// embeds for other elements need to be handled manually
		// make sure to test before if the regex matches
		// see: https://github.com/epiphyt/embed-privacy/issues/26
		if ( empty( $replacements ) && ! empty( $args['regex'] ) && ! $is_empty_provider ) {
			$content = preg_replace( $args['regex'], $this->get_output_template( $embed_provider, $embed_provider_lowercase, $content, $args ), $content );
		}
		
		// remove root element, see https://github.com/epiphyt/embed-privacy/issues/22
		return str_replace( [ '<html>', '</html>' ], '', $content );
	}
	
	/**
	 * Check if a post contains an embed.
	 * 
	 * @since	1.3.0
	 * 
	 * @param	\WP_Post|int|null	$post A post object, post ID or null
	 * @return	bool True if a post contains an embed, false otherwise
	 */
	public function has_embed( $post = null ) {
		if ( $post === null ) {
			global $post;
		}
		
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		
		/**
		 * Allow overwriting the return value of has_embed().
		 * If set to anything other than null, this value will be returned.
		 * 
		 * @param	null	$has_embed The default value
		 */
		$has_embed = apply_filters( 'embed_privacy_has_embed', null );
		
		if ( $has_embed !== null ) {
			return $has_embed;
		}
		
		if ( ! $post || ! $post instanceof WP_Post ) {
			return false;
		}
		
		if ( $this->has_embed ) {
			return true;
		}
		
		$embed_providers = $this->get_embeds();
		
		// check post content
		foreach ( $embed_providers as $provider ) {
			$regex = trim( get_post_meta( $provider->ID, 'regex_default', true ), '/' );
			
			if ( empty( $regex ) ) {
				continue;
			}
			
			// get overlay for this provider
			if ( preg_match( '/' . $regex . '/', $post->post_content ) ) {
				return true;
			}
		}
		
		return false;
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
	 * Check if a provider is always active.
	 * 
	 * @since	1.1.0
	 * 
	 * @param	string		$provider The embed provider in lowercase
	 * @return	bool True if provider is always active, false otherwise
	 */
	public function is_always_active_provider( $provider ) {
		$javascript_detection = get_option( 'embed_privacy_javascript_detection' );
		
		if ( $javascript_detection ) {
			return false;
		}
		
		$cookie = $this->get_cookie();
		
		if ( isset( $cookie->{$provider} ) && $cookie->{$provider} === true ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Load the translation files.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'embed-privacy', false, dirname( plugin_basename( $this->plugin_file ) ) . '/languages' );
	}
	
	/**
	 * Handle printing assets.
	 * 
	 * @since	1.3.0
	 */
	public function print_assets() {
		wp_enqueue_script( 'embed-privacy' );
		wp_enqueue_style( 'embed-privacy' );
		wp_localize_script( 'embed-privacy', 'embedPrivacy', [
			'javascriptDetection' => get_option( 'embed_privacy_javascript_detection' ),
		] );
	}
	
	/**
	 * Replace embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @since	1.2.0 Changed behaviour of the method
	 * @since	1.3.0 Added optional parameter $widget_id
	 * 
	 * @param	string	$content The original content
	 * @param	int		$widget_id The widget's ID, if any
	 * @return	string The updated content
	 */
	public function replace_embeds( $content, $widget_id = 0 ) {
		// do nothing in admin
		if ( ! $this->usecache ) {
			return $content;
		}
		
		// widgets already contain the embed code
		if ( ! $this->has_embed && $widget_id && strpos( $content, '<div class="embed-privacy-overlay">' ) !== false ) {
			$this->has_embed = true;
		}
		
		// get all non-system embed providers
		$embed_providers = $this->get_embeds( 'custom' );
		
		// get embed provider name
		foreach ( $embed_providers as $provider ) {
			$regex = trim( get_post_meta( $provider->ID, 'regex_default', true ), '/' );
			
			if ( ! empty( $regex ) ) {
				$regex = '/' . $regex . '/';
			}
			
			// get overlay for this provider
			if ( ! empty( $regex ) && preg_match( $regex, $content ) ) {
				$this->has_embed = true;
				$args['regex'] = $regex;
				$args['post_id'] = $provider->ID;
				$embed_provider = $provider->post_title;
				$embed_provider_lowercase = $provider->post_name;
				$content = $this->get_single_overlay( $content, $embed_provider, $embed_provider_lowercase, $args );
			}
		}
		
		// get default external content
		$content = $this->get_single_overlay( $content, '', '', [] );
		
		if ( $this->has_embed ) {
			$this->print_assets();
		}
		
		return $content;
	}
	
	/**
	 * Replace oembed embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @since	1.2.0
	 * @version	1.0.0
	 * 
	 * @param	string	$output The original output
	 * @param	string	$url The URL to the embed
	 * @param	array	$args Additional arguments of the embed
	 * @return	string The updated embed code
	 */
	public function replace_embeds_oembed( $output, $url, $args ) {
		// do nothing in admin
		if ( ! $this->usecache ) {
			return $output;
		}
		
		// check the current domain
		// see: https://github.com/epiphyt/embed-privacy/issues/24
		if ( strpos( $url, wp_parse_url( home_url(), PHP_URL_HOST ) ) !== false ) {
			return $output;
		}
		
		$embed_provider = '';
		$embed_provider_lowercase = '';
		$embed_providers = $this->get_embeds( 'oembed' );
		
		// get embed provider name
		foreach ( $embed_providers as $provider ) {
			$regex = get_post_meta( $provider->ID, 'regex_default', true );
			$regex = '/' . trim( $regex, '/' ) . '/';
			
			// save name of provider and stop loop
			if ( $regex !== '//' && preg_match( $regex, $url ) ) {
				$this->has_embed = true;
				$args['post_id'] = $provider->ID;
				$embed_provider = $provider->post_title;
				$embed_provider_lowercase = $provider->post_name;
				break;
			}
		}
		
		if ( $embed_provider_lowercase === 'youtube' ) {
			// replace youtube.com to youtube-nocookie.com
			$output = str_replace( 'youtube.com', 'youtube-nocookie.com', $output );
		}
		else if ( $embed_provider_lowercase === 'twitter' && get_option( 'embed_privacy_local_tweets' ) ) {
			// check for local tweets
			return $this->get_local_tweet( $output );
		}
		
		// check if cookie is set
		if ( $embed_provider_lowercase !== 'default' && $this->is_always_active_provider( $embed_provider_lowercase ) ) {
			return $output;
		}
		
		// add two click to markup
		return $this->get_output_template( $embed_provider, $embed_provider_lowercase, $output, $args );
	}
	
	/**
	 * Replace embeds in Divi Builder.
	 * 
	 * @since	1.2.0
	 * 
	 * @param	string	$item_embed The original output
	 * @param	string	$url The URL of the embed
	 * @return	string The updated embed code
	 */
	public function replace_embeds_divi( $item_embed, $url ) {
		return $this->replace_embeds_oembed( $item_embed, $url, [] );
	}
	
	/**
	 * Replace Google Maps iframes.
	 * 
	 * @deprecated	1.2.0
	 * @since		1.1.0
	 * 
	 * @param	string	$content The post content
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
	
	/**
	 * Register post type.
	 * 
	 * @since	1.2.0
	 */
	public function set_post_type() {
		register_post_type(
			'epi_embed',
			[
				'label' => __( 'Embeds', 'embed-privacy' ),
				'description' => __( 'Embeds from Embed Privacy', 'embed-privacy' ),
				'supports' => [
					'custom-fields',
					'editor',
					'revisions',
					'thumbnail',
					'title',
				],
				'hierarchical' => false,
				'public' => false,
				'menu_icon' => 'dashicons-format-video',
				'show_in_admin_bar' => false,
				'show_in_menu' => false,
				'show_in_nav_menus' => false,
				'show_in_rest' => false,
				'show_ui' => true,
				'can_export' => true,
				'has_archive' => false,
				'exclude_from_search' => true,
				'publicly_queryable' => false,
				'rewrite' => [
					'with_front' => false,
					'pages' => false,
				],
			]
		);
	}
	
	/**
	 * Display an Opt-out shortcode.
	 * 
	 * @since	1.2.0
	 * 
	 * @param	array	$attributes Shortcode attributes
	 * @return	string The shortcode output
	 */
	public function shortcode_opt_out( $attributes ) {
		$attributes = shortcode_atts( [
			'headline' => __( 'Embed providers', 'embed-privacy' ),
			'show_all' => 0,
			'subline' => __( 'Enable or disable embed providers globally. By enabling a provider, its embedded content will be displayed directly on every page without asking you anymore.', 'embed-privacy' ),
		], $attributes );
		$cookie = $this->get_cookie();
		$enabled_providers = array_keys( (array) $cookie );
		$embed_providers = $this->get_embeds();
		
		if ( $attributes['show_all'] ) {
			$providers = $embed_providers;
		}
		else {
			if ( empty( $cookie ) ) {
				return '';
			}
			
			$providers = [];
			
			foreach ( $embed_providers as $embed_provider ) {
				if ( in_array( $embed_provider->post_name, $enabled_providers, true ) ) {
					$providers[] = $embed_provider;
				}
			}
		}
		
		if ( empty( $providers ) ) {
			return '';
		}
		
		$headline = '<h3>' . esc_html( $attributes['headline'] ) . '</h3>' . PHP_EOL;
		
		/**
		 * Filter the opt-out headline.
		 * 
		 * @param	string	$headline Current headline HTML
		 * @param	array	$attributes Shortcode attributes
		 */
		$headline = apply_filters( 'embed_privacy_opt_out_headline', $headline, $attributes );
		
		/**
		 * Filter the opt-out subline.
		 * 
		 * @param	string	$subline Current subline HTML
		 * @param	array	$attributes Shortcode attributes
		 */
		$subline = apply_filters( 'embed_privacy_opt_out_subline', '<p>' . esc_html( $attributes['subline'] ) . '</p>' . PHP_EOL, $attributes );
		
		$output = '<div class="embed-privacy-opt-out">' . PHP_EOL . $headline . $subline;
		$output .= '<p>' . PHP_EOL;
		
		foreach ( $providers as $provider ) {
			if ( $attributes['show_all'] ) {
				$is_checked = in_array( $provider->post_name, $enabled_providers, true );
			}
			else {
				$is_checked = true;
			}
			
			$microtime = str_replace( '.', '', microtime( true ) );
			$output .= '<input type="checkbox" id="embed-privacy-provider-' . esc_attr( $provider->post_name ) . '-' . $microtime . '" ' . checked( $is_checked, true, false ) . ' class="embed-privacy-opt-out-input ' . ( $is_checked ? 'is-enabled' : 'is-disabled' ) . '" data-embed-provider="' . esc_attr( $provider->post_name ) . '">';
			$output .= '<label class="embed-privacy-opt-out-label" for="embed-privacy-provider-' . esc_attr( $provider->post_name ) . '-' . $microtime . '" data-embed-provider="' . esc_attr( $provider->post_name ) . '">';
			$enable_disable = '<span class="embed-privacy-provider-is-enabled">' . esc_html_x( 'Disable', 'complete string: Disable <embed name>', 'embed-privacy' ) . '</span><span class="embed-privacy-provider-is-disabled">' . esc_html_x( 'Enable', 'complete string: Disable <embed name>', 'embed-privacy' ) . '</span>';
			/* translators: 1: Enable/Disable, 2: embed provider title */
			$output .= wp_kses( sprintf( __( '%1$s %2$s', 'embed-privacy' ), $enable_disable, esc_html( $provider->post_title ) ), [ 'span' => [ 'class' => true ] ] );
			$output .= '</label><br>' . PHP_EOL;
		}
		
		$output .= '</p>' . PHP_EOL . '</div>' . PHP_EOL;
		
		return $output;
	}
}
