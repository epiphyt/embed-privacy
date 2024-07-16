<?php
namespace epiphyt\Embed_Privacy;

use DOMDocument;
use DOMElement;
use DOMNode;
use epiphyt\Embed_Privacy\admin\Fields;
use epiphyt\Embed_Privacy\admin\Settings;
use epiphyt\Embed_Privacy\admin\User_Interface;
use epiphyt\Embed_Privacy\embed\Assets;
use epiphyt\Embed_Privacy\embed\Template;
use epiphyt\Embed_Privacy\integration\Activitypub;
use epiphyt\Embed_Privacy\integration\Amp;
use epiphyt\Embed_Privacy\integration\Astra;
use epiphyt\Embed_Privacy\integration\Divi;
use epiphyt\Embed_Privacy\integration\Elementor;
use epiphyt\Embed_Privacy\integration\Jetpack;
use epiphyt\Embed_Privacy\integration\Kadence_Blocks;
use epiphyt\Embed_Privacy\integration\Maps_Marker;
use epiphyt\Embed_Privacy\integration\Polylang;
use epiphyt\Embed_Privacy\integration\Shortcodes_Ultimate;
use epiphyt\Embed_Privacy\integration\Twitter;
use epiphyt\Embed_Privacy\thumbnail\Thumbnail;
use ReflectionMethod;
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
	 * @since	1.10.0 Property is now public
	 * @var		array Replacements that already have taken place.
	 */
	public $did_replacements = [];
	
	/**
	 * @since	1.3.0
	 * @var		array An array of embed providers
	 */
	public $embeds = [];
	
	/**
	 * @since	1.10.0
	 * @var		\epiphyt\Embed_Privacy\admin\Fields
	 */
	public $fields;
	
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
	 * @since	1.10.0
	 * @var		array List of integrations
	 */
	private $integrations = [
		Activitypub::class,
		Amp::class,
		Astra::class,
		Divi::class,
		Elementor::class,
		Jetpack::class,
		Kadence_Blocks::class,
		Maps_Marker::class,
		Polylang::class,
		Shortcodes_Ultimate::class,
		Twitter::class,
	];
	
	/**
	 * @since	1.10.0
	 * @var		bool Whether the current request should be ignored
	 */
	public $is_ignored_request = false;
	
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
	 * @deprecated	1.10.0
	 * @var			array Style properties
	 */
	public $style = [
		'container' => [],
		'global' => [],
	];
	
	/**
	 * @var		\epiphyt\Embed_Privacy\thumbnail\Thumbnail
	 */
	public $thumbnail;
	
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
		$this->fields = new Fields();
		$this->thumbnail = new Thumbnail();
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
		\add_action( 'init', [ $this, 'set_ignored_request' ] );
		\add_action( 'init', [ $this, 'set_post_type' ], 5 );
		\add_action( 'plugins_loaded', [ $this, 'init_integrations' ] );
		\add_action( 'save_post_epi_embed', [ $this, 'preserve_backslashes' ] );
		
		// filters
		if ( ! $this->usecache ) {
			// set ttl to 0 in admin
			\add_filter( 'oembed_ttl', '__return_zero' );
		}
		
		\add_filter( 'acf_the_content', [ $this, 'replace_embeds' ] );
		\add_filter( 'do_shortcode_tag', [ $this, 'replace_embeds' ], 10, 2 );
		\add_filter( 'embed_oembed_html', [ $this, 'replace_embeds_oembed' ], 10, 3 );
		\add_filter( 'embed_privacy_widget_output', [ $this, 'replace_embeds' ] );
		\add_filter( 'the_content', [ $this, 'replace_embeds' ] );
		\add_filter( 'wp_video_shortcode', [ $this, 'replace_video_shortcode' ], 10, 2 );
		\add_shortcode( 'embed_privacy_opt_out', [ $this, 'shortcode_opt_out' ] );
		\register_activation_hook( $this->plugin_file, [ $this, 'clear_embed_cache' ] );
		\register_deactivation_hook( $this->plugin_file, [ $this, 'clear_embed_cache' ] );
		
		Migration::get_instance()->init();
		Provider::get_instance()->init();
		Settings::init();
		User_Interface::init();
		$this->fields->init();
		$this->thumbnail->init();
	}
	
	/**
	 * Initialize all integrations, if necessary.
	 */
	public function init_integrations() {
		/**
		 * Filter the integrations.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	array	$integrations List of integrations
		 */
		$this->integrations = (array) \apply_filters( 'embed_privacy_integrations', $this->integrations );
		
		foreach ( $this->integrations as $integration ) {
			if ( ! \method_exists( $integration, 'init' ) ) {
				continue;
			}
			
			$reflection = new ReflectionMethod( $integration, 'init' );
			
			if ( $reflection->isStatic() ) {
				$integration::init();
			}
			else {
				( new $integration() )->init();
			}
		}
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
	 * @deprecated	1.10.0
	 * @since		1.4.6
	 */
	public function deregister_assets() {
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method is outdated and will be removed in the future.', 'embed-privacy' ),
			'1.10.0'
		);
	}
	
	/**
	 * Enqueue our assets for the frontend.
	 * 
	 * @deprecated	1.4.4 Use Embed_Privacy::print_assets() instead
	 */
	public function enqueue_assets() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Embed_Privacy::print_assets()',
			),
			'1.4.4'
		);
	}
	
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
	 * @deprecated	1.3.5
	 * @since		1.3.0
	 * @noinspection PhpUnused
	 */
	public function get_elementor_filters() {
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method is outdated and will be removed in the future.', 'embed-privacy' ),
			'1.3.5'
		);
		
		if ( ! Elementor::is_used() ) {
			return;
		}
		
		// doesn't currently run with YouTube
		// see https://github.com/elementor/elementor/issues/14276
		\add_filter( 'oembed_result', [ $this, 'replace_embeds' ], 10, 3 );
	}
	
	/**
	 * Get an embed provider by its name.
	 * 
	 * @deprecated	Use epiphyt\Embed_Privacy\Provider::get_by_name() instead
	 * @since		1.3.5
	 * 
	 * @param	string	$name The name to search for
	 * @return	\WP_Post|null The embed or null
	 */
	public function get_embed_by_name( $name ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Provider::get_by_name()',
			),
			'1.10.0'
		);
		
		return Provider::get_instance()->get_by_name( $name );
	}
	
	/**
	 * Get an embed provider overlay.
	 * 
	 * @since	1.3.5
	 * @since	1.10.0 Method is now public
	 * 
	 * @param	\WP_Post	$provider An embed provider
	 * @param	string		$content The content
	 * @return	string The content with additional overlays of an embed provider
	 */
	public function get_embed_overlay( $provider, $content ) {
		if ( Provider::is_always_active( $provider->post_name ) ) {
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\Provider::get_list() instead
	 * @since		1.3.0
	 * @since		1.8.0 Added the $args parameter
	 * 
	 * @param	string	$type The embed type
	 * @param	array	$args Additional arguments
	 * @return	array A list of embeds
	 */
	public function get_embeds( $type = 'all', $args = [] ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Provider::get_list()',
			),
			'1.10.0'
		);
		
		return Provider::get_instance()->get_list( $type, $args );
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\integration\Twitter::get_local_tweet() instead
	 * @since		1.3.0
	 * 
	 * @param	string	$html Embed code
	 * @return	string Local embed
	 */
	private function get_local_tweet( $html ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\integration\Twitter::get_local_tweet()',
			),
			'1.10.0'
		);
		
		return Twitter::get_local_tweet( $html );
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\embed\Template::get() instead
	 * @since		1.1.0
	 * 
	 * @param	string	$embed_provider The embed provider
	 * @param	string	$embed_provider_lowercase The embed provider without spaces and in lowercase
	 * @param	string	$output The output before replacing it
	 * @param	array	$args Additional arguments
	 * @return	string The overlay template
	 */
	public function get_output_template( $embed_provider, $embed_provider_lowercase, $output, $args = [] ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\embed\Template::get()',
			),
			'1.10.0'
		);
		
		return Template::get( $embed_provider, $embed_provider_lowercase, $output, $args );
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
		
		/**
		 * Filter whether to ignore this embed.
		 * 
		 * @since	1.9.0
		 * 
		 * @param	bool	$ignore_embed Whether to ignore this embed
		 * @param	string	$content The original content
		 * @param	string	$embed_provider The embed provider
		 * @param	string	$embed_provider_lowercase The embed provider without spaces and in lowercase
		 * @param	array	$args Additional arguments
		 */
		$ignore_embed = (bool) \apply_filters( 'embed_privacy_ignore_embed', false, $content, $embed_provider, $embed_provider_lowercase, $args );
		
		if ( $ignore_embed ) {
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
			$providers = Provider::get_instance()->get_list();
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
				if ( $embed_provider_lowercase && $args['check_always_active'] && Provider::is_always_active( $embed_provider_lowercase ) ) {
					if ( ! empty( $args['assets'] ) ) {
						$content = Assets::get_static( $args['assets'], $content );
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
					if ( $args['check_always_active'] && Provider::is_always_active( $embed_provider_lowercase ) ) {
						if ( ! empty( $args['assets'] ) ) {
							$content = Assets::get_static( $args['assets'], $content );
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
				$args['embed_title'] = $element->hasAttribute( 'title' ) ? $element->getAttribute( 'title' ) : '';
				$args['embed_url'] = $element->getAttribute( $args['element_attribute'] );
				$args['height'] = $element->hasAttribute( 'height' ) ? $element->getAttribute( 'height' ) : 0;
				$args['width'] = $element->hasAttribute( 'width' ) ? $element->getAttribute( 'width' ) : 0;
				
				// get overlay template as DOM element
				$template_dom->loadHTML(
					'<html><meta charset="utf-8">' . str_replace( '%', '%_epi_', Template::get( $embed_provider, $embed_provider_lowercase, $dom->saveHTML( $element ), $args ) ) . '</html>',
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
				
				$content = $dom->saveHTML( $dom->documentElement );
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
			$provider = Provider::get_instance()->get_by_name( $embed_provider_lowercase );
			
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
					$content = \preg_replace( $args['regex'], Template::get( $embed_provider, $embed_provider_lowercase, $matches[0], $args ), $content, 1 );
				}
			}
		}
		
		// decode to make sure there is nothing left encoded if replacements have been made
		// otherwise, content is untouched by DOMDocument, and we don't need a decoding
		// only required for WPBakery Page Builder
		if ( ! empty( $this->did_replacements ) && \str_contains( 'vc_row', $content ) ) {
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
	 * Get dynamically generated style.
	 * 
	 * @deprecated	1.10.0
	 * 
	 * @return	string Dynamically generated style
	 */
	public function get_style() {
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method has no more functionality.', 'embed-privacy' ),
			'1.10.0'
		);
		
		/**
		 * Filter the style properties before generating dynamic styles.
		 * 
		 * @deprecated	1.10.0
		 * @since		1.9.0
		 * 
		 * @param	array $style_properties Style properties array
		 */
		$this->style = (array) \apply_filters_deprecated( 'embed_privacy_dynamic_style_properties', $this->style, '1.10.0' );
		
		/**
		 * Filter dynamic generated style.
		 * 
		 * @deprecated	1.10.0
		 * @since	1.9.0
		 * 
		 * @param	string	$style Generated style
		 * @param	array	$style_properties Style properties array
		 */
		$style = \apply_filters_deprecated( 'embed_privacy_dynamic_style', '', $this->style, '1.10.0' );
		
		return $style;
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
		
		$embed_providers = Provider::get_instance()->get_list();
		
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
	 * Check if a provider is always active.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\Provider::is_always_active() instead
	 * @since		1.1.0
	 * 
	 * @param	string		$provider The embed provider in lowercase
	 * @return	bool True if provider is always active, false otherwise
	 */
	public function is_always_active_provider( $provider ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Provider::is_always_active()',
			),
			'1.10.0'
		);
		
		return Provider::get_instance()::is_always_active( $provider );
	}
	
	/**
	 * Check if a post is written in Elementor.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\integration\Elementor::is_used() instead
	 * @since		1.3.5
	 * 
	 * @return	bool True if Elementor is used, false otherwise
	 */
	public function is_elementor() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\integration\Elementor::is_used()',
			),
			'1.10.0'
		);
		
		return Elementor::is_used();
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
	 * Callback for the page output buffer.
	 * 
	 * @deprecated	1.10.0
	 * 
	 * @param	string	$buffer Current buffer
	 * @return	string Updated buffer
	 */
	public function output_buffer_callback( $buffer ) {
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method has no more functionality.', 'embed-privacy' ),
			'1.10.0'
		);
		
		return $buffer;
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
		
		if ( ! \function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . WPINC . '/plugin.php';
		}
		
		/**
		 * Fires after assets are printed.
		 * 
		 * @since	1.10.0
		 */
		\do_action( 'embed_privacy_print_assets' );
		
		$this->is_printed = true;
	}
	
	/**
	 * Print assets of an embed before the content.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\embed\Assets::get_static() instead
	 * @since		1.4.5
	 * 
	 * @param	array	$assets List of assets
	 * @param	string	$output The output
	 * @return	string The updated output
	 */
	private function print_embed_assets( $assets, $output ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\embed\Assets::get_static()',
			),
			'1.10.0'
		);
		
		return Assets::get_static( $assets ) . $output;
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
		
		if ( ! Amp::is_amp() ) {
			$js_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/embed-privacy' . $suffix . '.js';
			$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/js/embed-privacy' . $suffix . '.js' ) : \EMBED_PRIVACY_VERSION;
			
			\wp_register_script( 'embed-privacy', $js_file_url, [], $file_version, [ 'strategy' => 'defer' ] );
		}
		
		/**
		 * Fires after assets have been registered.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	bool	$is_debug Whether debug mode is enabled
		 * @param	string	$suffix A filename suffix
		 */
		\do_action( 'embed_privacy_register_assets', $is_debug, $suffix );
		
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\integration\Polylang::register_post_type() instead
	 * @since		1.5.0
	 * 
	 * @param	array	$post_types List of current translatable custom post types
	 * @param	bool	$is_settings Whether the current page is the settings page
	 * @return	array Updated list of translatable custom post types
	 */
	public function register_polylang_post_type( array $post_types, $is_settings ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\integration\Polylang::register_post_type()',
			),
			'1.10.0'
		);
		
		return Polylang::register_post_type( $post_types, $is_settings );
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
		
		if ( $this->is_ignored_request ) {
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
		$embed_providers = Provider::get_instance()->get_list();
		
		foreach ( $embed_providers as $provider ) {
			$content = $this->get_embed_overlay( $provider, $content );
		}
		
		/**
		 * Filter the content after it has been replaced with an overlay.
		 * 
		 * @since	1.10.10
		 * 
		 * @param	string	$content Replaced content
		 * @param	array	$embed_providers List of embed providers
		 */
		$content = (string) \apply_filters( 'embed_privacy_replaced_content', $content, $embed_providers );
		
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
				|| ! Provider::is_always_active( 'youtube' )
			)
		) {
			$new_content = $this->get_single_overlay( $content, '', '', [ 'check_always_active' => true ] );
			
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
		
		if ( $this->is_ignored_request ) {
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
		$embed_providers = Provider::get_instance()->get_list();
		
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
			return Twitter::get_local_tweet( $output );
		}
		
		// check if cookie is set
		if ( $embed_provider_lowercase !== 'default' && Provider::is_always_active( $embed_provider_lowercase ) ) {
			return $output;
		}
		
		$embed_title = $this->get_oembed_title( $output );
		/* translators: embed title */
		$args['embed_title'] = ! empty( $embed_title ) ? $embed_title : '';
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
		
		$this->print_assets();
		
		// add two click to markup
		return Template::get( $embed_provider, $embed_provider_lowercase, $output, $args );
	}
	
	/**
	 * Replace embeds in Divi Builder.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\integration\Divi::replace() instead
	 * @since		1.2.0
	 * @since		1.6.0 Deprecated second parameter
	 * 
	 * @param	string	$item_embed The original output
	 * @param	string	$url The URL of the embed
	 * @return	string The updated embed code
	 */
	public function replace_embeds_divi( $item_embed, $url ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\integration\Divi::replace()',
			),
			'1.10.0'
		);
		
		return ( new Divi() )->replace( $item_embed, $url );
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
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method is outdated and will be removed in the future.', 'embed-privacy' ),
			'1.6.3'
		);
		
		// do nothing in admin
		if ( ! $this->usecache ) {
			return $output;
		}
		
		$provider = Provider::get_instance()->get_by_name( 'twitter' );
		
		if ( ! \preg_match( \get_post_meta( $provider->ID, 'regex_default', true ), $url ) ) {
			return $output;
		}
		
		if ( Provider::is_always_active( $provider->post_name ) ) {
			return $output;
		}
		
		if ( \get_option( 'embed_privacy_local_tweets' ) ) {
			// check for local tweets
			return Twitter::get_local_tweet( $output );
		}
		
		$args['embed_url'] = $url;
		$args['ignore_aspect_ratio'] = true;
		$args['strip_newlines'] = true;
		
		return Template::get( $provider->post_title, $provider->post_name, $output, $args );
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
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Embed_Privacy::get_embed_overlay()',
			),
			'1.2.0'
		);
		\preg_match_all( self::IFRAME_REGEX, $content, $matches );
		
		if ( empty( $matches ) || empty( $matches[0] ) ) {
			return $content;
		}
		
		$embed_provider = 'Google Maps';
		$embed_provider_lowercase = 'google-maps';
		
		// check if cookie is set
		if ( Provider::is_always_active( $embed_provider_lowercase ) ) {
			return $content;
		}
		
		foreach ( $matches[0] as $match ) {
			if ( \strpos( $match, 'google.com/maps' ) === false ) {
				continue;
			}
			
			$overlay_output = Template::get( $embed_provider, $embed_provider_lowercase, $match );
			$content = \str_replace( $match, $overlay_output, $content );
		}
		
		return $content;
	}
	
	/**
	 * Replace Maps Marker (Pro) shortcodes.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\integration\Maps_Marker::replace() instead
	 * @since		1.5.0
	 * 
	 * @param	string	$output Shortcode output
	 * @param	string	$tag Shortcode tag
	 * @return	string Updated shortcode output
	 */
	public function replace_maps_marker( $output, $tag ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\integration\Maps_Marker::replace()',
			),
			'1.10.0'
		);
		
		return Maps_Marker::replace( $output, $tag );
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
	 * Check whether this request should be ignored by Embed Privacy.
	 * 
	 * @since	1.10.0
	 */
	public function set_ignored_request() {
		/**
		 * Filter whether the current request should be ignored.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	bool	$is_ignored_request Whether the current request should be ignored
		 */
		$this->is_ignored_request = (bool) \apply_filters( 'embed_privacy_is_ignored_request', $this->is_ignored_request );
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
		$embed_providers = Provider::get_instance()->get_list();
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
			$output .= '<label class="embed-privacy-opt-out-label" for="embed-privacy-provider-' . \esc_attr( $provider->post_name ) . '-' . $microtime . '" data-embed-provider="' . \esc_attr( $provider->post_name ) . '">';
			$output .= '<input type="checkbox" id="embed-privacy-provider-' . \esc_attr( $provider->post_name ) . '-' . $microtime . '" ' . \checked( $is_checked, true, false ) . ' class="embed-privacy-opt-out-input" data-embed-provider="' . \esc_attr( $provider->post_name ) . '"> ';
			$output .= \sprintf(
				/* translators: embed provider title */
				\esc_html__( 'Load all embeds from %s', 'embed-privacy' ),
				\esc_html( $provider->post_title )
			);
			$output .= '</label><br>' . \PHP_EOL;
			$output .= '</span>' . \PHP_EOL;
		}
		
		$output .= '</div>' . \PHP_EOL;
		
		return $output;
	}
	
	/**
	 * Start an output buffer.
	 * 
	 * @deprecated	1.10.0
	 */
	public function start_output_buffer() {
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method has no more functionality.', 'embed-privacy' ),
			'1.10.0'
		);
	}
}
