<?php
namespace epiphyt\Embed_Privacy\thumbnail;

use epiphyt\Embed_Privacy\thumbnail\provider\SlideShare;
use epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider;
use epiphyt\Embed_Privacy\thumbnail\provider\Vimeo;
use epiphyt\Embed_Privacy\thumbnail\provider\WordPress_TV;
use epiphyt\Embed_Privacy\thumbnail\provider\YouTube;
use WP_Post;

/**
 * Thumbnail related functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
final class Thumbnail {
	const METADATA_PREFIX = 'embed_privacy_thumbnail';
	
	/**
	 * @var		\epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider[] List of thumbnail provider classes
	 */
	public $providers = [
		SlideShare::class,
		Vimeo::class,
		WordPress_TV::class,
		YouTube::class,
	];
	
	/**
	 * Initialize functionality.
	 */
	public function init() {
		if ( ! \get_option( 'embed_privacy_download_thumbnails' ) ) {
			return;
		}
		
		\add_action( 'before_delete_post', [ self::class, 'delete_thumbnails' ] );
		\add_action( 'init', [ $this, 'register_providers' ] );
		\add_action( 'post_updated', [ $this, 'delete_orphaned' ], 10, 2 );
		\add_filter( 'oembed_dataparse', [ $this, 'get_from_provider' ], 10, 3 );
	}
	
	/**
	 * Check and delete orphaned thumbnails.
	 * 
	 * @param	int			$post_id The post ID
	 * @param	\WP_Post	$post The post object
	 */
	public function delete_orphaned( $post_id, $post ) {
		// don't check for orphaned if it's not a proper post update
		// e.g. via REST API, where not all fields are updated
		if ( empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		
		$global_metadata = self::get_metadata();
		$metadata = \get_post_meta( $post_id );
		$supported_providers = \array_map(
			static function( $provider ) {
				$provider_obj = new $provider(); // phpcs:ignore NeutronStandard.Functions.VariableFunctions.VariableFunction
				
				return $provider_obj::$name;
			},
			$this->providers
		);
		
		/**
		 * Filter the supported provider names.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	\epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider[]	$supported_providers Current supported provider names
		 */
		$supported_providers = (array) \apply_filters( 'embed_privacy_thumbnail_supported_provider_names', $supported_providers );
		
		foreach ( $metadata as $meta_key => $meta_value ) {
			if ( ! \str_contains( $meta_key, self::METADATA_PREFIX . '_' ) ) {
				continue;
			}
			
			if ( \is_array( $meta_value ) ) {
				$meta_value = \reset( $meta_value );
			}
			
			foreach ( $supported_providers as $provider ) {
				if ( \str_contains( $meta_key, '_' . $provider . '_' ) && ! \str_contains( $meta_key, '_url' ) ) {
					$id = \str_replace( self::METADATA_PREFIX . '_' . $provider . '_', '', $meta_key );
					$missing_id = ! \str_contains( $post->post_content, $id );
					$missing_url = true;
					$url = '';
					
					if ( $missing_id && self::is_in_acf_fields( $post_id, $id ) ) {
						$missing_id = false;
					}
					
					if ( $missing_id && isset( $metadata[ $meta_key . '_url' ] ) ) {
						$url = $metadata[ $meta_key . '_url' ];
						
						if ( \is_array( $url ) ) {
							$url = \reset( $url );
						}
						
						$missing_url = ! \str_contains( $post->post_content, $url );
						
						if ( $missing_url && self::is_in_acf_fields( $post_id, $url ) ) {
							$missing_url = false;
						}
					}
					
					$should_delete = $missing_id && $missing_url && ! self::is_in_use( $meta_value, $post_id, $global_metadata );
					
					/**
					 * Filters whether an thumbnail marked as orphaned should be deleted.
					 * 
					 * @since	1.10.0
					 * 
					 * @param	bool	$should_delete Whether the thumbnail should be deleted
					 * @param	string	$id The thumbnail ID
					 * @param	string	$url The thumbnail URL
					 * @param	int		$post_id The post ID
					 * @param	string	$provider The provider
					 */
					$should_delete = \apply_filters( 'embed_privacy_thumbnail_delete_orphaned', $should_delete, $id, $url, $post_id, $provider );
					
					if ( $should_delete ) {
						/**
						 * Fires before orphaned data are deleted.
						 * 
						 * @deprecated	1.10.0 Use filter embed_privacy_thumbnail_delete_orphaned instead
						 * @since		1.8.0
						 * 
						 * @param	string	$id The thumbnail ID
						 * @param	string	$url The thumbnail URL
						 * @param	int		$post_id The post ID
						 * @param	string	$provider The provider name
						 */
						\do_action_deprecated(
							'embed_privacy_pre_thumbnail_delete_orphaned_delete',
							[
								$id,
								$url,
								$post_id,
								$provider,
							],
							'1.10.0',
							'embed_privacy_thumbnail_delete_orphaned'
						);
						
						$should_delete = ! \has_action( 'embed_privacy_pre_thumbnail_delete_orphaned_delete' );
						
						if ( $should_delete ) {
							self::delete( $meta_value );
							\delete_post_meta( $post_id, $meta_key );
							\delete_post_meta( $post_id, $meta_key . '_url' );
						}
					}
					
					/**
					 * Fires after orphaned data have been checked.
					 * 
					 * @since	1.7.0
					 * 
					 * @param	string		$provider Provider name
					 * @param	string		$id The ID of the embedded content
					 * @param	string		$url The embed URL
					 * @param	bool		$missing_id Whether the ID is missing
					 * @param	bool		$missing_url Whether the URL is missing
					 * @param	string		$meta_value The thumbnail filename
					 * @param	string		$meta_key The thumbnail meta key
					 * @param	\WP_Post	$post The post object
					 * @param	int			$post_id The post ID
					 */
					\do_action( 'embed_privacy_thumbnail_checked_orphaned', $provider, $id, $url, $missing_id, $missing_url, $meta_value, $meta_key, $post, $post_id );
				}
			}
		}
	}
	
	/**
	 * Delete a thumbnail.
	 * 
	 * @param	string	$filename The thumbnail filename
	 */
	private static function delete( $filename ) {
		$directory = self::get_directory();
		
		if ( ! \file_exists( $directory['base_dir'] . '/' . $filename ) ) {
			return;
		}
		
		\wp_delete_file( $directory['base_dir'] . '/' . $filename );
	}
	
	/**
	 * Delete thumbnails for a given post ID.
	 * 
	 * @param	int		$post_id Post ID
	 */
	public static function delete_thumbnails( $post_id ) {
		$global_metadata = self::get_metadata();
		$metadata = \get_post_meta( $post_id );
		
		foreach ( $metadata as $meta_key => $meta_value ) {
			if ( ! \str_contains( $meta_key, self::METADATA_PREFIX . '_' ) ) {
				continue;
			}
			
			if ( \is_array( $meta_value ) ) {
				$meta_value = \reset( $meta_value );
			}
			
			if ( ! self::is_in_use( $meta_value, $post_id, $global_metadata ) ) {
				self::delete( $meta_value );
			}
		}
	}
	
	/**
	 * Get path and URL to an embed thumbnail.
	 * 
	 * @param	\WP_Post	$post Post object
	 * @param	string		$url Embedded URL
	 * @return	array Thumbnail path and URL
	 */
	public function get_data( $post, $url ) {
		$empty = [
			'thumbnail_path' => '',
			'thumbnail_url' => '',
		];
		
		if ( ! $post instanceof WP_Post ) {
			return $empty;
		}
		
		$id = '';
		$provider = $this->get_provider_by_url( $url );
		$thumbnail = '';
		$thumbnail_path = '';
		$thumbnail_url = '';
		
		if ( ! $provider instanceof Thumbnail_Provider ) {
			return $empty;
		}
		
		$id = $provider->get_id( $url );
		$thumbnail = \get_post_meta( $post->ID, self::METADATA_PREFIX . '_' . $provider::$name . '_' . $id, true );
		
		/**
		 * Filter the thumbnail ID.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string		$id The thumbnail ID
		 * @param	\WP_Post	$post The post object
		 * @param	string		$url The embed URL
		 */
		$id = \apply_filters( 'embed_privacy_thumbnail_data_id', $id, $post, $url );
		
		/**
		 * Filter the thumbnail filename.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string		$thumbnail The thumbnail filename
		 * @param	\WP_Post	$post The post object
		 * @param	string		$url The embed URL
		 */
		$thumbnail = \apply_filters( 'embed_privacy_thumbnail_data_filename', $thumbnail, $post, $url );
		
		if ( $thumbnail ) {
			$directory = self::get_directory();
			$thumbnail_path = $directory['base_dir'] . '/' . $thumbnail;
			
			if ( \file_exists( $thumbnail_path ) ) {
				$thumbnail_url = $directory['base_url'] . '/' . $thumbnail;
			}
		}
		
		/**
		 * Filter the thumbnail path.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string		$thumbnail The thumbnail path
		 * @param	\WP_Post	$post The post object
		 * @param	string		$url The embed URL
		 */
		$thumbnail_path = \apply_filters( 'embed_privacy_thumbnail_data_path', $thumbnail_path, $post, $url );
		
		/**
		 * Filter the thumbnail URL.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string		$thumbnail The thumbnail URL
		 * @param	\WP_Post	$post The post object
		 * @param	string		$url The embed URL
		 */
		$thumbnail_url = \apply_filters( 'embed_privacy_thumbnail_data_url', $thumbnail_url, $post, $url );
		
		return [
			'thumbnail_path' => $thumbnail_path,
			'thumbnail_url' => $thumbnail_url,
		];
	}
	
	/**
	 * Get the thumbnail directory and URL.
	 * Since we don't want to have a directory per site in a network, we need to
	 * get rid of the site ID in the path.
	 * 
	 * @return	string[] Thumbnail directory and URL
	 */
	public static function get_directory() {
		$upload_dir = \wp_get_upload_dir();
		
		if ( ! $upload_dir || $upload_dir['error'] !== false ) {
			return [
				'base_dir' => '',
				'base_url' => '',
			];
		}
		
		if ( ! \file_exists( $upload_dir['basedir'] . '/embed-privacy/thumbnails' ) ) {
			\wp_mkdir_p( $upload_dir['basedir'] . '/embed-privacy/thumbnails' );
		}
		
		return [
			'base_dir' => $upload_dir['basedir'] . '/embed-privacy/thumbnails',
			'base_url' => $upload_dir['baseurl'] . '/embed-privacy/thumbnails',
		];
	}
	
	/**
	 * Get embed thumbnails from the embed provider.
	 * 
	 * @param	string	$output The returned oEmbed HTML
	 * @param	object	$data A data object result from an oEmbed provider
	 * @param	string	$url The URL of the content to be embedded
	 * @return	string The returned oEmbed HTML
	 */
	public function get_from_provider( $output, $data, $url ) {
		foreach ( $this->providers as $provider ) {
			$provider_obj = new $provider(); // phpcs:ignore NeutronStandard.Functions.VariableFunctions.VariableFunction
			$provider_obj->get( $data, $url );
		}
		
		/**
		 * Fires after getting data from provider.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string	$output The returned oEmbed HTML
		 * @param	object	$data A data object result from an oEmbed provider
		 * @param	string	$url The URL of the content to be embedded
		 */
		\do_action( 'embed_privacy_thumbnail_get_from_provider', $output, $data, $url );
		
		return $output;
	}
	
	/**
	 * Get all thumbnail metadata of all posts.
	 * 
	 * @param	string	$provider Optional provider name to limit metadata
	 * @return	array All thumbnail metadata
	 */
	public static function get_metadata( $provider = '' ) {
		global $wpdb;
		
		if ( ! empty( $provider ) ) {
			$key = self::METADATA_PREFIX . '_' . $provider . '_%';
		}
		else {
			$key = self::METADATA_PREFIX . '_%';
		}
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT	post_id,
									meta_key,
									meta_value
				FROM				$wpdb->postmeta
				WHERE				meta_key LIKE %s",
				$key
			),
			\ARRAY_A
		);
		// phpcs:enable
	}
	
	/**
	 * Get a thumbnail provider by an URL.
	 * 
	 * @param	string	$url Embed URL
	 * @return	\epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider|null Thumbnail provider object or null
	 */
	public function get_provider_by_url( $url ) {
		foreach ( $this->providers as $provider ) {
			$provider_obj = new $provider(); // phpcs:ignore NeutronStandard.Functions.VariableFunctions.VariableFunction
			
			foreach ( $provider_obj::$domains as $domain ) {
				if ( \str_contains( $url, $domain ) ) {
					return $provider_obj;
				}
			}
		}
		
		return null;
	}
	
	/**
	 * Get the provider titles
	 * 
	 * @return	string[] List of provider titles
	 */
	public function get_provider_titles() {
		$titles = \array_map(
			static function( $provider ) {
				$provider_obj = new $provider(); // phpcs:ignore NeutronStandard.Functions.VariableFunctions.VariableFunction
				
				return $provider_obj->get_title();
			},
			$this->providers
		);
		
		\asort( $titles, \SORT_NATURAL );
		
		return $titles;
	}
	
	/**
	 * Check whether a thumbnail is in use in ACF fields.
	 * 
	 * @param	int		$post_id Current post ID
	 * @param	string	$content Content to search for
	 * @param	array	$fields List of ACF fields
	 * @return	bool Whether thumbnail is in use in ACF fields
	 */
	private function is_in_acf_fields( $post_id, $content, array $fields = [] ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! \function_exists( 'get_fields' ) ) {
			return false;
		}
		
		if ( empty( $fields ) ) {
			if ( empty( $_POST['acf'] ) ) {
				return false;
			}
			
			// we need to use the post fields since get_fields() doesn't contain
			// the actual value since it will be stored after the post is saved
			$fields = \wp_unslash( $_POST['acf'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		// phpcs:enable
		
		$is_in_fields = false;
		
		foreach ( $fields as $field ) {
			if ( \is_array( $field ) || \is_object( $field ) ) {
				if ( self::is_in_acf_fields( $post_id, $content, (array) $field ) ) {
					$is_in_fields = true;
					break;
				}
			}
			else if ( \str_contains( (string) $field, $content ) ) {
				$is_in_fields = true;
				break;
			}
		}
		
		return $is_in_fields;
	}
	
	/**
	 * Check whether a thumbnail is in use in another post.
	 * 
	 * @param	string	$meta_value The thumbnail filename
	 * @param	int		$post_id The post ID of the current post
	 * @param	array	$global_metadata Global metadata to check in
	 * @return	bool Whether a thumbnail is in use in another post
	 */
	private static function is_in_use( $meta_value, $post_id, $global_metadata ) {
		$is_in_use = false;
		
		foreach ( $global_metadata as $global_meta_value ) {
			if ( (int) $global_meta_value['post_id'] === $post_id ) {
				continue;
			}
			
			if ( $global_meta_value['meta_value'] === $meta_value ) {
				$is_in_use = true;
				break;
			}
		}
		
		return $is_in_use;
	}
	
	/**
	 * Register thumbnail providers.
	 */
	public function register_providers() {
		/**
		 * Filter the supported providers.
		 * 
		 * @since	1.9.0
		 * 
		 * @param	\epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider[]	$supported_providers Current supported providers
		 */
		$this->providers = (array) \apply_filters( 'embed_privacy_thumbnail_providers', $this->providers );
		
		$providers = [
			\_x( 'SlideShare', 'embed provider', 'embed-privacy' ),
			\_x( 'Vimeo', 'embed provider', 'embed-privacy' ),
			\_x( 'YouTube', 'embed provider', 'embed-privacy' ),
		];
		
		/**
		 * Filter the supported providers.
		 * 
		 * @deprecated	1.9.0
		 * @since		1.7.0
		 * 
		 * @param	array	$supported_providers Current supported providers
		 */
		$providers = \apply_filters_deprecated( 'embed_privacy_thumbnail_supported_providers', $providers, '1.9.0', 'embed_privacy_thumbnail_providers' );
		
		foreach ( $this->providers as $provider ) {
			try {
				$provider_obj = new $provider(); // phpcs:ignore NeutronStandard.Functions.VariableFunctions.VariableFunction
				
				if ( ! $provider_obj instanceof Thumbnail_Provider ) {
					\wp_die(
						\sprintf(
							/* translators: PHP class */
							\esc_html__( 'Thumbnail provider is not an instance of %s', 'embed-privacy' ),
							'epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider'
						)
					);
				}
			}
			catch ( \Exception $e ) {
				\wp_die(
					\sprintf(
						/* translators: PHP class */
						\esc_html__( 'Thumbnail provider is not an instance of %s', 'embed-privacy' ),
						'epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider'
					)
				);
			}
		}
	}
}
