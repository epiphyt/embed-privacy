<?php
namespace epiphyt\Embed_Privacy;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\thumbnail\Thumbnail;
use FilesystemIterator;
use WP_Post;

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
	 * @var		array Default embed providers
	 */
	private $providers = [];
	
	/**
	 * @var		string Current migration version
	 * @since	1.2.2
	 */
	private $version = '1.11.0';
	
	/**
	 * Migration constructor.
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
		\add_action( 'admin_init', [ $this, 'migrate' ], 10, 0 );
		\add_action( 'admin_notices', [ $this, 'register_migration_failed_notice' ] );
	}
	
	/**
	 * Add an embed provider post.
	 * 
	 * @param	array	$embed Embed provider information
	 */
	private function add_embed( array $embed ) {
		// since meta_input doesn't work on every multisite (I don't know why)
		// extract metadata and use add_post_meta() afterwards
		// see: https://github.com/epiphyt/embed-privacy/issues/14
		if ( ! empty( $embed['meta_input'] ) ) {
			$meta_data = $embed['meta_input'];
			unset( $embed['meta_input'] );
		}
		
		$post_id = \wp_insert_post( $embed );
		
		// add meta data
		if ( \is_int( $post_id ) && isset( $meta_data ) ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				\add_post_meta( $post_id, $meta_key, $meta_value );
			}
		}
	}
	
	/**
	 * Create thumbnails directory.
	 * 
	 * @since	1.5.0
	 */
	private function create_thumbnails_dir() {
		$directory = Thumbnail::get_directory();
		
		if ( empty( $directory['base_dir'] ) ) {
			return;
		}
		
		if ( \file_exists( $directory['base_dir'] ) && ! \is_dir( $directory['base_dir'] ) ) {
			return;
		}
		
		if ( ! \is_dir( $directory['base_dir'] ) ) {
			\wp_mkdir_p( $directory['base_dir'] );
		}
	}
	
	/**
	 * Delete an option either from the global multisite settings or the regular site.
	 * 
	 * @param	string	$option The option name
	 * @return	bool True if the option was deleted, false otherwise
	 */
	private function delete_option( $option ) {
		return \delete_option( 'embed_privacy_' . $option );
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
	 * @param	mixed	$default_value The default value if the option is not set
	 * @return	mixed Value set for the option
	 */
	private function get_option( $option, $default_value = false ) {
		return \get_option( 'embed_privacy_' . $option, $default_value );
	}
	
	/**
	 * Run migrations.
	 * 
	 * @param	null	$deprecated Deprecated, has no function anymore
	 * @param	null	$deprecated2 Deprecated, has no function anymore
	 */
	public function migrate( $deprecated = null, $deprecated2 = null ) {
		if ( $deprecated !== null ) {
			\_doing_it_wrong(
				__METHOD__,
				\sprintf(
					/* translators: parameter */
					\esc_html__( 'The function does not support the parameter "%s" anymore.', 'embed-privacy' ),
					'$plugin'
				),
				'1.4.0'
			);
		}
		
		if ( $deprecated2 !== null ) {
			\_doing_it_wrong(
				__METHOD__,
				\sprintf(
					/* translators: parameter */
					\esc_html__( 'The function does not support the parameter "%s" anymore.', 'embed-privacy' ),
					'$network_wide'
				),
				'1.4.0'
			);
		}
		
		if ( \wp_doing_ajax() ) {
			return;
		}
		
		// check for active migration
		if (
			\is_numeric( $this->get_option( 'is_migrating' ) )
			&& $this->get_option( 'is_migrating' ) !== '1' // possible legacy value
			&& (int) $this->get_option( 'is_migrating' ) > \time() - \MINUTE_IN_SECONDS
		) {
			return;
		}
		
		$version = $this->get_option( 'migrate_version', 'initial' );
		
		// get legacy network option
		if ( $version === 'initial' && \is_multisite() ) {
			$version = \get_site_option( 'embed_privacy_migrate_version', 'initial' );
		}
		
		if ( $version === $this->version ) {
			return;
		}
		
		// start the migration
		$this->update_option( 'is_migrating', \time() );
		$this->update_option( 'migration_count', (int) $this->get_option( 'migration_count' ) + 1 );
		// load textdomain early for migrations
		\load_plugin_textdomain( 'embed-privacy', false, \dirname( \plugin_basename( \EPI_EMBED_PRIVACY_FILE ) ) . '/languages' );
		// make sure all default embed providers are available and translated
		$this->register_default_embed_providers();
		
		switch ( $version ) {
			case '1.2.0':
				$this->migrate_1_2_1();
			case '1.2.1':
				$this->migrate_1_2_2();
			case '1.2.2':
				$this->migrate_1_3_0();
			case '1.3.0':
				$this->migrate_1_4_0();
			case '1.4.0':
				$this->migrate_1_4_7();
			case '1.4.7':
				$this->migrate_1_5_0();
			case '1.5.0':
				$this->migrate_1_6_0();
			case '1.6.0':
				$this->migrate_1_7_0();
			case '1.7.0':
				$this->migrate_1_7_3();
			case '1.7.3':
				$this->migrate_1_8_0();
			case '1.8.0':
				$this->migrate_1_10_5();
			case '1.10.5':
				$this->migrate_1_10_6();
			case '1.10.6':
			case '1.10.7':
				$this->migrate_1_10_7();
			case '1.10.9':
				$this->migrate_1_11_0();
			case $this->version:
				// most recent version, do nothing
				break;
			default:
				// run all migrations
				$this->migrate_1_2_0();
				break;
		}
		
		// migration done
		$this->update_option( 'migrate_version', $this->version );
		$this->delete_option( 'is_migrating' );
		$this->delete_option( 'migration_count' );
	}
	
	/**
	 * Migrations for version 1.2.0.
	 * 
	 * @since	1.2.0
	 * @since	1.5.0 Create thumbnails directory
	 * 
	 * - Add default embed providers
	 * - Add thumbnails directory
	 */
	private function migrate_1_2_0() {
		// add embeds
		foreach ( $this->providers as $embed ) {
			$this->add_embed( $embed );
		}
		
		$this->create_thumbnails_dir();
	}
	
	/**
	 * Migrations for version 1.2.1.
	 * 
	 * @since	1.2.1
	 * 
	 * - Add missing meta data
	 */
	private function migrate_1_2_1() {
		global $wp_filesystem;
		
		// initialize the WP filesystem if not exists
		if ( empty( $wp_filesystem ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}
		
		$available_providers = \get_posts( [
			'no_found_rows' => true,
			'numberposts' => -1,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		
		foreach ( $available_providers as $provider ) {
			// get according default provider
			$key = \array_search( $provider->post_title, \array_column( $this->providers, 'post_title' ), true );
			
			// if no default provider, continue with next available provider
			if ( $key === false ) {
				continue;
			}
			
			// update default meta data, if missing
			foreach ( [ 'is_system', 'privacy_policy_url', 'regex_default' ] as $meta_key ) {
				if ( ! \get_post_meta( $provider->ID, $meta_key, true ) ) {
					\update_post_meta( $provider->ID, $meta_key, $this->providers[ $key ]['meta_input'][ $meta_key ] );
				}
			}
		}
	}
	
	/**
	 * Migrations for version 1.2.2.
	 * 
	 * @since	1.2.2
	 * 
	 * - Update regex for Amazon Kindle
	 */
	private function migrate_1_2_2() {
		$amazon_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'amazon-kindle',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		
		if ( ! empty( $amazon_provider ) ) {
			$amazon_provider = \reset( $amazon_provider );
		}
		
		if ( ! $amazon_provider instanceof WP_Post ) {
			return;
		}
		
		\update_post_meta( $amazon_provider->ID, 'regex_default', '/\\\.?(ama?zo?n\\\.|a\\\.co\\\/|z\\\.cn\\\/)/' );
	}
	
	/**
	 * Migrations for version 1.3.0.
	 * 
	 * @since	1.3.0
	 * 
	 * - Delete post thumbnails
	 * - Update regex for Google Maps
	 */
	private function migrate_1_3_0() {
		$providers = Embed_Privacy::get_instance()->get_embeds( 'oembed' );
		$google_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'google-maps',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$providers = \array_merge( $providers, $google_provider );
		
		foreach ( $providers as $provider ) {
			if ( ! $provider instanceof WP_Post ) {
				continue;
			}
			
			// delete post thumbnails
			// see https://github.com/epiphyt/embed-privacy/issues/32
			$thumbnail_id = \get_post_thumbnail_id( $provider->ID );
			
			if ( $thumbnail_id ) {
				\delete_post_thumbnail( $provider );
				\wp_delete_attachment( $thumbnail_id, true );
			}
			
			// make regex ungreedy
			// see https://github.com/epiphyt/embed-privacy/issues/31
			if ( $provider->post_name === 'google-maps' ) {
				\update_post_meta( $provider->ID, 'regex_default', '/google\\\.com\\\/maps\\\/embed/' );
			}
		}
	}
	
	/**
	 * Migrations for version 1.4.0.
	 * 
	 * @since	1.4.0
	 * 
	 * - Replace duplicate embed providers
	 * - Add missing default embed providers (this also adds new Wolfram Cloud)
	 */
	private function migrate_1_4_0() {
		$providers = Embed_Privacy::get_instance()->get_embeds();
		$missing_providers = $this->providers;
		$processed_providers = [];
		
		foreach ( $providers as $provider ) {
			if ( ! $provider instanceof WP_Post ) {
				continue;
			}
			
			// check only system providers
			if ( \get_post_meta( $provider->ID, 'is_system', true ) !== 'yes' ) {
				continue;
			}
			
			// since post name differs, use post title to get duplicates
			if ( \in_array( $provider->post_title, $processed_providers, true ) ) {
				// delete duplicate providers
				\wp_delete_post( $provider->ID, true );
			}
			else {
				$processed_providers[] = $provider->post_title;
			}
			
			$key = false;
			
			// delete provider from list of missing providers if it already exists
			foreach ( $missing_providers as $provider_key => $missing_provider ) {
				if ( $provider->post_title === $missing_provider['post_title'] ) {
					$key = $provider_key;
					break;
				}
			}
			
			if ( $key !== false ) {
				unset( $missing_providers[ $key ] );
			}
		}
		
		// add missing default providers
		foreach ( $missing_providers as $missing_provider ) {
			$this->add_embed( $missing_provider );
		}
	}
	
	/**
	 * Migrations for version 1.4.7.
	 * 
	 * @see		https://github.com/epiphyt/embed-privacy/issues/120
	 * @since	1.4.7
	 * 
	 * - Update regex for Google Maps
	 */
	private function migrate_1_4_7() {
		$google_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'google-maps',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$google_provider = \reset( $google_provider );
		
		\update_post_meta( $google_provider->ID, 'regex_default', '/google\\\.com\\\/maps\\\/(d\\\/)?embed/' );
	}
	
	/**
	 * Migrations for version 1.5.0.
	 * 
	 * @see		https://github.com/epiphyt/embed-privacy/issues/124
	 * @since	1.5.0
	 * 
	 * - Add new embed provider Pocket Casts
	 * - Add new embed provider Maps Marker
	 * - Add thumbnails directory
	 * - Update Google Maps regex
	 */
	private function migrate_1_5_0() {
		$this->add_embed( [
			'meta_input' => [
				'is_system' => 'yes',
				'privacy_policy_url' => \__( 'https://support.pocketcasts.com/article/privacy-policy/', 'embed-privacy' ),
				'regex_default' => '/pca\\\.st/',
			],
			/* translators: embed provider */
			'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Pocket Casts', 'embed provider', 'embed-privacy' ) ),
			'post_status' => 'publish',
			'post_title' => \_x( 'Pocket Casts', 'embed provider', 'embed-privacy' ),
			'post_type' => 'epi_embed',
		] );
		$this->add_embed( [
			'meta_input' => [
				'is_system' => 'yes',
				'privacy_policy_url' => '',
				'regex_default' => '',
			],
			/* translators: embed provider */
			'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Maps Marker Pro', 'embed provider', 'embed-privacy' ) ),
			'post_status' => 'publish',
			'post_title' => \_x( 'Maps Marker Pro', 'embed provider', 'embed-privacy' ),
			'post_type' => 'epi_embed',
		] );
		$this->create_thumbnails_dir();
		
		$google_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'google-maps',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$google_provider = \reset( $google_provider );
		
		if ( $google_provider instanceof WP_Post ) {
			\update_post_meta( $google_provider->ID, 'regex_default', '/(google\\\.com\\\/maps\\\/embed|maps\\\.google\\\.com\\\/(maps)?)/' );
		}
	}
	
	/**
	 * Migrations for version 1.6.0.
	 * 
	 * @see		https://github.com/epiphyt/embed-privacy/issues/124
	 * @since	1.6.0
	 * 
	 * - Update Google Maps regex
	 */
	private function migrate_1_6_0() {
		$google_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'google-maps',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$google_provider = \reset( $google_provider );
		
		if ( $google_provider instanceof WP_Post ) {
			\update_post_meta( $google_provider->ID, 'regex_default', '/(google\\\.com\\\/maps\\\/embed|maps\\\.google\\\.com\\\/(maps)?)/' );
		}
	}
	
	/**
	 * Migrations for version 1.7.0.
	 * 
	 * @see		https://github.com/epiphyt/embed-privacy/issues/163
	 * @since	1.7.0
	 * 
	 * - Update CrowdSignal regex
	 */
	private function migrate_1_7_0() {
		$crowdsignal_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'crowdsignal',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$crowdsignal_provider = \reset( $crowdsignal_provider );
		
		if ( $crowdsignal_provider instanceof WP_Post ) {
			\update_post_meta( $crowdsignal_provider->ID, 'regex_default', '/((poll(\\\.fm|daddy\\\.com))|crowdsignal\\\.(com|net)|survey\\\.fm)/' );
		}
	}
	
	/**
	 * Migrations for version 1.7.3.
	 * 
	 * @since	1.7.3
	 * 
	 * - Copy thumbnails to different directory
	 */
	private function migrate_1_7_3() {
		$this->create_thumbnails_dir();
		
		$new_dir = Thumbnail::get_directory()['base_dir'];
		$old_dir = \WP_CONTENT_DIR . '/uploads/embed-privacy/thumbnails';
		
		// directories are identical, don't do anything
		if ( $new_dir === $old_dir ) {
			return;
		}
		
		if ( \file_exists( $old_dir ) ) {
			$post_args = [
				'fields' => 'ids',
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'compare' => '!=',
						'compare_key' => 'LIKE',
						'key' => 'embed_privacy_thumbnail_',
						'value' => '',
					],
				],
				'no_found_rows' => true,
				'offset' => 0,
				'post_type' => 'any',
				'update_post_term_cache' => false,
			];
			$posts = \get_posts( $post_args );
			
			// iterate through posts to get metadata
			while ( \count( $posts ) ) {
				foreach ( $posts as $post_id ) {
					$metadata = \get_post_meta( $post_id );
					
					foreach ( $metadata as $meta_key => $meta_value ) {
						if ( ! \str_contains( $meta_key, 'embed_privacy_thumbnail_' ) ) {
							continue;
						}
						
						if ( \str_contains( $meta_key, '_url' ) ) {
							continue;
						}
						
						$filename = \reset( $meta_value );
						
						// move thumbnail
						if ( \file_exists( $old_dir . '/' . $filename ) ) {
							Embed_Privacy::get_wp_filesystem()->move( $old_dir . '/' . $filename, $new_dir . '/' . $filename );
						}
					}
				}
				
				$post_args['offset'] += 5;
				$posts = \get_posts( $post_args );
			}
			
			// remove old directory 
			if ( ! ( new FilesystemIterator( $old_dir ) )->valid() ) {
				Embed_Privacy::get_wp_filesystem()->rmdir( $old_dir, true );
			}
		}
	}
	
	/**
	 * Migrations for version 1.8.0.
	 * 
	 * @since	1.8.0
	 * 
	 * - Add new embed provider Anghami
	 */
	private function migrate_1_8_0() {
		$this->add_embed( [
			'meta_input' => [
				'is_system' => 'yes',
				'privacy_policy_url' => \__( 'https://www.anghami.com/legal', 'embed-privacy' ),
				'regex_default' => '/anghami\\\.com/',
			],
			/* translators: embed provider */
			'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Anghami', 'embed provider', 'embed-privacy' ) ),
			'post_status' => 'publish',
			'post_title' => \_x( 'Anghami', 'embed provider', 'embed-privacy' ),
			'post_type' => 'epi_embed',
		] );
	}
	
	/**
	 * Migrations for version 1.10.5.
	 * 
	 * @see		https://github.com/epiphyt/embed-privacy/issues/235
	 * @since	1.10.5
	 * 
	 * - Rename Twitter to X
	 */
	private function migrate_1_10_5() {
		$twitter_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'twitter',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$twitter_provider = \reset( $twitter_provider );
		
		if ( $twitter_provider instanceof WP_Post ) {
			$x_provider = [
				'ID' => $twitter_provider->ID,
				'post_name' => \sanitize_title( \_x( 'X', 'embed provider', 'embed-privacy' ) ),
				'post_title' => \_x( 'X', 'embed provider', 'embed-privacy' ),
			];
			
			/* translators: embed provider */
			if ( $twitter_provider->post_content === \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Twitter', 'embed provider', 'embed-privacy' ) ) ) {
				/* translators: embed provider */
				$x_provider['post_content'] = \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'X', 'embed provider', 'embed-privacy' ) );
			}
			
			\wp_update_post( $x_provider );
			\update_post_meta( $twitter_provider->ID, 'privacy_policy_url', \__( 'https://x.com/privacy', 'embed-privacy' ) );
			\update_post_meta( $twitter_provider->ID, 'regex_default', '/\\\/\\\/(www\\\.)?(twitter|x)\\\.com/' );
			\update_post_meta( $twitter_provider->ID, 'is_system', 'yes' );
		}
	}
	
	/**
	 * Migrations for version 1.10.6.
	 * 
	 * @since	1.10.6
	 * 
	 * - Rename Maps Marker to Maps Marker Pro
	 */
	private function migrate_1_10_6() {
		$maps_marker_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'maps-marker',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$maps_marker_provider = \reset( $maps_marker_provider );
		
		if ( $maps_marker_provider instanceof WP_Post ) {
			$maps_marker_pro_provider = [
				'ID' => $maps_marker_provider->ID,
				'post_name' => \sanitize_title( \_x( 'Maps Marker Pro', 'embed provider', 'embed-privacy' ) ),
				'post_title' => \_x( 'Maps Marker Pro', 'embed provider', 'embed-privacy' ),
			];
			
			/* translators: embed provider */
			if ( $maps_marker_provider->post_content === \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Maps Marker', 'embed provider', 'embed-privacy' ) ) ) {
				/* translators: embed provider */
				$maps_marker_pro_provider['post_content'] = \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Maps Marker Pro', 'embed provider', 'embed-privacy' ) );
			}
			
			\wp_update_post( $maps_marker_pro_provider );
		}
	}
	
	/**
	 * Migrations for version 1.10.7.
	 * 
	 * @since	1.10.7
	 * 
	 * - Improve X regular expression
	 */
	private function migrate_1_10_7() {
		$x_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'x',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$x_provider = \reset( $x_provider );
		
		if ( $x_provider instanceof WP_Post ) {
			\update_post_meta( $x_provider->ID, 'regex_default', '/\\\/\\\/(www\\\.)?(twitter|x)\\\.com/' );
		}
		
		\delete_option( 'embed_privacy_javascript_detection' );
	}
	
	/**
	 * Migrations for version 1.11.0
	 * 
	 * @since	1.11.0
	 * 
	 * - Add Bluesky embed provider
	 * - Add Canva embed provider
	 * - Add default content item names
	 * - Make https: optional for YouTube regular expression
	 */
	private function migrate_1_11_0() {
		foreach ( Providers::get_instance()->get_list() as $provider ) {
			if ( ! $provider->get_post_object() ) {
				continue;
			}
			
			switch ( $provider->get_title() ) {
				case \_x( 'Instagram', 'embed provider', 'embed-privacy' ):
				case \_x( 'TikTok', 'embed provider', 'embed-privacy' ):
					$content_item_name = \_x( 'post', 'content item name', 'embed-privacy' );
					break;
				case \_x( 'Flickr', 'embed provider', 'embed-privacy' ):
				case \_x( 'Imgur', 'embed provider', 'embed-privacy' ):
					$content_item_name = \_x( 'image', 'content item name', 'embed-privacy' );
					break;
				case \_x( 'Maps Marker Pro', 'embed provider', 'embed-privacy' ):
					$content_item_name = \_x( 'map', 'content item name', 'embed-privacy' );
					break;
				case \_x( 'Meetup', 'embed provider', 'embed-privacy' ):
					$content_item_name = \_x( 'event', 'content item name', 'embed-privacy' );
					break;
				case \_x( 'Photobucket', 'embed provider', 'embed-privacy' ):
					$content_item_name = \_x( 'photo', 'content item name', 'embed-privacy' );
					break;
				case \_x( 'X', 'embed provider', 'embed-privacy' ):
					$content_item_name = \_x( 'tweet', 'content item name', 'embed-privacy' );
					break;
				case \_x( 'DailyMotion', 'embed provider', 'embed-privacy' ):
				case \_x( 'VideoPress', 'embed provider', 'embed-privacy' ):
				case \_x( 'Vimeo', 'embed provider', 'embed-privacy' ):
				case \_x( 'WordPress.tv', 'embed provider', 'embed-privacy' ):
				case \_x( 'YouTube', 'embed provider', 'embed-privacy' ):
					$content_item_name = \_x( 'video', 'content item name', 'embed-privacy' );
					break;
				default:
					$content_item_name = \_x( 'content', 'content item name', 'embed-privacy' );
					break;
			}
			
			\add_post_meta( $provider->get_post_object()->ID, 'content_item_name', $content_item_name, true );
		}
		
		$youtube_provider = \get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'youtube',
			'no_found_rows' => true,
			'post_type' => 'epi_embed',
			'update_post_term_cache' => false,
		] );
		$youtube_provider = \reset( $youtube_provider );
		
		if ( $youtube_provider instanceof WP_Post ) {
			\update_post_meta( $youtube_provider->ID, 'regex_default', '/(https?:)?\\\/\\\/(?:.+?.)?youtu(?:.be|be.com)/' );
		}
		
		$this->add_embed( [
			'meta_input' => [
				'content_item_name' => \_x( 'post', 'content item name', 'embed-privacy' ),
				'is_system' => 'yes',
				'privacy_policy_url' => \__( 'https://bsky.social/about/support/privacy-policy', 'embed-privacy' ),
				'regex_default' => '/bsky\\\.app/',
			],
			/* translators: embed provider */
			'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Bluesky', 'embed provider', 'embed-privacy' ) ),
			'post_status' => 'publish',
			'post_title' => \_x( 'Bluesky', 'embed provider', 'embed-privacy' ),
			'post_type' => 'epi_embed',
		] );
		$this->add_embed( [
			'meta_input' => [
				'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
				'is_system' => 'yes',
				'privacy_policy_url' => \__( 'https://www.canva.com/policies/privacy-policy/', 'embed-privacy' ),
				'regex_default' => '/canva\\\.com/',
			],
			/* translators: embed provider */
			'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Canva', 'embed provider', 'embed-privacy' ) ),
			'post_status' => 'publish',
			'post_title' => \_x( 'Canva', 'embed provider', 'embed-privacy' ),
			'post_type' => 'epi_embed',
		] );
	}
	
	/**
	 * Register default embed providers.
	 */
	public function register_default_embed_providers() {
		$this->providers = [
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.amazon.com/gp/help/customer/display.html?nodeId=GX7NJQ4ZB8MHFRNJ', 'embed-privacy' ),
					'regex_default' => '/\\\.?(ama?zo?n\\\.|a\\\.co\\\/|z\\\.cn\\\/)/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Amazon Kindle', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Amazon Kindle', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.anghami.com/legal', 'embed-privacy' ),
					'regex_default' => '/anghami\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Anghami', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Anghami', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://animoto.com/legal/privacy_policy', 'embed-privacy' ),
					'regex_default' => '/animoto\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Animoto', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Animoto', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'post', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://bsky.social/about/support/privacy-policy', 'embed-privacy' ),
					'regex_default' => '/bsky\\\.app/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Bluesky', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Bluesky', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.canva.com/policies/privacy-policy/', 'embed-privacy' ),
					'regex_default' => '/canva\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Canva', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Canva', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/cloudup\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Cloudup', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Cloudup', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/((poll(\\\.fm|daddy\\\.com))|crowdsignal\\\.(com|net)|survey\\\.fm)/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Crowdsignal', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Crowdsignal', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'video', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.dailymotion.com/legal/privacy?localization=en', 'embed-privacy' ),
					'regex_default' => '/dailymotion\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'DailyMotion', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'DailyMotion', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.facebook.com/privacy/explanation', 'embed-privacy' ),
					'regex_default' => '/facebook\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Facebook', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Facebook', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'image', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.flickr.com/help/privacy', 'embed-privacy' ),
					'regex_default' => '/flickr\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Flickr', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Flickr', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.funnyordie.com/legal/privacy-notice', 'embed-privacy' ),
					'regex_default' => '/funnyordie\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Funny Or Die', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Funny Or Die', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => '',
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://policies.google.com/privacy?hl=en', 'embed-privacy' ),
					'regex_default' => '/(google\\\.com\\\/maps\\\/embed|maps\\\.google\\\.com\\\/(maps)?)/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Google Maps', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Google Maps', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'image', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://imgur.com/privacy', 'embed-privacy' ),
					'regex_default' => '/imgur\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Imgur', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Imgur', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'post', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.instagram.com/legal/privacy/', 'embed-privacy' ),
					'regex_default' => '/instagram\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Instagram', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Instagram', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://issuu.com/legal/privacy', 'embed-privacy' ),
					'regex_default' => '/issuu\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Issuu', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Issuu', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.kickstarter.com/privacy', 'embed-privacy' ),
					'regex_default' => '/kickstarter\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Kickstarter', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Kickstarter', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'map', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => '',
					'regex_default' => '',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Maps Marker Pro', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Maps Marker Pro', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'event', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.meetup.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/meetup\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Meetup', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Meetup', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.mixcloud.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/mixcloud\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Mixcloud', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Mixcloud', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'photo', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://app.photobucket.com/privacy', 'embed-privacy' ),
					'regex_default' => '/photobucket\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Photobucket', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Photobucket', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://policy.pinterest.com/en/privacy-policy', 'embed-privacy' ),
					'regex_default' => '/pinterest\\\./',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Pinterest', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Pinterest', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://support.pocketcasts.com/article/privacy-policy/', 'embed-privacy' ),
					'regex_default' => '/pca\\\.st/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Pocket Casts', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Pocket Casts', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.reddit.com/help/privacypolicy', 'embed-privacy' ),
					'regex_default' => '/reddit\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Reddit', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Reddit', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.reverbnation.com/privacy', 'embed-privacy' ),
					'regex_default' => '/reverbnation\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'ReverbNation', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'ReverbNation', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://scribd.com/privacy', 'embed-privacy' ),
					'regex_default' => '/scribd\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Scribd', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Scribd', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://sketchfab.com/privacy', 'embed-privacy' ),
					'regex_default' => '/sketchfab\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Sketchfab', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Sketchfab', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'slides', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.slideshare.net/privacy', 'embed-privacy' ),
					'regex_default' => '/slideshare\\\.net/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'SlideShare', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'SlideShare', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.smugmug.com/about/privacy', 'embed-privacy' ),
					'regex_default' => '/smugmug\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'SmugMug', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'SmugMug', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://soundcloud.com/pages/privacy', 'embed-privacy' ),
					'regex_default' => '/soundcloud\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'SoundCloud', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'SoundCloud', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://speakerdeck.com/privacy', 'embed-privacy' ),
					'regex_default' => '/speakerdeck\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Speaker Deck', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Speaker Deck', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.spotify.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/spotify\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Spotify', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Spotify', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'post', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.tiktok.com/legal/privacy-policy?lang=en-US', 'embed-privacy' ),
					'regex_default' => '/tiktok\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'TikTok', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'TikTok', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.ted.com/about/our-organization/our-policies-terms/privacy-policy', 'embed-privacy' ),
					'regex_default' => '/ted\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'TED', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'TED', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.tumblr.com/privacy_policy', 'embed-privacy' ),
					'regex_default' => '/tumblr\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Tumblr', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Tumblr', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'tweet', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://x.com/privacy', 'embed-privacy' ),
					'regex_default' => '\\\/\\\/(www\\\.)?(twitter|x)\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'X', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'X', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'video', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/videopress\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'VideoPress', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'VideoPress', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'video', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://vimeo.com/privacy', 'embed-privacy' ),
					'regex_default' => '/vimeo\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Vimeo', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Vimeo', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://www.wolfram.com/legal/privacy/wolfram/', 'embed-privacy' ),
					'regex_default' => '/wolframcloud\\\.com/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'Wolfram Cloud', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'Wolfram Cloud', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'content', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://wordpress.org/about/privacy/', 'embed-privacy' ),
					'regex_default' => '/wordpress\\\.org\\\/plugins/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'WordPress.org', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'WordPress.org', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'video', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://wordpress.org/about/privacy/', 'embed-privacy' ),
					'regex_default' => '/wordpress\\\.tv\/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'WordPress.tv', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'WordPress.tv', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'content_item_name' => \_x( 'video', 'content item name', 'embed-privacy' ),
					'is_system' => 'yes',
					'privacy_policy_url' => \__( 'https://policies.google.com/privacy?hl=en', 'embed-privacy' ),
					'regex_default' => '/(https?:)?\\\/\\\/(?:.+?.)?youtu(?:.be|be.com)/',
				],
				/* translators: embed provider */
				'post_content' => \sprintf( \__( 'Click here to display content from %s.', 'embed-privacy' ), \_x( 'YouTube', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => \_x( 'YouTube', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
		];
	}
	
	/**
	 * Add a notice if migration failed.
	 * 
	 * @since	1.5.0
	 */
	public function register_migration_failed_notice() {
		if ( (int) $this->get_option( 'migration_count' ) < 3 ) {
			return;
		}
		?>
		<div class="notice notice-error" data-notice="embed_privacy_migration_failed_notice">
			<p>
				<?php
				\printf(
					/* translators: 1: current migration version, 2: target migration version, 3: starting HTML anchor, 4: ending HTML anchor */
					\esc_html__( 'Embed Privacy migration from version %1$s to %2$s failed. Please contact the %3$ssupport%4$s for further assistance.', 'embed-privacy' ),
					\esc_html( $this->get_option( 'migrate_version' ) ),
					\esc_html( $this->version ),
					'<a href="' . \esc_url( \__( 'https://wordpress.org/support/plugin/embed-privacy/#new-topic-0', 'embed-privacy' ) ) . '">',
					'</a>'
				); ?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Update an option either from the global multisite settings or the regular site.
	 * 
	 * @param	string	$option The option name
	 * @param	mixed	$value The value to update
	 * @return	bool Whether the update was successful
	 */
	private function update_option( $option, $value ) {
		return \update_option( 'embed_privacy_' . $option, $value );
	}
}
