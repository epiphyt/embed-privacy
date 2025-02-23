<?php
namespace epiphyt\Embed_Privacy;

use epiphyt\Embed_Privacy\admin\Fields;
use epiphyt\Embed_Privacy\admin\Settings;
use epiphyt\Embed_Privacy\admin\User_Interface;
use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\data\Replacer;
use epiphyt\Embed_Privacy\embed\Replacement;
use epiphyt\Embed_Privacy\embed\Template;
use epiphyt\Embed_Privacy\handler\Post;
use epiphyt\Embed_Privacy\handler\Shortcode;
use epiphyt\Embed_Privacy\handler\Theme;
use epiphyt\Embed_Privacy\handler\Widget;
use epiphyt\Embed_Privacy\integration\Activitypub;
use epiphyt\Embed_Privacy\integration\Amp;
use epiphyt\Embed_Privacy\integration\Astra;
use epiphyt\Embed_Privacy\integration\Divi;
use epiphyt\Embed_Privacy\integration\Elementor;
use epiphyt\Embed_Privacy\integration\Instagram_Feed;
use epiphyt\Embed_Privacy\integration\Jetpack;
use epiphyt\Embed_Privacy\integration\Kadence_Blocks;
use epiphyt\Embed_Privacy\integration\Maps_Marker;
use epiphyt\Embed_Privacy\integration\Polylang;
use epiphyt\Embed_Privacy\integration\Shortcodes_Ultimate;
use epiphyt\Embed_Privacy\integration\X;
use epiphyt\Embed_Privacy\thumbnail\Thumbnail;
use ReflectionMethod;

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
	 * @since	1.10.0
	 * @var		\epiphyt\Embed_Privacy\Frontend
	 */
	public $frontend;
	
	/**
	 * @since	1.3.0
	 * @var		bool Whether the current request has any embed processed by Embed Privacy
	 */
	public $has_embed = false;
	
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
		Instagram_Feed::class,
		Jetpack::class,
		Kadence_Blocks::class,
		Maps_Marker::class,
		Polylang::class,
		Shortcodes_Ultimate::class,
		X::class,
	];
	
	/**
	 * @since	1.10.0
	 * @var		bool Whether the current request should be ignored
	 */
	public $is_ignored_request = false;
	
	/**
	 * @var		\epiphyt\Embed_Privacy\Embed_Privacy
	 */
	public static $instance;
	
	/**
	 * @deprecated	1.10.0 Use \EPI_EMBED_PRIVACY_FILE instead
	 * @var		string The full path to the main plugin file
	 */
	public $plugin_file = '';
	
	/**
	 * @since	1.10.0
	 * @var		\epiphyt\Embed_Privacy\handler\Shortcode
	 */
	public $shortcode;
	
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
	public $use_cache;
	
	/**
	 * @deprecated	1.2.0
	 * @var			array The supported media providers
	 */
	public $embed_providers = [ // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
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
		$this->frontend = new Frontend();
		$this->shortcode = new Shortcode();
		$this->thumbnail = new Thumbnail();
		$this->use_cache = ! \is_admin();
	}
	
	/**
	 * Initialize the class.
	 * 
	 * @since	1.2.0
	 */
	public function init() {
		// actions
		\add_action( 'init', [ $this, 'load_textdomain' ], 0 );
		\add_action( 'init', [ self::class, 'register_post_type' ], 5 );
		\add_action( 'plugins_loaded', [ $this, 'init_integrations' ] );
		\add_action( 'save_post_epi_embed', [ $this, 'preserve_backslashes' ] );
		\add_filter( 'template_include', [ $this, 'set_ignored_request_in_template_include' ], 100 );
		
		// filters
		if ( ! $this->use_cache ) {
			// set ttl to 0 in admin
			\add_filter( 'oembed_ttl', '__return_zero' );
		}
		
		Migration::get_instance()->init();
		Post::init();
		Providers::get_instance()->init();
		Settings::init();
		User_Interface::init();
		Widget::init();
		$this->fields->init();
		$this->frontend->init();
		$this->shortcode->init();
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
				( new $integration() )->init(); // phpcs:ignore NeutronStandard.Functions.VariableFunctions.VariableFunction
			}
		}
	}
	
	/**
	 * Embeds are cached in the postmeta database table and need to be removed
	 * whenever the plugin will be enabled or disabled.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Post::clear_embed_cache() instead
	 */
	public function clear_embed_cache() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\handler\Post::clear_embed_cache()'
			),
			'1.10.0'
		);
		Post::clear_embed_cache();
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
	 * @deprecated	1.4.4 Use epiphyt\Embed_Privacy\Frontend::print_assets() instead
	 */
	public function enqueue_assets() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Frontend::print_assets()'
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\data\Providers::get_by_name() instead
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
				'epiphyt\Embed_Privacy\data\Providers::get_by_name()'
			),
			'1.10.0'
		);
		
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\embed\Replacement::get() instead
	 * @since		1.3.5
	 * 
	 * @param	\WP_Post	$provider An embed provider
	 * @param	string		$content The content
	 * @return	string The content with additional overlays of an embed provider
	 */
	public function get_embed_overlay( $provider, $content ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\embed\Replacement::get()'
			),
			'1.10.0'
		);
		
		$replacement = new Replacement( $content );
		
		foreach ( $replacement->get_providers() as $provider ) {
			$content = Template::get( $provider, $replacement );
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\data\Providers::get_list() instead
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
				'epiphyt\Embed_Privacy\data\Providers::get_list()'
			),
			'1.10.0'
		);
		
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
					'meta_query' => [ // phpcs:ignore SlevomatCodingStandard.Arrays.DisallowPartiallyKeyed.DisallowedPartiallyKeyed
						'relation' => 'OR',
						[ // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found, Universal.Arrays.MixedArrayKeyTypes.ImplicitNumericKey
							'compare' => 'NOT EXISTS',
							'key' => 'is_system',
							'value' => 'yes',
						],
						[ // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found, Universal.Arrays.MixedArrayKeyTypes.ImplicitNumericKey
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\Shortcode::get_ignored() instead
	 * @since		1.6.0
	 * 
	 * @return	string[] List with ignored shortcodes
	 */
	public function get_ignored_shortcodes() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Shortcode::get_ignored()'
			),
			'1.10.0'
		);
		
		return $this->shortcode->get_ignored();
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
				'epiphyt\Embed_Privacy\embed\Template::get()'
			),
			'1.10.0'
		);
		
		return Template::get( $embed_provider, $embed_provider_lowercase, $output, $args );
	}
	
	/**
	 * Get a single overlay for all matching embeds.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\embed\Replacement::get() instead
	 * @since		1.2.0
	 * 
	 * @param	string	$content The original content
	 * @param	string	$embed_provider The embed provider
	 * @param	string	$embed_provider_lowercase The embed provider without spaces and in lowercase
	 * @param	array	$args Additional arguments
	 * @return	string The updated content
	 */
	public function get_single_overlay( $content, $embed_provider, $embed_provider_lowercase, $args ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\embed\Replacement::get()'
			),
			'1.10.0'
		);
		
		$overlay = new Replacement( $content );
		
		return $overlay->get( $args );
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
		$style = \apply_filters_deprecated( 'embed_privacy_dynamic_style', '', $this->style, '1.10.0' ); // phpcs:ignore SlevomatCodingStandard.Variables.UselessVariable.UselessVariable
		
		return $style;
	}
	
	/**
	 * Check if a post contains an embed.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Post::has_embed() instead
	 * @since		1.3.0
	 * 
	 * @param	\WP_Post|int|null	$post A post object, post ID or null
	 * @return	bool True if a post contains an embed, false otherwise
	 */
	public function has_embed( $post = null ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\handler\Post::has_embed()'
			),
			'1.10.0'
		);
		
		return Post::has_embed( $post );
	}
	
	/**
	 * Check if a provider is always active.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\data\Providers::is_always_active() instead
	 * @since		1.1.0
	 * 
	 * @param	string	$provider The embed provider in lowercase
	 * @return	bool True if provider is always active, false otherwise
	 */
	public function is_always_active_provider( $provider ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\data\Providers::is_always_active()'
			),
			'1.10.0'
		);
		
		return Providers::is_always_active( $provider );
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
				'epiphyt\Embed_Privacy\integration\Elementor::is_used()'
			),
			'1.10.0'
		);
		
		return Elementor::is_used();
	}
	
	/**
	 * Check if the current theme is matching your name.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Theme::is() instead
	 * @since		1.3.5
	 * 
	 * @param	string	$name The theme name to test
	 * @return	bool True if the current theme is matching, false otherwise
	 */
	public function is_theme( $name ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\handler\Theme::is()'
			),
			'1.10.0'
		);
		
		return Theme::is( $name );
	}
	
	/**
	 * Get the WP_Filesystem object
	 * 
	 * @return	\WP_Filesystem_Direct WP_Filesystem object
	 */
	public static function get_wp_filesystem() {
		/** @var	\WP_Filesystem_Direct $wp_filesystem */
		global $wp_filesystem;
		
		// initialize the WP filesystem if not exists
		if ( empty( $wp_filesystem ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
			
			\WP_Filesystem();
		}
		
		return $wp_filesystem;
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\Frontend::print_assets() instead
	 * @since		1.3.0
	 */
	public function print_assets() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Frontend::print_assets()'
			),
			'1.10.0'
		);
		$this->frontend->print_assets();
	}
	
	/**
	 * Register our assets for the frontend.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\Frontend::register_assets() instead
	 * @since		1.4.4
	 */
	public function register_assets() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Frontend::register_assets()'
			),
			'1.10.0'
		);
		$this->frontend->register_assets();
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
				'epiphyt\Embed_Privacy\integration\Polylang::register_post_type()'
			),
			'1.10.0'
		);
		
		return Polylang::register_post_type( $post_types, $is_settings );
	}
	
	/**
	 * Register post type.
	 * 
	 * @since	1.10.0
	 */
	public static function register_post_type() {
		\register_post_type(
			'epi_embed',
			[ // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
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
					'pages' => false,
					'with_front' => false,
				],
			]
		);
	}
	
	/**
	 * Replace embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\data\Replacer::replace_embeds() instead
	 * @since		1.2.0 Changed behavior of the method
	 * @since		1.6.0 Added optional $tag parameter
	 * 
	 * @param	string	$content The original content
	 * @param	string	$tag The shortcode tag if called via do_shortcode
	 * @return	string The updated content
	 */
	public function replace_embeds( $content, $tag = '' ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\data\Replacer::replace_embeds()'
			),
			'1.10.0'
		);
		
		return Replacer::replace_embeds( $content, $tag );
	}
	
	/**
	 * Replace oembed embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\data\Replacer::replace_oembed() instead
	 * @since		1.2.0
	 * 
	 * @param	string	$output The original output
	 * @param	string	$url The URL to the embed
	 * @param	array	$args Additional arguments of the embed
	 * @return	string The updated embed code
	 */
	public function replace_embeds_oembed( $output, $url, $args ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\data\Replacer::replace_oembed()'
			),
			'1.10.0'
		);
		
		return Replacer::replace_oembed( $output, $url, $args );
	}
	
	/**
	 * Replace embeds in Divi Builder.
	 * 
	 * @deprecated	1.10.0
	 * @since		1.2.0
	 * @since		1.6.0 Deprecated second parameter
	 * 
	 * @param	string	$item_embed The original output
	 * @param	string	$url The URL of the embed
	 * @return	string The updated embed code
	 */
	public function replace_embeds_divi( $item_embed, $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method is outdated and will be removed in the future.', 'embed-privacy' ),
			'1.10.0'
		);
		
		return $item_embed;
	}
	
	/**
	 * Replace X embeds.
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
		if ( ! $this->use_cache ) {
			return $output;
		}
		
		$provider = Providers::get_instance()->get_by_name( 'x' );
		
		if ( ! $provider->is_matching( $url ) ) {
			return $output;
		}
		
		// check for local tweets
		if ( \get_option( 'embed_privacy_local_tweets' ) ) {
			return X::get_local_tweet( $output );
		}
		
		$args['embed_url'] = $url;
		$args['ignore_aspect_ratio'] = true;
		$args['strip_newlines'] = true;
		
		return Template::get( $provider, $output, $args );
	}
	
	/**
	 * Replace Google Maps iframes.
	 * 
	 * @deprecated	1.2.0 Use epiphyt\Embed_Privacy\embed\Replacement::get() instead
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
				'epiphyt\Embed_Privacy\embed\Replacement::get()'
			),
			'1.2.0'
		);
		
		$overlay = new Replacement( $content );
		
		return $overlay->get();
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
				'epiphyt\Embed_Privacy\integration\Maps_Marker::replace()'
			),
			'1.10.0'
		);
		
		return Maps_Marker::replace( $output, $tag );
	}
	
	/**
	 * Replace video shortcode embeds.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\data\Replacer::replace_video_shortcode() instead
	 * @since		1.7.0
	 * 
	 * @param	string	$output Video shortcode HTML output
	 * @param	array	$atts Array of video shortcode attributes
	 * @return	string Updated embed code
	 */
	public function replace_video_shortcode( $output, $atts ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\data\Replacer::replace_video_shortcode()'
			),
			'1.10.0'
		);
		
		return Replacer::replace_video_shortcode( $output, $atts );
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
	 * @since	1.10.0 Method is now public
	 * 
	 * @param	array		$checks A list of checks
	 * @param	\DOMElement	$element The DOM Element
	 * @return	bool Whether all checks are successful
	 */
	public function run_checks( $checks, $element ) {
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
	 * @deprecated	1.10.10 Use epiphyt\Embed_Privacy:\Embed_Privacy:set_ignored_request_in_template_include() instead
	 * @since	1.10.0
	 */
	public function set_ignored_request() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Embed_Privacy::set_ignored_request_in_template_include()'
			),
			'1.10.10'
		);
		
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
	 * Check whether this request should be ignored by Embed Privacy.
	 * Template inclusion
	 * 
	 * @since	1.10.10
	 * 
	 * @param	string	$template Template path to include
	 * @return	string $template Template path to include
	 */
	public function set_ignored_request_in_template_include( $template ) {
		/**
		 * Filter whether the current request should be ignored.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	bool	$is_ignored_request Whether the current request should be ignored
		 */
		$this->is_ignored_request = (bool) \apply_filters( 'embed_privacy_is_ignored_request', $this->is_ignored_request );
		
		return $template;
	}
	
	/**
	 * Set the plugin file.
	 * 
	 * @deprecated	1.10.0 Use \EPI_EMBED_PRIVACY_FILE instead
	 * @since		1.1.0
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
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy:\Embed_Privacy:register_post_type() instead
	 * @since	1.2.0
	 */
	public function set_post_type() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\Embed_Privacy::register_post_type()'
			),
			'1.10.0'
		);
		self::register_post_type();
	}
	
	/**
	 * Display an Opt-out shortcode.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Shortcode::opt_out() instead
	 * @since		1.2.0
	 * 
	 * @param	array	$attributes Shortcode attributes
	 * @return	string The shortcode output
	 */
	public function shortcode_opt_out( $attributes ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\handler\Shortcode::opt_out()'
			),
			'1.10.0'
		);
		
		return Shortcode::opt_out( $attributes );
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
