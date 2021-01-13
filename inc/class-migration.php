<?php
namespace epiphyt\Embed_Privacy;
use function __;
use function _x;
use function add_action;
use function dirname;
use function file_exists;
use function get_option;
use function get_site_option;
use function get_sites;
use function is_int;
use function is_multisite;
use function is_plugin_active_for_network;
use function load_plugin_textdomain;
use function plugin_basename;
use function plugin_dir_path;
use function register_activation_hook;
use function restore_current_blog;
use function sanitize_title;
use function set_post_thumbnail;
use function sprintf;
use function switch_to_blog;
use function update_option;
use function update_site_option;
use function WP_Filesystem;
use function wp_insert_post;

/**
 * Migration class to update data in the database on upgrades.
 * 
 * @since	1.2.0
 *
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Migration {
	/**
	 * @var		\epiphyt\Embed_Privacy\Migration
	 */
	private static $instance;
	
	/**
	 * Post Type constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 */
	public function init() {
		// since upgrader_process_complete hook runs the old plugin code,
		// it is useless for real migrations, unfortunately
		// thus, we need to check on every page load in the admin if there are
		// new migrations
		add_action( 'admin_init', [ $this, 'migrate' ] );
		
		register_activation_hook( Embed_Privacy::get_instance()->plugin_file, [ $this, 'migrate' ] );
	}
	
	/**
	 * Add an embed provider post.
	 * 
	 * @param	array					$embed Embed provider information
	 * @param	\WP_Filesystem_Direct	$wp_filesystem WordPress filesystem operation class
	 */
	private function add_embed( array $embed, $wp_filesystem ) {
		$post_id = wp_insert_post( $embed );
		
		if ( is_int( $post_id ) ) {
			// $post->post_name could contain a counter, which we don't want here
			$post_name = sanitize_title( $embed['post_title'] );
			
			// upload logo
			if ( file_exists( plugin_dir_path( Embed_Privacy::get_instance()->plugin_file ) . 'assets/images/embed-' . $post_name . '.png' ) ) {
				$attachment_id = Fields::get_instance()->upload_file( [
					'content' => $wp_filesystem->get_contents( plugin_dir_path( Embed_Privacy::get_instance()->plugin_file ) . 'assets/images/embed-' . $post_name . '.png' ),
					'name' => 'embed-' . $post_name . '.png',
				] );
				
				if ( is_int( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}
		}
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Embed_Privacy\Migration The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Get an option either from the global multisite settings or the regular site.
	 * 
	 * @param	string	$option The option name
	 * @param	mixed	$default The default value if the option is not set
	 * @return	mixed Value set for the option
	 */
	private function get_option( $option, $default = false ) {
		if ( is_multisite() && is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) {
			return get_site_option( 'embed_privacy_' . $option, $default );
		}
		
		return get_option( 'embed_privacy_' . $option, $default );
	}
	
	/**
	 * Run migrations.
	 */
	public function migrate() {
		// load textdomain early for migrations
		load_plugin_textdomain( 'embed-privacy', false, dirname( plugin_basename( Embed_Privacy::get_instance()->plugin_file ) ) . '/languages' );
		
		$version = $this->get_option( 'migrate_version', 'initial' );
		
		switch ( $version ) {
			case '1.2.0':
				// most recent version, do nothing
				break;
			default:
				// run all migrations
				$this->migrate_1_2_0();
				break;
		}
	}
	
	/**
	 * Migrations for version 1.2.0.
	 */
	private function migrate_1_2_0() {
		global $wp_filesystem;
		
		// initialize the WP filesystem if not exists
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			WP_Filesystem();
		}
		
		$embeds = [
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.amazon.com/gp/help/customer/display.html?nodeId=GX7NJQ4ZB8MHFRNJ', 'embed-privacy' ),
					'regex_default' => '/\\\.?(ama?zo?n\\\.|a\\\.co|z\\\.cn)/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Amazon Kindle', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Amazon Kindle', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://animoto.com/legal/privacy_policy', 'embed-privacy' ),
					'regex_default' => '/animoto\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Animoto', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Animoto', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/cloudup\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Cloudup', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Cloudup', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/((poll(\\\.fm|daddy\\\.com))|croudsignal\\\.com|survey\\\.fm)/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Crowdsignal', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Crowdsignal', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.dailymotion.com/legal/privacy?localization=en', 'embed-privacy' ),
					'regex_default' => '/dailymotion\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'DailyMotion', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'DailyMotion', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.facebook.com/privacy/explanation', 'embed-privacy' ),
					'regex_default' => '/facebook\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Facebook', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Facebook', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.flickr.com/help/privacy', 'embed-privacy' ),
					'regex_default' => '/flickr\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Flickr', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Flickr', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.funnyordie.com/legal/privacy-notice', 'embed-privacy' ),
					'regex_default' => '/funnyordie\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Funny Or Die', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Funny Or Die', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://policies.google.com/privacy?hl=en', 'embed-privacy' ),
					'regex_default' => '/google\\\.com\\\/maps/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Google Maps', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Google Maps', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://imgur.com/privacy', 'embed-privacy' ),
					'regex_default' => '/imgur\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Imgur', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Imgur', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.instagram.com/legal/privacy/', 'embed-privacy' ),
					'regex_default' => '/instagram\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Instagram', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Instagram', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://issuu.com/legal/privacy', 'embed-privacy' ),
					'regex_default' => '/issuu\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Issuu', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Issuu', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.kickstarter.com/privacy', 'embed-privacy' ),
					'regex_default' => '/kickstarter\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Kickstarter', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Kickstarter', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.meetup.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/meetup\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Meetup', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Meetup', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.mixcloud.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/mixcloud\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Mixcloud', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Mixcloud', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://app.photobucket.com/privacy', 'embed-privacy' ),
					'regex_default' => '/photobucket\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Photobucket', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Photobucket', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.reddit.com/help/privacypolicy', 'embed-privacy' ),
					'regex_default' => '/reddit\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Reddit', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Reddit', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.reverbnation.com/privacy', 'embed-privacy' ),
					'regex_default' => '/reverbnation\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'ReverbNation', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'ReverbNation', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://scribd.com/privacy', 'embed-privacy' ),
					'regex_default' => '/scribd\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Scribd', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Scribd', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://sketchfab.com/privacy', 'embed-privacy' ),
					'regex_default' => '/sketchfab\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Sketchfab', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Sketchfab', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.slideshare.net/privacy', 'embed-privacy' ),
					'regex_default' => '/slideshare\\\.net/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'SlideShare', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'SlideShare', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.smugmug.com/about/privacy', 'embed-privacy' ),
					'regex_default' => '/smugmug\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'SmugMug', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'SmugMug', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://soundcloud.com/pages/privacy', 'embed-privacy' ),
					'regex_default' => '/soundcloud\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'SoundCloud', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'SoundCloud', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://speakerdeck.com/privacy', 'embed-privacy' ),
					'regex_default' => '/speakerdeck\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Speaker Deck', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Speaker Deck', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.spotify.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/spotify\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Spotify', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Spotify', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.tiktok.com/legal/privacy-policy?lang=en-US', 'embed-privacy' ),
					'regex_default' => '/tiktok\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'TikTok', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'TikTok', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.ted.com/about/our-organization/our-policies-terms/privacy-policy', 'embed-privacy' ),
					'regex_default' => '/ted\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'TED', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'TED', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.tumblr.com/privacy_policy', 'embed-privacy' ),
					'regex_default' => '/tumblr\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Tumblr', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Tumblr', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://twitter.com/privacy', 'embed-privacy' ),
					'regex_default' => '/twitter\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Twitter', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Twitter', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/videopress\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'VideoPress', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'VideoPress', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://vimeo.com/privacy', 'embed-privacy' ),
					'regex_default' => '/vimeo\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Vimeo', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Vimeo', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://wordpress.org/about/privacy/', 'embed-privacy' ),
					'regex_default' => '/wordpress\\\.org\\\/plugins/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'WordPress.org', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'WordPress.org', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://wordpress.org/about/privacy/', 'embed-privacy' ),
					'regex_default' => '/wordpress\\\.tv\/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'WordPress.tv', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'WordPress.tv', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://policies.google.com/privacy?hl=en', 'embed-privacy' ),
					'regex_default' => '/https?:\\\/\\\/(?:.+?.)?youtu(?:.be|be.com)/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'YouTube', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'YouTube', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
		];
		
		// add embeds
		if ( is_multisite() && is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) {
			$sites = get_sites( [
				'number' => 10000,
			] );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				
				foreach ( $embeds as $embed ) {
					$this->add_embed( $embed, $wp_filesystem );
				}
			}
			
			restore_current_blog();
		}
		else {
			foreach ( $embeds as $embed ) {
				$this->add_embed( $embed, $wp_filesystem );
			}
		}
		
		$this->update_option( 'migrate_version', '1.2.0' );
	}
	
	/**
	 * Update an option either from the global multisite settings or the regular site.
	 * 
	 * @param	string	$option The option name
	 * @param	mixed	$value The value to update
	 * @return	mixed Value set for the option
	 */
	private function update_option( $option, $value ) {
		if ( is_multisite() && is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) {
			return update_site_option( 'embed_privacy_' . $option, $value );
		}
		
		return update_option( 'embed_privacy_' . $option, $value );
	}
}
