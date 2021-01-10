<?php
namespace epiphyt\Embed_Privacy;
use DOMDocument;
use function __;
use function add_action;
use function add_filter;
use function addslashes;
use function apply_filters;
use function defined;
use function dirname;
use function esc_attr;
use function esc_html;
use function esc_html__;
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
use function get_the_post_thumbnail_url;
use function home_url;
use function htmlentities;
use function is_admin;
use function is_plugin_active_for_network;
use function json_decode;
use function libxml_use_internal_errors;
use function load_plugin_textdomain;
use function mb_convert_encoding;
use function md5;
use function plugin_basename;
use function preg_match;
use function preg_match_all;
use function register_post_type;
use function sanitize_text_field;
use function sanitize_title;
use function sprintf;
use function str_replace;
use function stripos;
use function strpos;
use function switch_to_blog;
use function trim;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_generate_uuid4;
use function wp_get_attachment_url;
use function wp_json_encode;
use function wp_kses;
use function wp_localize_script;
use function wp_parse_url;
use function wp_unslash;
use const DEBUG_MODE;
use const EPI_EMBED_PRIVACY_BASE;
use const EPI_EMBED_PRIVACY_URL;
use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;
use const PHP_EOL;

/**
 * Two click embed main class.
 * 
 * @author		Epiphyt
 * @license		GPL2
 * @package		epiphyt\Embed_Privacy
 */
class Embed_Privacy {
	/**
	 * @deprecated	1.2.0
	 * @since		1.1.0
	 */
	const IFRAME_REGEX = '/<iframe(.*?)src="([^"]+)"([^>]*)>((?!<\/iframe).)*<\/iframe>/ms';
	
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
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// filters
		if ( ! $this->usecache ) {
			// set ttl to 0 in admin
			add_filter( 'oembed_ttl', '__return_zero' );
		}
		
		add_filter( 'do_shortcode_tag', [ $this, 'replace_embeds' ] );
		add_filter( 'embed_oembed_html', [ $this, 'replace_embeds_oembed' ], 10, 3 );
		add_filter( 'embed_privacy_widget_output', [ $this, 'replace_embeds' ] );
		add_filter( 'et_builder_get_oembed', [ $this, 'replace_embeds_divi' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'replace_embeds' ] );
		
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
		
		// the query to delete cache
		$query = "DELETE FROM	$wpdb->postmeta
				WHERE			meta_key LIKE '%_oembed_%'";
		
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
			wp_localize_script( 'embed-privacy', 'embedPrivacy', [
				'javascriptDetection' => get_option( 'embed_privacy_javascript_detection' ),
			] );
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
	 * @version	2.0.0
	 * 
	 * @param	string	$content The original content
	 * @return	string The updated content
	 */
	public function replace_embeds( $content ) {
		// do nothing in admin
		if ( ! $this->usecache ) {
			return;
		}
		
		// get all non-system embed providers
		$embed_providers = get_posts( [
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
			'post_type' => 'epi_embed',
		] );
		
		// get embed provider name
		foreach ( $embed_providers as $provider ) {
			$regex = trim( get_post_meta( $provider->ID, 'regex_default', true ), '/' );
			
			if ( ! empty( $regex ) ) {
				$regex = '/' . $regex . '/';
			}
			
			// get overlay for this provider
			if ( ! empty( $regex ) && preg_match( $regex, $content ) ) {
				$args['regex'] = $regex;
				$args['post_id'] = $provider->ID;
				$embed_provider = $provider->post_title;
				$embed_provider_lowercase = $provider->post_name;
				$content = $this->get_single_overlay( $content, $embed_provider, $embed_provider_lowercase, $args );
			}
		}
		
		// get default external content
		$content = $this->get_single_overlay( $content, '', '', [] );
		
		return $content;
	}
	
