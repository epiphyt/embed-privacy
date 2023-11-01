<?php
namespace epiphyt\Embed_Privacy;

use Automattic\Jetpack\Assets;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Elementor\Plugin;
use Jetpack;
use WP_Post;

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
	 * @since	1.4.0
	 * @var		mixed The cookie content or any error message from json_decode()
	 */
	private $cookie;
	
	/**
	 * @since	1.3.5
	 * @var		array Replacements that already have taken place.
	 */
	private $did_replacements = [];
	
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
	 * @since	1.6.0
	 * @var		string[] List of ignored shortcodes
	 */
	private $ignored_shortcodes = [
		'embed_privacy_opt_out',
		'grw',
	];
	
	/**
	 * @since	1.4.8
	 * @var		bool Whether the current request has printed Embed Privacy assets.
	 */
	private $is_printed = false;
	
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
		$this->usecache = ! \is_admin();
	}
	
	/**
	 * Initialize the class.
	 * 
	 * @since	1.2.0
	 */
	public function init() {
		// actions
		\add_action( 'init', [ $this, 'load_textdomain' ], 0 );
		\add_action( 'init', [ $this, 'register_assets' ] );
		\add_action( 'init', [ $this, 'set_post_type' ], 5 );
		\add_action( 'save_post_epi_embed', [ $this, 'preserve_backslashes' ] );
		\add_action( 'wp_enqueue_scripts', [ $this, 'deregister_assets' ], 100 );
		
		// filters
		if ( ! $this->usecache ) {
			// set ttl to 0 in admin
			\add_filter( 'oembed_ttl', '__return_zero' );
		}
		
		\add_filter( 'acf_the_content', [ $this, 'replace_embeds' ] );
		\add_filter( 'do_shortcode_tag', [ $this, 'replace_embeds' ], 10, 2 );
		\add_filter( 'do_shortcode_tag', [ $this, 'replace_maps_marker' ], 10, 2 );
		\add_filter( 'embed_oembed_html', [ $this, 'replace_embeds_oembed' ], 10, 3 );
		\add_filter( 'embed_privacy_widget_output', [ $this, 'replace_embeds' ] );
		\add_filter( 'et_builder_get_oembed', [ $this, 'replace_embeds_divi' ], 10, 2 );
		\add_filter( 'pll_get_post_types', [ $this, 'register_polylang_post_type' ], 10, 2 );
		\add_filter( 'the_content', [ $this, 'replace_embeds' ] );
		\add_filter( 'wp_video_shortcode', [ $this, 'replace_video_shortcode' ], 10, 2 );
		\add_shortcode( 'embed_privacy_opt_out', [ $this, 'shortcode_opt_out' ] );
		\register_activation_hook( $this->plugin_file, [ $this, 'clear_embed_cache' ] );
		\register_deactivation_hook( $this->plugin_file, [ $this, 'clear_embed_cache' ] );
		
		Admin::get_instance()->init();
		Fields::get_instance()->init();
		Migration::get_instance()->init();
		Thumbnails::get_instance()->init();
	}
	
	/**
	 * Embeds are cached in the postmeta database table and need to be removed
	 * whenever the plugin will be enabled or disabled.
	 */
	public function clear_embed_cache() {
		global $wpdb;
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( \is_plugin_active_for_network( 'embed-privacy/embed-privacy.php' ) ) {
			// on networks we need to iterate through every site
			$sites = \get_sites( [
				'fields' => 'ids',
				'number' => 99999,
			] );
			
			foreach ( $sites as $blog_id ) {
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						"DELETE FROM	$wpdb->get_blog_prefix( $blog_id )postmeta
						WHERE			meta_key LIKE %s",
						// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						[ '%_oembed_%' ]
					)
				);
			}
		}
		else {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM	$wpdb->postmeta
					WHERE			meta_key LIKE %s",
					[ '%_oembed_%' ]
				)
			);
		}
		//phpcs:enable
	}
	
	/**
	 * Deregister assets.
	 * 
	 * @since	1.4.6
	 */
	public function deregister_assets() {
		\wp_deregister_script( 'jetpack-facebook-embed' );
	}
	
	/**
	 * Enqueue our assets for the frontend.
	 * 
	 * @deprecated	1.4.4 Use Embed_Privacy::print_assets() instead
	 */
	public function enqueue_assets() { }
	
	/**
	 * Get the Embed Privacy cookie.
	 * 
	 * @return	mixed The content of the cookie
	 */
	public function get_cookie() {
		if ( empty( $_COOKIE['embed-privacy'] ) ) {
			return '';
		}
		
		if ( ! empty( $this->cookie ) ) {
			return $this->cookie;
		}
		
		$this->cookie = \json_decode( \sanitize_text_field( \wp_unslash( $_COOKIE['embed-privacy'] ) ) );
		
		return $this->cookie;
	}
	
	/**
	 * Get filters for Elementor.
	 * 
	 * @since		1.3.0
	 * @deprecated	1.3.5
	 * @noinspection PhpUnused
	 */
	public function get_elementor_filters() {
		if ( ! $this->is_elementor() ) {
			return;
		}
		
		// doesn't currently run with YouTube
		// see https://github.com/elementor/elementor/issues/14276
		\add_filter( 'oembed_result', [ $this, 'replace_embeds' ], 10, 3 );
	}
	
	/**
	 * Get an overlay for Elementor YouTube videos.
	 * 
	 * @since	1.3.5
	 * 
	 * @param	string	$content The content
	 * @return	string The content with an embed overlay (if needed)
	 */
	private function get_elementor_youtube_overlay( $content ) {
		$embed_provider = $this->get_embed_by_name( 'youtube' );
		$replacements = [];
		
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		\libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $content . '</html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		$template_dom = new DOMDocument();
		
		foreach ( $dom->getElementsByTagName( 'div' ) as $element ) {
			if ( \strpos( $element->getAttribute( 'data-settings' ), 'youtube_url' ) === false ) {
				continue;
			}
			
			$settings = \json_decode( $element->getAttribute( 'data-settings' ) );
			$args = [];
			
			if ( ! empty( $settings->youtube_url ) ) {
				$args['embed_url'] = $settings->youtube_url;
			}
			
			// get overlay template as DOM element
			$template_dom->loadHTML(
				'<html><meta charset="utf-8">' . $this->get_output_template( $embed_provider->post_title, $embed_provider->post_name, $dom->saveHTML( $element ), $args ) . '</html>',
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
			$overlay = null;
			
			foreach ( $template_dom->getElementsByTagName( 'div' ) as $div ) {
				if ( \stripos( $div->getAttribute( 'class' ), 'embed-privacy-container' ) !== false ) {
					$overlay = $div;
					break;
				}
			}
			
			// store the elements to replace (see regressive loop down below)
			if ( $overlay instanceof DOMNode || $overlay instanceof DOMElement ) {
				$replacements[] = [
					'element' => $element,
					'replace' => $dom->importNode( $overlay, true ),
				];
			}
		}
		
		if ( ! empty( $replacements ) ) {
			$this->did_replacements = \array_merge( $this->did_replacements, $replacements );
			$this->has_embed = true;
			$elements = $dom->getElementsByTagName( 'div' );
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
			
			$content = \str_replace( [ '<html><meta charset="utf-8">', '</html>' ], '', $dom->saveHTML( $dom->documentElement ) );
		}
		
		\libxml_use_internal_errors( false );
		// phpcs:enable
		
		return $content;
	}
	
	/**
	 * Get an embed provider by its name.
	 * 
	 * @since	1.3.5
	 * 
	 * @param	string	$name The name to search for
	 * @return	\WP_Post|null The embed or null
	 */
	public function get_embed_by_name( $name ) {
		if ( empty( $name ) ) {
			return null;
		}
		
		$embed_providers = $this->get_embeds();
		$embed = null;
		$pattern = '/^' . \preg_quote( $name, '/' ) . '\-\d+/';
		
		foreach ( $embed_providers as $embed_provider ) {
			if ( $embed_provider->post_name !== $name && ! \preg_match( $pattern, $embed_provider->post_name ) ) {
				continue;
			}
			
			$embed = $embed_provider;
			break;
		}
		
		return $embed;
	}
	
	/**
	 * Get an embed provider overlay.
	 * 
	 * @since	1.3.5
	 * 
	 * @param	\WP_Post	$provider An embed provider
	 * @param	string		$content The content
	 * @return	string The content with additional overlays of an embed provider
	 */
	private function get_embed_overlay( $provider, $content ) {
		// make sure to test every provider for its always active state
		if ( $this->is_always_active_provider( $provider->post_name ) ) {
			return $content;
		}
		
		$regex = \trim( \get_post_meta( $provider->ID, 'regex_default', true ), '/' );
		
		if ( ! empty( $regex ) ) {
			$regex = '/' . $regex . '/';
		}
		
		// get overlay for this provider
		if ( ! empty( $regex ) && \preg_match( $regex, $content ) ) {
			$this->has_embed = true;
			$args['regex'] = $regex;
			$args['post_id'] = $provider->ID;
			$embed_provider = $provider->post_title;
			$embed_provider_lowercase = $provider->post_name;
			$content = $this->get_single_overlay( $content, $embed_provider, $embed_provider_lowercase, $args );
		}
		
		return $content;
	}
	
	/**
	 * Get a specific type of embeds.
	 * 
	 * For more information on the accepted arguments in $args, see the
	 * {@link https://developer.wordpress.org/reference/classes/wp_query/
	 * WP_Query} documentation in the Developer Handbook.
	 * 
	 * @since	1.3.0
	 * @since	1.8.0 Added the $args parameter
	 * 
	 * @param	string	$type The embed type
	 * @param	array	$args Additional arguments
	 * @return	array A list of embeds
	 */
	public function get_embeds( $type = 'all', $args = [] ) {
		if ( ! empty( $this->embeds ) && isset( $this->embeds[ $type ] ) ) {
			return $this->embeds[ $type ];
		}
		
		if ( $type === 'all' && isset( $this->embeds['custom'] ) && isset( $this->embeds['oembed'] ) ) {
			$this->embeds[ $type ] = \array_merge( $this->embeds['custom'], $this->embeds['oembed'] );
			
			return $this->embeds[ $type ];
		}
		
		if ( ! empty( $args ) ) {
			$hash = \md5( \wp_json_encode( $args ) );
		}
		
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		switch ( $type ) {
			case 'custom':
				$custom_providers = \get_posts( \array_merge( [
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
					'no_found_rows' => true,
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
					'update_post_term_cache' => false,
				], $args ) );
				$google_provider = \get_posts( \array_merge( [
					'meta_key' => 'is_system',
					'meta_value' => 'yes',
					'name' => 'google-maps',
					'no_found_rows' => true,
					'post_type' => 'epi_embed',
					'update_post_term_cache' => false,
				], $args ) );
				
				if ( ! empty( $hash ) ) {
					$this->embeds[ $hash ] = \array_merge( $custom_providers, $google_provider );
				}
				else {
					$this->embeds[ $type ] = \array_merge( $custom_providers, $google_provider );
				}
				break;
			case 'oembed':
				$embed_providers = \get_posts( \array_merge( [
					'meta_key' => 'is_system',
					'meta_value' => 'yes',
					'no_found_rows' => true,
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
					'update_post_term_cache' => false,
				], $args ) );
				
				if ( ! empty( $hash ) ) {
					$this->embeds[ $hash ] = $embed_providers;
				}
				else {
					$this->embeds[ $type ] = $embed_providers;
				}
				break;
			case 'all':
			default:
				$embed_providers = \get_posts( \array_merge( [
					'no_found_rows' => true,
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
					'update_post_term_cache' => false,
				], $args ) );
				
				if ( ! empty( $hash ) ) {
					$this->embeds[ $hash ] = $embed_providers;
				}
				else {
					$this->embeds['all'] = $embed_providers;
				}
				break;
		}
		// phpcs:enable
		
		if ( ! empty( $hash ) ) {
			return $this->embeds[ $hash ];
		}
		
		return $this->embeds[ $type ];
	}
	
	/**
	 * Get a list with ignored shortcodes.
	 * 
	 * @since	1.6.0
	 * 
	 * @return	string[] List with ignored shortcodes
	 */
	public function get_ignored_shortcodes() {
		/**
		 * Filter the ignored shortcodes list.
		 * 
		 * @since	1.6.0
		 * 
		 * @param	string[]	$ignored_shortcodes Current list of ignored shortcodes
		 */
		$this->ignored_shortcodes = \apply_filters( 'embed_privacy_ignored_shortcodes', $this->ignored_shortcodes );
		
		return $this->ignored_shortcodes;
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
		\libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $html . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
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
			
			// create meta cite
			$parent_node = $dom->createElement( 'cite' );
			$parent_node->setAttribute( 'class', 'embed-privacy-tweet-meta' );
			// append created cite to blockquote
			$node->parentNode->appendChild( $parent_node );
			// move author meta inside meta cite
			$parent_node->appendChild( $node );
		}
		
		foreach ( $dom->getElementsByTagName( 'a' ) as $link ) {
			if ( ! \preg_match( '/https?:\/\/twitter.com\/([^\/]+)\/status\/(\d+)/', $link->getAttribute( 'href' ) ) ) {
				continue;
			}
			
			// modify date in link to tweet
			$l10n_date = \wp_date( \get_option( 'date_format' ), \strtotime( $link->nodeValue ) );
			
			if ( \is_string( $l10n_date ) ) {
				$link->nodeValue = $l10n_date;
			}
			
			// move link inside meta div
			if ( isset( $parent_node ) && $parent_node instanceof DOMElement ) {
				$parent_node->appendChild( $link );
			}
		}
		
		$content = $dom->saveHTML( $dom->documentElement );
		// phpcs:enable
		
		return \str_replace( [ '<html><meta charset="utf-8">', '</html>' ], [ '<div class="embed-privacy-local-tweet">', '</div>' ], $content );
	}
	
	/**
	 * Get en oEmbed title by its title attribute.
	 * 
	 * @since	1.6.4
	 * 
	 * @param	string	$content The content to get the title of
	 * @return	array The dimensions or an empty array
	 */
	private function get_oembed_dimensions( $content ) {
		\libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $content . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		\libxml_use_internal_errors( false );
		
		foreach ( [ 'embed', 'iframe', 'img', 'object' ] as $tag ) {
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				$height = $element->getAttribute( 'height' );
				$width = $element->getAttribute( 'width' );
				
				if ( $height && $width ) {
					return [
						'height' => $height,
						'width' => $width,
					];
				}
			}
		}
		
		return [];
	}
	
	/**
	 * Get en oEmbed title by its title attribute.
	 * 
	 * @since	1.4.0
	 * 
	 * @param	string	$content The content to get the title of
	 * @return	string The title or an empty string
	 */
	private function get_oembed_title( $content ) {
		\libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $content . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		\libxml_use_internal_errors( false );
		
		foreach ( [ 'embed', 'iframe', 'object' ] as $tag ) {
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				$title = $element->getAttribute( 'title' );
				
				if ( $title ) {
					return $title;
				}
			}
		}
		
		return '';
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
			$embed_post = \get_post( $args['post_id'] );
			
			// if provider is disabled, to nothing
			if ( \get_post_meta( $embed_post->ID, 'is_disabled', true ) === 'yes' ) {
				return $output;
			}
		}
		else {
			$embed_post = null;
		}
		
		if ( $embed_provider_lowercase === 'youtube' ) {
			$output = \str_replace( 'youtube.com', 'youtube-nocookie.com', $output );
		}
		
		$embed_provider_lowercase = \sanitize_title( $embed_provider_lowercase );
		$embed_class = 'embed-' . ( ! empty( $embed_provider_lowercase ) ? $embed_provider_lowercase : 'default' );
		$embed_classes = $embed_class;
		
		$background_path = '';
		$background_url = '';
		$embed_thumbnail = [
			'thumbnail_path' => '',
			'thumbnail_url' => '',
		];
		$logo_path = '';
		$logo_url = '';
		
		if ( ! empty( $args['align'] ) ) {
			$embed_classes .= ' align' . $args['align'];
		}
		
		// display embed provider background image and logo
		if ( $embed_post ) {
			$background_image_id = \get_post_meta( $embed_post->ID, 'background_image', true );
			$thumbnail_id = \get_post_thumbnail_id( $embed_post );
		}
		else {
			$background_image_id = null;
			$thumbnail_id = null;
		}
		
		if ( $background_image_id ) {
			$background_path = \get_attached_file( $background_image_id );
			$background_url = \wp_get_attachment_url( $background_image_id );
		}
		
		if ( $thumbnail_id ) {
			$logo_path = \get_attached_file( $thumbnail_id );
			$logo_url = \get_the_post_thumbnail_url( $args['post_id'] );
		}
		else if ( \file_exists( \plugin_dir_path( $this->plugin_file ) . 'assets/images/embed-' . $embed_provider_lowercase . '.png' ) ) {
			$logo_path = \plugin_dir_path( $this->plugin_file ) . 'assets/images/embed-' . $embed_provider_lowercase . '.png';
			$logo_url = \plugin_dir_url( $this->plugin_file ) . 'assets/images/embed-' . $embed_provider_lowercase . '.png';
		}
		
		if ( ! empty( $args['embed_url'] ) && \get_option( 'embed_privacy_download_thumbnails' ) ) {
			$embed_thumbnail = Thumbnails::get_instance()->get_data( \get_post(), $args['embed_url'] );
		}
		
		if ( ! empty( $args['assets'] ) && \is_array( $args['assets'] ) ) {
			/**
			 * Filter the additional assets of an embed provider.
			 * 
			 * @since	1.4.5
			 * 
			 * @param	array	$assets List of embed assets
			 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
			 */
			$args['assets'] = \apply_filters( "embed_privacy_assets_$embed_provider_lowercase", $args['assets'], $embed_provider_lowercase );
			
			$output = $this->print_embed_assets( $args['assets'], $output );
		}
		
		/**
		 * Filter the path to the background image.
		 * 
		 * @param	string	$background_path The default background path
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$background_path = \apply_filters( "embed_privacy_background_path_$embed_provider_lowercase", $background_path, $embed_provider_lowercase );
		
		/**
		 * Filter the URL to the background image.
		 * 
		 * @param	string	$background_url The default background URL
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$background_url = \apply_filters( "embed_privacy_background_url_$embed_provider_lowercase", $background_url, $embed_provider_lowercase );
		
		/**
		 * Filter the path to the thumbnail.
		 * 
		 * @param	string	$thumbnail_path The default thumbnail path
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$embed_thumbnail['thumbnail_path'] = \apply_filters( "embed_privacy_thumbnail_path_$embed_provider_lowercase", $embed_thumbnail['thumbnail_path'], $embed_provider_lowercase );
		
		/**
		 * Filter the URL to the thumbnail.
		 * 
		 * @param	string	$thumbnail_url The default thumbnail URL
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$embed_thumbnail['thumbnail_url'] = \apply_filters( "embed_privacy_thumbnail_url_$embed_provider_lowercase", $embed_thumbnail['thumbnail_url'], $embed_provider_lowercase );
		
		/**
		 * Filter the path to the logo.
		 * 
		 * @param	string	$logo_path The default logo path
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$logo_path = \apply_filters( "embed_privacy_logo_path_$embed_provider_lowercase", $logo_path, $embed_provider_lowercase );
		
		/**
		 * Filter the URL to the logo.
		 * 
		 * @param	string	$logo_url The default logo URL
		 * @param	string	$embed_provider_lowercase The current embed provider in lowercase
		 */
		$logo_url = \apply_filters( "embed_privacy_logo_url_$embed_provider_lowercase", $logo_url, $embed_provider_lowercase );
		
		$embed_md5 = \md5( $output . \wp_generate_uuid4() );
		
		\ob_start();
		?>
		<p>
		<?php
			if ( ! empty( $embed_provider ) ) {
				if ( $embed_post ) {
					$allowed_tags = [
						'a' => [
							'href',
							'target',
						],
					];
					echo $embed_post->post_content . \PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$privacy_policy = \get_post_meta( $embed_post->ID, 'privacy_policy_url', true );
					
					if ( $privacy_policy ) {
						?>
						<br>
						<?php
						/* translators: 1: the embed provider, 2: opening <a> tag to the privacy policy, 3: closing </a> */
						\printf( \wp_kses( \__( 'Learn more in %1$s’s %2$sprivacy policy%3$s.', 'embed-privacy' ), $allowed_tags ), \esc_html( $embed_provider ), '<a href="' . \esc_url( $privacy_policy ) . '" target="_blank">', '</a>' );
					}
				}
				else {
					/* translators: the embed provider */
					\printf( \esc_html__( 'Click here to display content from %s', 'embed-privacy' ), \esc_html( $embed_provider ) );
				}
			}
			else {
				\esc_html_e( 'Click here to display content from an external service.', 'embed-privacy' );
			}
		?>
		</p>
		<?php
		$checkbox_id = 'embed-privacy-store-' . $embed_provider_lowercase . '-' . $embed_md5;
		
		if ( $embed_provider_lowercase !== 'default' ) {
			?>
			<p class="embed-privacy-input-wrapper">
				<input id="<?php echo \esc_attr( $checkbox_id ); ?>" type="checkbox" value="1" class="embed-privacy-input" data-embed-provider="<?php echo \esc_attr( $embed_provider_lowercase ); ?>">
				<label for="<?php echo \esc_attr( $checkbox_id ); ?>" class="embed-privacy-label" data-embed-provider="<?php echo \esc_attr( $embed_provider_lowercase ); ?>">
					<?php
					/* translators: the embed provider */
					\printf( \esc_html__( 'Always display content from %s', 'embed-privacy' ), \esc_html( $embed_provider ) );
					?>
				</label>
			</p>
			<?php
		}
		
		$content = \ob_get_clean();
		
		/**
		 * Filter the content of the embed overlay.
		 * 
		 * @param	string		$content The content
		 * @param	string		$embed_provider The embed provider of this embed
		 */
		$content = \apply_filters( 'embed_privacy_content', $content, $embed_provider );
		
		\ob_start();
		
		$footer_content = '';
		
		if ( ! empty( $args['embed_url'] ) ) {
			$footer_content = '<div class="embed-privacy-footer">';
			
			if ( ! \get_option( 'embed_privacy_disable_link' ) ) {
				$footer_content .= '<span class="embed-privacy-url"><a href="' . \esc_url( $args['embed_url'] ) . '">';
				$footer_content .= \sprintf(
				/* translators: content name or 'content' */
					\esc_html__( 'Open %s directly', 'embed-privacy' ),
					! empty( $args['embed_title'] ) ? $args['embed_title'] : \__( 'content', 'embed-privacy' )
				);
				$footer_content .= '</a></span>';
			}
			
			$footer_content .= '</div>' . \PHP_EOL;
			
			/**
			 * Filter the overlay footer.
			 * 
			 * @param	string	$footer_content The footer content
			 */
			$footer_content = \apply_filters( 'embed_privacy_overlay_footer', $footer_content );
		}
		?>
		<div class="embed-privacy-container is-disabled <?php echo \esc_attr( $embed_classes ); ?>" data-embed-id="oembed_<?php echo \esc_attr( $embed_md5 ); ?>" data-embed-provider="<?php echo \esc_attr( $embed_provider_lowercase ); ?>"<?php echo ( ! empty( $embed_thumbnail['thumbnail_path'] ) && \file_exists( $embed_thumbnail['thumbnail_path'] ) ? ' style="background-image: url(' . \esc_url( $embed_thumbnail['thumbnail_url'] ) . ');"' : '' ); ?>>
			<?php /* translators: embed provider */ ?>
			<button class="embed-privacy-enable screen-reader-text"><?php \printf( \esc_html__( 'Display content from %s', 'embed-privacy' ), \esc_html( $embed_provider ) ); ?></button>
			
			<div class="embed-privacy-overlay">
				<div class="embed-privacy-inner">
					<?php
					echo ( \file_exists( $logo_path ) ? '<div class="embed-privacy-logo"></div>' . \PHP_EOL : '' );
					echo $content . \PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
				
				<?php echo $footer_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			
			<div class="embed-privacy-content">
				<script>var _oembed_<?php echo $embed_md5; ?> = '<?php echo \addslashes( \wp_json_encode( [ 'embed' => \htmlentities( \preg_replace( '/\s+/S', ' ', $output ) ) ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';</script>
			</div>
			
			<style>
				<?php
				if ( ! empty( $args['height'] ) && ! empty( $args['width'] ) && empty( $args['ignore_aspect_ratio'] ) ) {
					// if height is in percentage, we cannot determine the aspect ratio
					if ( \strpos( $args['height'], '%' ) !== false ) {
						$args['ignore_aspect_ratio'] = true;
					}
					// if width is in percentage, we need to use the content width
					// since we cannot determine the actual width
					if ( \strpos( $args['width'], '%' ) !== false ) {
						global $content_width;
						
						$args['width'] = $content_width;
					}
					
					\printf(
						'[data-embed-id="oembed_%1$s"] {
							aspect-ratio: %2$s;
						}',
						\esc_attr( $embed_md5 ),
						\esc_html( $args['width'] . '/' . $args['height'] )
					);
				}
				
				$is_debug = \defined( 'WP_DEBUG' ) && WP_DEBUG;
				
				// display only if file exists
				if ( \file_exists( $background_path ) ) {
					$version = $is_debug ? \filemtime( $background_path ) : EMBED_PRIVACY_VERSION;
					
					\printf(
						'.%1$s {
							background-image: url(%2$s?v=%3$s);
						}',
						\esc_html( $embed_class ),
						\esc_url( $background_url ),
						\esc_html( $version )
					);
				}
				
				// display only if file exists
				if ( \file_exists( $logo_path ) ) {
					$version = $is_debug ? \filemtime( $logo_path ) : EMBED_PRIVACY_VERSION;
					
					\printf(
						'.%1$s {
							background-image: url(%2$s?v=%3$s);
						}',
						\esc_html( $embed_class . ' .embed-privacy-logo' ),
						\esc_url( $logo_url ),
						\esc_html( $version )
					);
				}
				?>
			</style>
		</div>
		<?php
		$markup = \ob_get_clean();
		
		/**
		 * Filter the complete markup of the embed.
		 * 
		 * @param	string	$markup The markup
		 * @param	string	$embed_provider The embed provider of this embed
		 */
		$markup = \apply_filters( 'embed_privacy_markup', $markup, $embed_provider );
		
		$this->has_embed = true;
		
		if ( ! empty( $args['strip_newlines'] ) ) {
			$markup = \str_replace( \PHP_EOL, '', $markup );
		}
		
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
		
		$args = \wp_parse_args( $args, [
			'additional_checks' => [],
			'check_always_active' => false,
			'element_attribute' => 'src',
			'elements' => [ 'embed', 'iframe', 'object' ],
			'height' => 0,
			'ignore_aspect_ratio' => false,
			'regex' => '',
			'strip_newlines' => ! \has_blocks( $content ),
			'width' => 0,
		] );
		
		\libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . \str_replace( '%', '%_epi_', $content ) . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		$is_empty_provider = empty( $embed_provider );
		$template_dom = new DOMDocument();
		
		if ( $is_empty_provider ) {
			$providers = $this->get_embeds();
		}
		
		// detect domain if WordPress is installed on a sub domain
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		
		if ( ! \filter_var( $host, \FILTER_VALIDATE_IP ) ) {
			$host_array = \explode( '.', \str_replace( 'www.', '', $host ) );
			$tld_count = \count( $host_array );
			
			if ( $tld_count >= 3 && strlen( $host_array[ $tld_count - 2 ] ) === 2 ) {
				$host = \implode( '.', \array_splice( $host_array, $tld_count - 3, 3 ) );
			}
			else if ( $tld_count >= 2 ) {
				$host = \implode( '.', \array_splice( $host_array, $tld_count - 2, $tld_count ) );
			}
		}
		
		foreach ( $args['elements'] as $tag ) {
			$replacements = [];
			
			if ( $tag === 'object' ) {
				$args['element_attribute'] = 'data';
			}
			
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				if ( ! $this->run_checks( $args['additional_checks'], $element ) ) {
					continue;
				}
				
				// ignore embeds from the same (sub-)domain
				if ( \preg_match( '/https?:\/\/(.*\.)?' . \preg_quote( $host, '/' ) . '/', $element->getAttribute( $args['element_attribute'] ) ) ) {
					continue;
				}
				
				if ( ! empty( $args['regex'] ) && ! \preg_match( $args['regex'], $element->getAttribute( $args['element_attribute'] ) ) ) {
					continue;
				}
				
				// providers need to be explicitly checked if they're always active
				// see https://github.com/epiphyt/embed-privacy/issues/115
				if ( $embed_provider_lowercase && $args['check_always_active'] && $this->is_always_active_provider( $embed_provider_lowercase ) ) {
					if ( ! empty( $args['assets'] ) ) {
						$content = $this->print_embed_assets( $args['assets'], $content );
					}
					
					return $content;
				}
				
				if ( $is_empty_provider ) {
					$embedded_host = \wp_parse_url( $element->getAttribute( $args['element_attribute'] ), \PHP_URL_HOST );
					
					// embeds with relative paths have no host
					// and they are local by definition, so do nothing
					// see https://github.com/epiphyt/embed-privacy/issues/27
					if ( empty( $embedded_host ) ) {
						return $content;
					}
					
					$embed_provider = $embedded_host;
					$embed_provider_lowercase = \sanitize_title( $embedded_host );
					
					// unknown providers need to be explicitly checked if they're always active
					// see https://github.com/epiphyt/embed-privacy/issues/115
					if ( $args['check_always_active'] && $this->is_always_active_provider( $embed_provider_lowercase ) ) {
						if ( ! empty( $args['assets'] ) ) {
							$content = $this->print_embed_assets( $args['assets'], $content );
						}
						
						return $content;
					}
					
					// check URL for available provider
					foreach ( $providers as $provider ) {
						$regex = \trim( \get_post_meta( $provider->ID, 'regex_default', true ), '/' );
						
						if ( ! empty( $regex ) ) {
							$regex = '/' . $regex . '/';
						}
						else {
							continue;
						}
						
						if ( \preg_match( $regex, $element->getAttribute( $args['element_attribute'] ) ) && empty( $replacements ) ) {
							continue 2;
						}
					}
				}
				
				/* translators: embed title */
				$args['embed_title'] = ! empty( $element->getAttribute( 'title' ) ) ? \sprintf( \__( '"%s"', 'embed-privacy' ), $element->getAttribute( 'title' ) ) : '';
				$args['embed_url'] = $element->getAttribute( $args['element_attribute'] );
				$args['height'] = ! empty( $element->getAttribute( 'height' ) ) ? $element->getAttribute( 'height' ) : 0;
				$args['width'] = ! empty( $element->getAttribute( 'width' ) ) ? $element->getAttribute( 'width' ) : 0;
				
				// get overlay template as DOM element
				$template_dom->loadHTML(
					'<html><meta charset="utf-8">' . str_replace( '%', '%_epi_', $this->get_output_template( $embed_provider, $embed_provider_lowercase, $dom->saveHTML( $element ), $args ) ) . '</html>',
					\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
				);
				$overlay = null;
				
				foreach ( $template_dom->getElementsByTagName( 'div' ) as $div ) {
					if ( stripos( $div->getAttribute( 'class' ), 'embed-privacy-container' ) !== false ) {
						$overlay = $div;
						break;
					}
				}
				
				// store the elements to replace (see regressive loop down below)
				if ( $overlay instanceof DOMNode || $overlay instanceof DOMElement ) {
					$replacements[] = [
						'element' => $element,
						'replace' => $dom->importNode( $overlay, true ),
					];
				}
				
				// reset embed provider name
				if ( $is_empty_provider ) {
					$embed_provider = '';
					$embed_provider_lowercase = '';
				}
			}
			
			if ( ! empty( $replacements ) ) {
				$this->did_replacements = \array_merge( $this->did_replacements, $replacements );
				$this->has_embed = true;
				$elements = $dom->getElementsByTagName( $tag );
				$i = $elements->length - 1;
				
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
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
				
				$content = \str_replace( '%_epi_', '%', $dom->saveHTML( $dom->documentElement ) );
				// phpcs:enable
			}
		}
		
		\libxml_use_internal_errors( false );
		
		// embeds for other elements need to be handled manually
		// make sure to test before if the regex matches
		// see: https://github.com/epiphyt/embed-privacy/issues/26
		if (
			empty( $this->did_replacements )
			&& ! empty( $args['regex'] )
			&& ! $is_empty_provider
		) {
			$provider = $this->get_embed_by_name( $embed_provider_lowercase );
			
			if (
				$provider instanceof WP_Post
				&& ! \get_post_meta( $provider->ID, 'is_system', true )
				&& \get_post_meta( $provider->ID, 'is_disabled', true ) !== 'yes'
			) {
				// extend regular expression to match the full element
				if ( \strpos( $args['regex'], '<' ) === false || \strpos( $args['regex'], '>' ) === false ) {
					$allowed_tags = [
						'blockquote',
						'div',
						'embed',
						'iframe',
						'object',
					];
					
					/**
					 * Filter allowed HTML tags in regular expressions.
					 * Only elements matching these tags get processed.
					 * 
					 * @since	1.6.0
					 * 
					 * @param	string[]	$allowed_tags The allowed tags
					 * @param	string		$embed_provider_lowercase The embed provider without spaces and in lowercase
					 * @return	array A list of allowed tags
					 */
					$allowed_tags = \apply_filters( 'embed_privacy_matcher_elements', $allowed_tags, $embed_provider_lowercase );
					
					$tags_regex = '(' . \implode( '|', \array_filter( $allowed_tags, function( $tag ) {
						return \preg_quote( $tag, '/' );
					} ) ) . ')';
					$args['regex'] = '/<' . $tags_regex . '([^"]*)"([^<]*)' . \trim( $args['regex'], '/' ) . '([^"]*)"([^>]*)(>(.*)<\/' . $tags_regex . ')?>/';
				}
				
				while ( \preg_match( $args['regex'], $content, $matches ) ) {
					$content = \preg_replace( $args['regex'], $this->get_output_template( $embed_provider, $embed_provider_lowercase, $matches[0], $args ), $content, 1 );
				}
			}
		}
		
		// decode to make sure there is nothing left encoded if replacements have been made
		// otherwise, content is untouched by DOMDocument, and we don't need a decoding
		if ( ! empty( $this->did_replacements ) ) {
			$content = \rawurldecode( $content );
		}
		
		// remove root element, see https://github.com/epiphyt/embed-privacy/issues/22
		return \str_replace(
			[
				'<html><meta charset="utf-8">',
				'</html>',
				'%_epi_',
			],
			[
				'',
				'',
				'%',
			],
			$content
		);
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
		
		if ( \is_numeric( $post ) ) {
			$post = \get_post( $post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		
		/**
		 * Allow overwriting the return value of has_embed().
		 * If set to anything other than null, this value will be returned.
		 * 
		 * @param	null	$has_embed The default value
		 */
		$has_embed = \apply_filters( 'embed_privacy_has_embed', null );
		
		if ( $has_embed !== null ) {
			return $has_embed;
		}
		
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		
		if ( $this->has_embed ) {
			return true;
		}
		
		$embed_providers = $this->get_embeds();
		
		// check post content
		foreach ( $embed_providers as $provider ) {
			$regex = \trim( \get_post_meta( $provider->ID, 'regex_default', true ), '/' );
			
			if ( empty( $regex ) ) {
				continue;
			}
			
			// get overlay for this provider
			if ( \preg_match( '/' . $regex . '/', $post->post_content ) ) {
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
		/** @noinspection PhpUndefinedFunctionInspection */
		return \function_exists( 'is_amp_endpoint' ) && \is_amp_endpoint();
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
		$javascript_detection = \get_option( 'embed_privacy_javascript_detection' );
		
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
	 * Check if a post is written in Elementor.
	 * 
	 * @since	1.3.5
	 * 
	 * @return	bool True if Elementor is used, false otherwise
	 */
	public function is_elementor() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		if (
			! \is_plugin_active( 'elementor/elementor.php' )
			|| ! \get_the_ID()
			|| ! Plugin::$instance->documents->get( \get_the_ID() )->is_built_with_elementor()
		) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Check if the current theme is matching your name.
	 * 
	 * @since	1.3.5
	 * 
	 * @param	string	$name The theme name to test
	 * @return	bool True if the current theme is matching, false otherwise
	 */
	public function is_theme( $name ) {
		$name = \strtolower( $name );
		
		if ( \strtolower( \wp_get_theme()->get( 'Name' ) ) === $name || \strtolower( \wp_get_theme()->get( 'Template' ) ) === $name ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Load the translation files.
	 */
	public function load_textdomain() {
		\load_plugin_textdomain( 'embed-privacy', false, \dirname( \plugin_basename( $this->plugin_file ) ) . '/languages' );
	}
	
	/**
	 * Preserve backslashes in regex field.
	 * 
	 * @since	1.4.0
	 */
	public function preserve_backslashes() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['regex_default'] ) ) {
			return;
		}
		
		$_POST['regex_default'] = \wp_slash( $_POST['regex_default'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable
	}
	
	/**
	 * Handle printing assets.
	 * 
	 * @since	1.3.0
	 */
	public function print_assets() {
		if ( $this->is_printed ) {
			return;
		}
		
		\wp_enqueue_script( 'embed-privacy' );
		\wp_enqueue_style( 'embed-privacy' );
		\wp_localize_script( 'embed-privacy', 'embedPrivacy', [
			'alwaysActiveProviders' => \array_keys( (array) $this->get_cookie() ), // deprecated
			'javascriptDetection' => \get_option( 'embed_privacy_javascript_detection' ),
		] );
		
		if ( $this->is_theme( 'Astra' ) ) {
			\wp_enqueue_style( 'embed-privacy-astra' );
		}
		
		if ( $this->is_theme( 'Divi' ) ) {
			\wp_enqueue_style( 'embed-privacy-divi' );
		}
		
		if ( $this->is_elementor() ) {
			\wp_enqueue_script( 'embed-privacy-elementor-video' );
			\wp_enqueue_style( 'embed-privacy-elementor' );
		}
		
		if ( ! \function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . WPINC . '/plugin.php';
		}
		
		if ( \is_plugin_active( 'kadence-blocks/kadence-blocks.php' ) ) {
			\wp_enqueue_style( 'embed-privacy-kadence-blocks' );
		}
		
		if ( \is_plugin_active( 'shortcodes-ultimate/shortcodes-ultimate.php' ) ) {
			\wp_enqueue_style( 'embed-privacy-shortcodes-ultimate' );
		}
		
		$this->is_printed = true;
	}
	
	/**
	 * Print assets of an embed before the content.
	 * 
	 * @since	1.4.5
	 * 
	 * @param	array	$assets List of assets
	 * @param	string	$output The output
	 * @return	string The updated output
	 */
	private function print_embed_assets( $assets, $output ) {
		if ( empty( $assets ) ) {
			return $output;
		}
		
		foreach ( array_reverse( $assets ) as $asset ) {
			if ( empty( $asset['type'] ) ) {
				continue;
			}
			
			if ( $asset['type'] === 'script' ) {
				if ( empty( $asset['handle'] ) || empty( $asset['src'] ) ) {
					continue;
				}
				
				$output = '<script src="' . \esc_url( $asset['src'] ) . ( ! empty( $asset['version'] ) ? '?ver=' . \esc_attr( \rawurlencode( $asset['version'] ) ) : '' ) . '" id="' . \esc_attr( $asset['handle'] ) . '"></script>' . \PHP_EOL . $output; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			}
			else if ( $asset['type'] === 'inline' ) {
				if ( empty( $asset['data'] ) || empty( $asset['object_name'] ) ) {
					continue;
				}
				
				if ( \is_string( $asset['data'] ) ) {
					$data = \html_entity_decode( $asset['data'], \ENT_QUOTES, 'UTF-8' );
				}
				else {
					foreach ( (array) $asset['data'] as $key => $value ) {
						if ( ! \is_scalar( $value ) ) {
							continue;
						}
						
						$data[ $key ] = \html_entity_decode( (string) $value, \ENT_QUOTES, 'UTF-8' );
					}
				}
				$output = '<script>var ' . esc_js( $asset['object_name'] ) . ' = ' . \wp_json_encode( $data ) . ';</script>' . \PHP_EOL . $output;
			}
		}
		
		return $output;
	}
	
	/**
	 * Register our assets for the frontend.
	 * 
	 * @since	1.4.4
	 */
	public function register_assets() {
		if ( \is_admin() || \wp_doing_ajax() || \wp_doing_cron() ) {
			return;
		}
		
		$is_debug = \defined( 'WP_DEBUG' ) && WP_DEBUG;
		$suffix = ( $is_debug ? '' : '.min' );
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/embed-privacy' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/embed-privacy' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy', $css_file_url, [], $file_version );
		
		if ( ! $this->is_amp() ) {
			$js_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/embed-privacy' . $suffix . '.js';
			$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/js/embed-privacy' . $suffix . '.js' ) : \EMBED_PRIVACY_VERSION;
			
			\wp_register_script( 'embed-privacy', $js_file_url, [], $file_version );
		}
		
		// Astra is too greedy at its CSS selectors
		// see https://github.com/epiphyt/embed-privacy/issues/33
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/astra' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/astra' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-astra', $css_file_url, [], $file_version );
		
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/divi' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/divi' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-divi', $css_file_url, [], $file_version );
		
		$js_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/elementor-video' . $suffix . '.js';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/js/elementor-video' . $suffix . '.js' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_script( 'embed-privacy-elementor-video', $js_file_url, [], $file_version );
		
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/elementor' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/elementor' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-elementor', $css_file_url, [], $file_version );
		
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/kadence-blocks' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/kadence-blocks' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-kadence-blocks', $css_file_url, [], $file_version );
		
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/shortcodes-ultimate' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/shortcodes-ultimate' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-shortcodes-ultimate', $css_file_url, [], $file_version );
		
		$current_url = \sprintf(
			'http%1$s://%2$s%3$s',
			\is_ssl() ? 's' : '',
			! empty( $_SERVER['HTTP_HOST'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '',
			! empty( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''
		);
		
		if ( empty( $_SERVER['HTTP_HOST'] ) ) {
			return;
		}
		
		$post_id = \url_to_postid( $current_url );
		
		if ( $post_id ) {
			$post = \get_post( $post_id );
		
			if ( $post instanceof WP_Post && \has_shortcode( $post->post_content, 'embed_privacy_opt_out' ) ) {
				$this->print_assets();
			}
		}
	}
	
	/**
	 * Register post type in Polylang to allow translation.
	 * 
	 * @since	1.5.0
	 * 
	 * @param	array	$post_types List of current translatable custom post types
	 * @param	bool	$is_settings Whether the current page is the settings page
	 * @return	array Updated list of translatable custom post types
	 */
	public function register_polylang_post_type( array $post_types, $is_settings ) {
		if ( $is_settings ) {
			unset( $post_types['epi_embed'] );
		}
		else {
			$post_types['epi_embed'] = 'epi_embed';
		}
		
		return $post_types;
	}
	
	/**
	 * Replace embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @since	1.2.0 Changed behavior of the method
	 * @since	1.6.0 Added optional $tag parameter
	 * 
	 * @param	string	$content The original content
	 * @param	string	$tag The shortcode tag if called via do_shortcode
	 * @return	string The updated content
	 */
	public function replace_embeds( $content, $tag = '' ) {
		// do nothing in admin
		if ( ! $this->usecache ) {
			return $content;
		}
		
		// do nothing for ignored shortcodes
		if ( ! empty( $tag ) && \in_array( $tag, $this->get_ignored_shortcodes(), true ) ) {
			return $content;
		}
		
		// check content for already available embeds
		if ( ! $this->has_embed && \strpos( $content, '<div class="embed-privacy-overlay">' ) !== false ) {
			$this->has_embed = true;
		}
		
		// get all embed providers
		$embed_providers = $this->get_embeds();
		
		foreach ( $embed_providers as $provider ) {
			$content = $this->get_embed_overlay( $provider, $content );
		}
		
		// Elementor video providers need special treatment
		if ( $this->is_elementor() ) {
			$embed_providers = [
				$this->get_embed_by_name( 'dailymotion' ),
				$this->get_embed_by_name( 'vimeo' ),
			];
			
			foreach ( $embed_providers as $provider ) {
				$content = $this->get_embed_overlay( $provider, $content );
			}
			
			if ( strpos( $content, 'youtube.com\/watch' ) !== false ) {
				$content = $this->get_elementor_youtube_overlay( $content );
			}
		}
		
		/**
		 * If set to true, unknown providers are not handled via Embed Privacy.
		 * 
		 * @since	1.5.0
		 * 
		 * @param	bool	$ignore_unknown Whether unknown providers should be ignored
		 * @param	string	$content The original content
		 */
		$ignore_unknown_providers = \apply_filters( 'embed_privacy_ignore_unknown_providers', false, $content );
		
		// get default external content
		// special case for youtube-nocookie.com as it is part of YouTube provider
		// and gets rewritten in Divi
		// see: https://github.com/epiphyt/embed-privacy/issues/69
		if (
			! $ignore_unknown_providers
			&& (
				\strpos( $content, 'youtube-nocookie.com' ) === false
				|| ! $this->is_always_active_provider( 'youtube' )
			)
		) {
			$new_content = $this->get_single_overlay( $content, '', '', [ 'check_always_active' => true ] );
			
			if ( $new_content !== $content ) {
				$this->has_embed = true;
				$content = $new_content;
			}
		}
		
		if ( \strpos( $content, 'class="fb-post"' ) !== false ) {
			$provider = $this->get_embed_by_name( 'facebook' );
			$args = [
				'additional_checks' => [
					[
						'attribute' => 'class',
						'compare' => '===',
						'type' => 'attribute',
						'value' => 'fb-post',
					],
				],
				'assets' => [],
				'check_always_active' => true,
				'element_attribute' => 'data-href',
				'elements' => [
					'div',
				],
			];
			
			// register jetpack script if available
			if ( \class_exists( '\Automattic\Jetpack\Assets' ) && \defined( 'JETPACK__VERSION' ) ) {
				$jetpack = Jetpack::init();
				
				$args['assets'][] = [
					'type' => 'inline',
					'object_name' => 'jpfbembed',
					'data' => [
						/**
						 * Filter the Jetpack sharing Facebook app ID.
						 * 
						 * @since	1.4.5
						 * 
						 * @param	string	$app_id The current app ID
						 */
						'appid' => \apply_filters( 'jetpack_sharing_facebook_app_id', '249643311490' ),
						'locale' => $jetpack->get_locale(),
					],
				];
				$args['assets'][] = [
					'type' => 'script',
					'handle' => 'jetpack-facebook-embed',
					'src' => Assets::get_file_url_for_environment( '_inc/build/facebook-embed.min.js', '_inc/facebook-embed.js' ),
					'version' => \JETPACK__VERSION,
				];
			}
			
			$new_content = $this->get_single_overlay( $content, $provider->post_title, $provider->post_name, $args );
			
			if ( $new_content !== $content ) {
				$this->has_embed = true;
				$content = $new_content;
			}
		}
		
		if ( $this->has_embed ) {
			$this->print_assets();
		}
		
		return $content;
	}
	
	/**
	 * Replace oembed embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @since	1.2.0
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
		
		// ignore embeds without host (ie. relative URLs)
		if ( empty( \wp_parse_url( $url, \PHP_URL_HOST ) ) ) {
			return $output;
		}
		
		// check the current host
		// see: https://github.com/epiphyt/embed-privacy/issues/24
		if ( \strpos( $url, \wp_parse_url( \home_url(), \PHP_URL_HOST ) ) !== false ) {
			return $output;
		}
		
		$embed_provider = '';
		$embed_provider_lowercase = '';
		$embed_providers = $this->get_embeds( 'oembed' );
		
		// get embed provider name
		foreach ( $embed_providers as $provider ) {
			$regex = \get_post_meta( $provider->ID, 'regex_default', true );
			$regex = '/' . \trim( $regex, '/' ) . '/';
			
			// save name of provider and stop loop
			if ( $regex !== '//' && \preg_match( $regex, $url ) ) {
				$this->has_embed = true;
				$args['post_id'] = $provider->ID;
				$embed_provider = $provider->post_title;
				$embed_provider_lowercase = $provider->post_name;
				break;
			}
		}
		
		// see https://github.com/epiphyt/embed-privacy/issues/89
		if ( empty( $embed_provider ) ) {
			$parsed_url = \wp_parse_url( $url );
			$embed_provider = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		}
		
		// make sure to only run once
		if ( \strpos( $output, 'data-embed-provider="' . $embed_provider_lowercase . '"' ) !== false ) {
			return $output;
		}
		
		if ( $embed_provider_lowercase === 'youtube' ) {
			// replace youtube.com to youtube-nocookie.com
			$output = \str_replace( 'youtube.com', 'youtube-nocookie.com', $output );
		}
		else if ( $embed_provider_lowercase === 'twitter' && \get_option( 'embed_privacy_local_tweets' ) ) {
			// check for local tweets
			return $this->get_local_tweet( $output );
		}
		
		// check if cookie is set
		if ( $embed_provider_lowercase !== 'default' && $this->is_always_active_provider( $embed_provider_lowercase ) ) {
			return $output;
		}
		
		$embed_title = $this->get_oembed_title( $output );
		/* translators: embed title */
		$args['embed_title'] = ! empty( $embed_title ) ? \sprintf( \__( '"%s"', 'embed-privacy' ), $embed_title ) : '';
		$args['embed_url'] = $url;
		$args['strip_newlines'] = true;
		
		// the default dimensions are useless
		// so ignore them if recognized as such
		$defaults = \wp_embed_defaults( $url );
		
		if (
			! empty( $args['height'] ) && $args['height'] === $defaults['height']
			&& ! empty( $args['width'] ) && $args['width'] === $defaults['width']
		) {
			unset( $args['height'], $args['width'] );
			
			$dimensions = $this->get_oembed_dimensions( $output );
			
			if ( ! empty( $dimensions ) ) {
				$args = \array_merge( $args, $dimensions );
			}
		}
		
		// add two click to markup
		return $this->get_output_template( $embed_provider, $embed_provider_lowercase, $output, $args );
	}
	
	/**
	 * Replace embeds in Divi Builder.
	 * 
	 * @since	1.2.0
	 * @since	1.6.0 Deprecated second parameter
	 * 
	 * @param	string	$item_embed The original output
	 * @param	string	$url The URL of the embed
	 * @return	string The updated embed code
	 */
	public function replace_embeds_divi( $item_embed, $url ) {
		return $this->replace_embeds_oembed( $item_embed, $url, [] );
	}
	
	/**
	 * Replace twitter embeds.
	 * 
	 * @deprecated	1.6.3
	 * @since		1.6.1
	 * 
	 * @param	string	$output The original output
	 * @param	string	$url The URL to the embed
	 * @param	array	$args Additional arguments of the embed
	 * @return	string The updated embed code
	 */
	public function replace_embeds_twitter( $output, $url, $args ) {
		// do nothing in admin
		if ( ! $this->usecache ) {
			return $output;
		}
		
		$provider = $this->get_embed_by_name( 'twitter' );
		
		if ( ! \preg_match( \get_post_meta( $provider->ID, 'regex_default', true ), $url ) ) {
			return $output;
		}
		
		if ( $this->is_always_active_provider( $provider->post_name ) ) {
			return $output;
		}
		
		if ( \get_option( 'embed_privacy_local_tweets' ) ) {
			// check for local tweets
			return $this->get_local_tweet( $output );
		}
		
		$args['embed_url'] = $url;
		$args['ignore_aspect_ratio'] = true;
		$args['strip_newlines'] = true;
		
		return $this->get_output_template( $provider->post_title, $provider->post_name, $output, $args );
	}
	
	/**
	 * Replace Google Maps iframes.
	 * 
	 * @deprecated	1.2.0 Use Embed_Privacy::get_embed_overlay() instead
	 * @since		1.1.0
	 * 
	 * @param	string	$content The post content
	 * @return	string The post content
	 */
	public function replace_google_maps( $content ) {
		\preg_match_all( self::IFRAME_REGEX, $content, $matches );
		
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
			if ( \strpos( $match, 'google.com/maps' ) === false ) {
				continue;
			}
			
			$overlay_output = $this->get_output_template( $embed_provider, $embed_provider_lowercase, $match );
			$content = \str_replace( $match, $overlay_output, $content );
		}
		
		return $content;
	}
	
	/**
	 * Replace Maps Marker (Pro) shortcodes.
	 * 
	 * @since	1.5.0
	 * 
	 * @param	string	$output Shortcode output
	 * @param	string	$tag Shortcode tag
	 * @return	string Updated shortcode output
	 */
	public function replace_maps_marker( $output, $tag ) {
		if ( $tag !== 'mapsmarker' ) {
			return $output;
		}
		
		$embed_provider = $this->get_embed_by_name( 'maps-marker' );
		
		if ( \get_post_meta( $embed_provider->ID, 'is_disabled', true ) ) {
			return $output;
		}
		
		return $this->get_output_template( $embed_provider->post_title, $embed_provider->post_name, $output );
	}
	
	/**
	 * Replace video shortcode embeds.
	 * 
	 * @since	1.7.0
	 * 
	 * @param	string	$output Video shortcode HTML output
	 * @param	array	$atts Array of video shortcode attributes
	 */
	public function replace_video_shortcode( $output, $atts ) {
		$url = isset( $atts['src'] ) ? $atts['src'] : '';
		
		if ( empty( $url ) && ! empty( $atts['mp4'] ) ) {
			$url = $atts['mp4'];
		}
		else if ( empty( $url ) && ! empty( $atts['m4v'] ) ) {
			$url = $atts['m4v'];
		}
		else if ( empty( $url ) && ! empty( $atts['webm'] ) ) {
			$url = $atts['webm'];
		}
		else if ( empty( $url ) && ! empty( $atts['ogv'] ) ) {
			$url = $atts['ogv'];
		}
		else if ( empty( $url ) && ! empty( $atts['flv'] ) ) {
			$url = $atts['flv'];
		}
		
		// ignore relative URLs
		if ( empty( \wp_parse_url( $url, \PHP_URL_HOST ) ) ) {
			return $output;
		}
		
		return $this->replace_embeds_oembed( $output, $url, $atts );
	}
	
	/**
	 * Run a compare check.
	 * 
	 * @since	1.4.4
	 * 
	 * @param	mixed	$value1 First value to compare
	 * @param	mixed	$value2 Second value to compare
	 * @param	string	$compare Compare operator
	 * @return	bool Result of comparing the values
	 */
	private function run_check_compare( $value1, $value2, $compare ) {
		switch ( $compare ) {
			case '===':
				return $value1 === $value2;
			case '==':
				return $value1 == $value2; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			case '!==':
				return $value1 !== $value2;
			case '!=':
				return $value1 != $value2; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			case '>':
				return $value1 > $value2;
			case '>=':
				return $value1 >= $value2;
			case '<':
				return $value1 < $value2;
			case '<=':
				return $value1 <= $value2;
			default:
				return false;
		}
	}
	
	/**
	 * Run additional for a DOM node checks.
	 * 
	 * @since	1.4.4
	 * 
	 * @param	array		$checks A list of checks
	 * @param	\DOMElement	$element The DOM Element
	 * @return	bool Whether all checks are successful
	 */
	private function run_checks( $checks, $element ) {
		if ( empty( $checks ) ) {
			return true;
		}
		
		foreach ( $checks as $check ) {
			if ( $check['type'] === 'attribute' ) {
				$compared = $this->run_check_compare( $element->getAttribute( $check['attribute'] ), $check['value'], $check['compare'] );
				
				if ( ! $compared ) {
					return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Set the plugin file.
	 * 
	 * @since	1.1.0
	 * 
	 * @param	string	$file The path to the file
	 */
	public function set_plugin_file( $file ) {
		if ( \file_exists( $file ) ) {
			$this->plugin_file = $file;
		}
	}
	
	/**
	 * Register post type.
	 * 
	 * @since	1.2.0
	 */
	public function set_post_type() {
		\register_post_type(
			'epi_embed',
			[
				'label' => \__( 'Embeds', 'embed-privacy' ),
				'description' => \__( 'Embeds from Embed Privacy', 'embed-privacy' ),
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
		$attributes = \shortcode_atts( [
			'headline' => \__( 'Embed providers', 'embed-privacy' ),
			'show_all' => 0,
			'subline' => \__( 'Enable or disable embed providers globally. By enabling a provider, its embedded content will be displayed directly on every page without asking you anymore.', 'embed-privacy' ),
		], $attributes );
		$cookie = $this->get_cookie();
		$embed_providers = $this->get_embeds();
		$enabled_providers = array_keys( (array) $cookie );
		$is_javascript_detection = get_option( 'embed_privacy_javascript_detection' ) === 'yes';
		
		if ( empty( $embed_providers ) ) {
			return '';
		}
		
		if ( ! $is_javascript_detection && ! $attributes['show_all'] && ! $enabled_providers ) {
			return '';
		}
		
		$headline = '<h3>' . \esc_html( $attributes['headline'] ) . '</h3>' . \PHP_EOL;
		
		/**
		 * Filter the opt-out headline.
		 * 
		 * @param	string	$headline Current headline HTML
		 * @param	array	$attributes Shortcode attributes
		 */
		$headline = \apply_filters( 'embed_privacy_opt_out_headline', $headline, $attributes );
		
		/**
		 * Filter the opt-out subline.
		 * 
		 * @param	string	$subline Current subline HTML
		 * @param	array	$attributes Shortcode attributes
		 */
		$subline = \apply_filters( 'embed_privacy_opt_out_subline', '<p>' . \esc_html( $attributes['subline'] ) . '</p>' . \PHP_EOL, $attributes );
		
		$output = '<div class="embed-privacy-opt-out" data-show-all="' . ( $attributes['show_all'] ? 1 : 0 ) . '">' . \PHP_EOL . $headline . $subline;
		
		foreach ( $embed_providers as $provider ) {
			if ( $is_javascript_detection ) {
				$is_checked = false;
			}
			else if ( $attributes['show_all'] ) {
				$is_checked = \in_array( $provider->post_name, $enabled_providers, true );
			}
			else {
				$is_checked = true;
			}
			
			$is_hidden = ! $is_javascript_detection && ! $attributes['show_all'] && ! \in_array( $provider->post_name, $enabled_providers, true );
			$microtime = \str_replace( '.', '', \microtime( true ) );
			$output .= '<span class="embed-privacy-provider' . ( $is_hidden ? ' is-hidden' : '' ) . '">' . \PHP_EOL;
			$output .= '<input type="checkbox" id="embed-privacy-provider-' . \esc_attr( $provider->post_name ) . '-' . $microtime . '" ' . \checked( $is_checked, true, false ) . ' class="embed-privacy-opt-out-input ' . ( $is_checked ? 'is-enabled' : 'is-disabled' ) . '" data-embed-provider="' . \esc_attr( $provider->post_name ) . '">';
			$output .= '<label class="embed-privacy-opt-out-label" for="embed-privacy-provider-' . \esc_attr( $provider->post_name ) . '-' . $microtime . '" data-embed-provider="' . \esc_attr( $provider->post_name ) . '">';
			$enable_disable = '<span class="embed-privacy-provider-is-enabled">' . \esc_html_x( 'Disable', 'complete string: Disable <embed name>', 'embed-privacy' ) . '</span><span class="embed-privacy-provider-is-disabled">' . \esc_html_x( 'Enable', 'complete string: Disable <embed name>', 'embed-privacy' ) . '</span>';
			/* translators: 1: Enable/Disable, 2: embed provider title */
			$output .= \wp_kses( \sprintf( \__( '%1$s %2$s', 'embed-privacy' ), $enable_disable, \esc_html( $provider->post_title ) ), [ 'span' => [ 'class' => true ] ] );
			$output .= '</label><br>' . \PHP_EOL;
			$output .= '</span>' . \PHP_EOL;
		}
		
		$output .= '</div>' . \PHP_EOL;
		
		return $output;
	}
}