	/**
	 * Get a single overlay for all matching embeds.
	 * 
	 * @param	string	$content The original content
	 * @param	string	$embed_provider The embed provider
	 * @param	string	$embed_provider_lowercase The embed provider without spaces and in lowercase
	 * @param	array	$args Additional arguments
	 * @return	string The updated content
	 */
	public function get_single_overlay( $content, $embed_provider, $embed_provider_lowercase, $args ) {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding(
				$content,
				'HTML-ENTITIES',
				'UTF-8'
			),
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		$template_dom = new DOMDocument();
		
		foreach ( [ 'embed', 'iframe', 'object' ] as $tag ) {
			$replacements = [];
			
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				$is_empty_provider = ( empty( $embed_provider ) );
				$parsed_url = wp_parse_url( home_url() );
				
				// ignore embeds from the same (sub-)domain
				if ( strpos( $element->getAttribute( 'src' ), $parsed_url['host'] ) !== false ) {
					continue;
				}
				
				if ( ! empty ( $args['regex'] ) && ! preg_match( $args['regex'], $element->getAttribute( 'src' ) ) ) {
					continue;
				}
				
				if ( $is_empty_provider ) {
					$parsed_url = wp_parse_url( $element->getAttribute( 'src' ) );
					$embed_provider = $parsed_url['host'];
					$embed_provider_lowercase = sanitize_title( $parsed_url['host'] );
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
			
			$output = $dom->saveHTML( $dom->documentElement );
		}
		
		libxml_use_internal_errors( false );
		
		return $output;
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
		
		$embed_provider = '';
		$embed_provider_lowercase = '';
		$embed_providers = get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'numberposts' => -1,
			'post_type' => 'epi_embed',
		] );
		
		// get embed provider name
		foreach ( $embed_providers as $provider ) {
			$regex = get_post_meta( $provider->ID, 'regex_default', true );
			$regex = '/' . trim( $regex, '/' ) . '/';
			
			// save name of provider and stop loop
			if ( $regex !== '//' && preg_match( $regex, $url ) ) {
				$args['post_id'] = $provider->ID;
				$embed_provider = $provider->post_title;
				$embed_provider_lowercase = $provider->post_name;
				break;
			}
		}
		
		// replace youtube.com to youtube-nocookie.com
		if ( $embed_provider === 'youtube' ) {
			$output = str_replace( 'youtube.com', 'youtube-nocookie.com', $output );
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
	 * @param	string		$item_embed The original output
	 * @param	string		$url The URL of the embed
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
		$markup = '<div class="embed-privacy-container ' . esc_attr( $embed_classes ) . '" id="oembed_' . esc_attr( $embed_md5 ) . '" data-embed-provider="' . esc_attr( $embed_provider_lowercase ) . '">';
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
					$content .= '<br>' . sprintf( wp_kses( __( 'Learn more in %1$s’s %2$sprivacy policy%3$s.' ), $allowed_tags ), esc_html( $embed_provider ), '<a href="' . esc_url( $privacy_policy ) . '" target="_blank">', '</a>' );
				}
			}
			else {
				/* translators: the embed provider */
				$content .= sprintf( esc_html__( 'Click here to display content from %s', 'embed-privacy' ), esc_html( $embed_provider ) );
			}
		}
		else {
			$content .= esc_html__( 'Click here to display content from external service.', 'embed-privacy' );
		}
		
		$content .= '</p>';
		
		$checkbox_id = 'embed-privacy-store-' . $embed_provider_lowercase . '-' . $embed_md5;
		
		if ( $embed_provider_lowercase !== 'default' ) {
			/* translators: the embed provider */
			$content .= '<p><label for="' . esc_attr( $checkbox_id ) . '" class="embed-privacy-label" data-embed-provider="' . esc_attr( $embed_provider_lowercase ) . '"><input id="' . esc_attr( $checkbox_id ) . '" type="checkbox" value="1"> ' . sprintf( esc_html__( 'Always display content from %s', 'embed-privacy' ), esc_html( $embed_provider ) ) . '</label></p>';
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
				'public' => true,
				'menu_icon' => 'dashicons-format-video',
				'show_in_admin_bar' => false,
				'show_in_menu' => true,
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
}
