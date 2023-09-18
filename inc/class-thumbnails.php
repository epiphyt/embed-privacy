<?php
namespace epiphyt\Embed_Privacy;

use WP_Post;

/**
 * Thumbnails for Embed Privacy.
 * 
 * @since	1.5.0
 *
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Thumbnails {
	// @deprecated: use Thumbnails::$directory instead
	const DIRECTORY = \WP_CONTENT_DIR . '/uploads/embed-privacy/thumbnails';
	
	/**
	 * @var		array Fields to output
	 */
	public $fields = [];
	
	/**
	 * @var		\epiphyt\Embed_Privacy\Thumbnails
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
		if ( ! \get_option( 'embed_privacy_download_thumbnails' ) ) {
			return;
		}
		
		\add_action( 'before_delete_post', [ $this, 'delete_thumbnails' ] );
		\add_action( 'post_updated', [ $this, 'check_orphaned' ], 10, 2 );
		\add_filter( 'oembed_dataparse', [ $this, 'get_from_provider' ], 10, 3 );
	}
	
	/**
	 * Check and delete orphaned thumbnails.
	 * 
	 * @param	int			$post_id The post ID
	 * @param	\WP_Post	$post The post object
	 */
	public function check_orphaned( $post_id, $post ) {
		// don't check for orphaned if it's not a proper post update
		// e.g. via REST API, where not all fields are updated
		if ( empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		
		$global_metadata = $this->get_metadata();
		$metadata = \get_post_meta( $post_id );
		$supported_providers = [
			'slideshare',
			'vimeo',
			'youtube',
		];
		
		/**
		 * Filter the supported provider names.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	array	$supported_providers Current supported provider names
		 */
		$supported_providers = \apply_filters( 'embed_privacy_thumbnail_supported_provider_names', $supported_providers );
		
		foreach ( $metadata as $meta_key => $meta_value ) {
			if ( \strpos( $meta_key, 'embed_privacy_thumbnail_' ) === false ) {
				continue;
			}
			
			if ( \is_array( $meta_value ) ) {
				$meta_value = \reset( $meta_value );
			}
			
			foreach ( $supported_providers as $provider ) {
				if ( \strpos( $meta_key, '_' . $provider . '_' ) !== false && \strpos( $meta_key, '_url' ) === false ) {
					$id = \str_replace( 'embed_privacy_thumbnail_' . $provider . '_', '', $meta_key );
					$missing_id = \strpos( $post->post_content, $id ) === false;
					$missing_url = true;
					$url = '';
					
					if ( $missing_id && $this->is_in_acf_fields( $post_id, $id ) ) {
						$missing_id = false;
					}
					
					if ( $missing_id && isset( $metadata[ $meta_key . '_url' ] ) ) {
						$url = $metadata[ $meta_key . '_url' ];
						
						if ( \is_array( $url ) ) {
							$url = reset( $url );
						}
						
						$missing_url = \strpos( $post->post_content, $url ) === false;
						
						if ( $missing_url && $this->is_in_acf_fields( $post_id, $url ) ) {
							$missing_url = false;
						}
					}
					
					if ( $missing_id && $missing_url && ! $this->is_in_use( $meta_value, $post_id, $global_metadata ) ) {
						/**
						 * Fires before orphaned data are deleted.
						 * 
						 * @since	1.8.0
						 * 
						 * @param	string	$id The thumbnail ID
						 * @param	string	$url The thumbnail URL
						 * @param	int		$post_id The post ID
						 * @param	string	$provider The provider name
						 */
						\do_action( 'embed_privacy_pre_thumbnail_check_orphaned_delete', $id, $url, $post_id, $provider );
						
						if ( ! \has_action( 'embed_privacy_pre_thumbnail_check_orphaned_delete' ) ) {
							$this->delete( $meta_value );
							\delete_post_meta( $post_id, $meta_key );
							\delete_post_meta( $post_id, $meta_key . '_url' );
						}
					}
					
					/**
					 * Fires after orphaned data have been checked.
					 * 
					 * @since	1.7.0
					 * 
					 * @param	string	$provider Provider name
					 * @param	string	$id The ID of the embedded content
					 * @param	string	$url The embed URL
					 * @param	bool	$missin_id Whether the ID is missing
					 * @param	bool	$missing_url Whether the URL is missing
					 * @param	string	$meta_value The thumbnail filename
					 * @param	string	$meta_key The thumbnail meta key
					 * @param	WP_Post	$post The post object
					 * @param	int		$post_id The post ID
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
	private function delete( $filename ) {
		$directory = $this->get_directory();
		
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
	public function delete_thumbnails( $post_id ) {
		$global_metadata = $this->get_metadata();
		$metadata = \get_post_meta( $post_id );
		
		foreach ( $metadata as $meta_key => $meta_value ) {
			if ( \strpos( $meta_key, 'embed_privacy_thumbnail_' ) === false ) {
				continue;
			}
			
			if ( \is_array( $meta_value ) ) {
				$meta_value = \reset( $meta_value );
			}
			
			if ( ! $this->is_in_use( $meta_value, $post_id, $global_metadata ) ) {
				$this->delete( $meta_value );
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
		if ( ! $post instanceof WP_Post ) {
			return [
				'thumbnail_path' => '',
				'thumbnail_url' => '',
			];
		}
		
		$id = '';
		$thumbnail = '';
		$thumbnail_path = '';
		$thumbnail_url = '';
		
		if ( \strpos( $url, 'slideshare.net' ) !== false ) {
			$id = \preg_replace( '/.*\/embed_code\/key\//', '', $url );
			
			if ( \strpos( $id, '?' ) !== false ) {
				$id = \substr( $id, 0, \strpos( $id, '?' ) );
			}
			
			$thumbnail = \get_post_meta( $post->ID, 'embed_privacy_thumbnail_slideshare_' . $id, true );
		}
		else if ( \strpos( $url, 'vimeo.com' ) !== false ) {
			$id = \str_replace( [ 'https://vimeo.com/', 'https://player.vimeo.com/video/' ], '', $url );
			
			if ( \strpos( $id, '?' ) !== false ) {
				$id = \substr( $id, 0, \strpos( $id, '?' ) );
			}
			
			$thumbnail = \get_post_meta( $post->ID, 'embed_privacy_thumbnail_vimeo_' . $id, true );
		}
		else if ( \strpos( $url, 'youtube.com' ) !== false || \strpos( $url, 'youtu.be' ) !== false ) {
			$id = \str_replace( [ 'https://www.youtube.com/watch?v=', 'https://www.youtube.com/embed/', 'https://youtu.be/' ], '', $url );
			$id = \strpos( $id, '?' ) !== false ? \substr( $id, 0, \strpos( $id, '?' ) ) : $id;
			$thumbnail = \get_post_meta( $post->ID, 'embed_privacy_thumbnail_youtube_' . $id, true );
		}
		
		/**
		 * Filter the thumbnail ID.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string	$id The thumbnail ID
		 * @param	WP_Post	$post The post object
		 * @param	string	$url The embed URL
		 */
		$id = \apply_filters( 'embed_privacy_thumbnail_data_id', $id, $post, $url );
		
		/**
		 * Filter the thumbnail filename.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string	$thumbnail The thumbnail filename
		 * @param	WP_Post	$post The post object
		 * @param	string	$url The embed URL
		 */
		$thumbnail = \apply_filters( 'embed_privacy_thumbnail_data_filename', $thumbnail, $post, $url );
		
		if ( $thumbnail ) {
			$directory = $this->get_directory();
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
		 * @param	string	$thumbnail The thumbnail path
		 * @param	WP_Post	$post The post object
		 * @param	string	$url The embed URL
		 */
		$thumbnail_path = \apply_filters( 'embed_privacy_thumbnail_data_path', $thumbnail_path, $post, $url );
		
		/**
		 * Filter the thumbnail URL.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string	$thumbnail The thumbnail URL
		 * @param	WP_Post	$post The post object
		 * @param	string	$url The embed URL
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
	 * @since	1.7.3
	 * 
	 * @return	string[] Thumbnail directory and URL
	 */
	public function get_directory() {
		$upload_dir = \wp_get_upload_dir();
		
		if ( ! $upload_dir || $upload_dir['error'] !== false ) {
			return [
				'base_dir' => '',
				'base_url' => '',
			];
		}
		
		return [
			'base_dir' => $upload_dir['basedir'] . '/embed-privacy/thumbnails',
			'base_url' => $upload_dir['baseurl'] . '/embed-privacy/thumbnails',
		];
	}
	
	/**
	 * Get embed thumbnails from the embed provider.
	 * 
	 * @param	string	$return The returned oEmbed HTML
	 * @param	object	$data A data object result from an oEmbed provider
	 * @param	string	$url The URL of the content to be embedded
	 * @return	string The returned oEmbed HTML
	 */
	public function get_from_provider( $return, $data, $url ) {
		if ( \strpos( $url, 'slideshare.net' ) !== false ) {
			// the thumbnail URL contains sizing parameters in the query string
			// remove this to get the maximum resolution
			$thumbnail_url = \preg_replace( '/\?.*/', '', $data->thumbnail_url );
			$extracted = \preg_replace( '/.*\/embed_code\/key\//', '', $data->html );
			$parts = \explode( '"', $extracted );
			$id = isset( $parts[0] ) ? $parts[0] : false;
			
			if ( $id ) {
				$this->set_slideshare_thumbnail( $id, $url, $thumbnail_url );
			}
		}
		else if ( \strpos( $url, 'vimeo.com' ) !== false ) {
			// the thumbnail URL has usually something like _295x166 in the end
			// remove this to get the maximum resolution
			$thumbnail_url = \substr( $data->thumbnail_url, 0, \strrpos( $data->thumbnail_url, '_' ) );
			$id = \str_replace( [ 'https://vimeo.com/', 'https://player.vimeo.com/video/' ], '', $url );
			
			if ( \strpos( $id, '?' ) !== false ) {
				$id = \substr( $id, 0, \strpos( $id, '?' ) );
			}
			
			if ( $id ) {
				$this->set_vimeo_thumbnail( $id, $url, $thumbnail_url );
			}
		}
		else if ( \strpos( $url, 'youtube.com' ) !== false || \strpos( $url, 'youtu.be' ) !== false ) {
			$thumbnail_url = $data->thumbnail_url;
			// format: <id>/<thumbnail-name>.jpg
			$extracted = \str_replace( 'https://i.ytimg.com/vi/', '', $thumbnail_url );
			// first part is the ID
			$parts = \explode( '/', $extracted );
			$id = isset( $parts[0] ) ? $parts[0] : false;
			
			if ( $id ) {
				$this->set_youtube_thumbnail( $id, $url );
			}
		}
		
		/**
		 * Fires after getting data from provider.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	string	$return The returned oEmbed HTML
		 * @param	object	$data A data object result from an oEmbed provider
		 * @param	string	$url The URL of the content to be embedded
		 */
		\do_action( 'embed_privacy_thumbnail_get_from_provider', $return, $data, $url );
		
		return $return;
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Embed_Privacy\Thumbnails The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Get all thumbnail metadata of all posts.
	 * 
	 * @return	array All thumbnail metadata
	 */
	private function get_metadata() {
		global $wpdb;
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT	post_id,
									meta_value
				FROM				$wpdb->postmeta
				WHERE				meta_key LIKE %s",
				'embed_privacy_thumbnail_%'
			),
			\ARRAY_A
		);
		// phpcs:enable
	}
	
	/**
	 * Get a list of supported embed providers for thumbnails.
	 * 
	 * @return	array A list of supported embed providers
	 */
	public function get_supported_providers() {
		$providers = [
			\_x( 'Slideshare', 'embed provider', 'embed-privacy' ),
			\_x( 'Vimeo', 'embed provider', 'embed-privacy' ),
			\_x( 'YouTube', 'embed provider', 'embed-privacy' ),
		];
		
		/**
		 * Filter the supported providers.
		 * 
		 * @since	1.7.0
		 * 
		 * @param	array	$supported_providers Current supported providers
		 */
		$providers = \apply_filters( 'embed_privacy_thumbnail_supported_providers', $providers );
		
		return $providers;
	}
	
	/**
	 * Check whether a thumbnail is in use in ACF fields.
	 * 
	 * @since	1.7.3
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
				if ( $this->is_in_acf_fields( $post_id, $content, (array) $field ) ) {
					$is_in_fields = true;
					break;
				}
			}
			else if ( \strpos( (string) $field, $content ) !== false ) {
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
	private function is_in_use( $meta_value, $post_id, $global_metadata ) {
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
	 * Download and save a Slideshare thumbnail.
	 * 
	 * @since	1.7.0
	 * 
	 * @param	string	$id Slideshare embed ID
	 * @param	string	$url Slideshare deck URL
	 * @param	string	$thumbnail_url Slideshare thumbnail URL
	 */
	public function set_slideshare_thumbnail( $id, $url, $thumbnail_url ) {
		$post = \get_post();
		
		if ( ! $post ) {
			return;
		}
		
		$filename = 'slideshare-' . $id . '.jpg' ;
		$thumbnail_path = $this->get_directory()['base_dir'] . '/' . $filename;
		
		if ( ! \file_exists( $thumbnail_path ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			
			$file = \download_url( $thumbnail_url );
			
			if ( \is_wp_error( $file ) ) {
				return;
			}
			
			global $wp_filesystem;
			
			// initialize the WP filesystem if not exists
			if ( empty( $wp_filesystem ) ) {
				\WP_Filesystem();
			}
			
			$wp_filesystem->move( $file, $thumbnail_path );
		}
		
		\update_post_meta( $post->ID, 'embed_privacy_thumbnail_slideshare_' . $id, $filename );
		\update_post_meta( $post->ID, 'embed_privacy_thumbnail_slideshare_' . $id . '_url', $url );
	}
	
	/**
	 * Download and save a Vimeo thumbnail.
	 * 
	 * @param	string	$id Vimeo video ID
	 * @param	string	$url Vimeo video URL
	 * @param	string	$thumbnail_url Vimeo thumbnail URL
	 */
	public function set_vimeo_thumbnail( $id, $url, $thumbnail_url ) {
		$post = \get_post();
		
		if ( ! $post ) {
			return;
		}
		
		$thumbnail_path = $this->get_directory()['base_dir'] . '/vimeo-' . $id . '.jpg';
		
		if ( ! \file_exists( $thumbnail_path ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			
			$file = \download_url( $thumbnail_url );
			
			if ( \is_wp_error( $file ) ) {
				return;
			}
			
			global $wp_filesystem;
			
			// initialize the WP filesystem if not exists
			if ( empty( $wp_filesystem ) ) {
				\WP_Filesystem();
			}
			
			$wp_filesystem->move( $file, $thumbnail_path );
		}
		
		\update_post_meta( $post->ID, 'embed_privacy_thumbnail_vimeo_' . $id, 'vimeo-' . $id . '.jpg' );
		\update_post_meta( $post->ID, 'embed_privacy_thumbnail_vimeo_' . $id . '_url', $url );
	}
	
	/**
	 * Download and save a YouTube thumbnail.
	 * 
	 * @param	string	$id YouTube video ID
	 * @param	string	$url YouTube video URL
	 */
	public function set_youtube_thumbnail( $id, $url ) {
		$post = \get_post();
		
		if ( ! $post ) {
			return;
		}
		
		require_once ABSPATH . 'wp-admin/includes/file.php';
		
		$directory = $this->get_directory();
		// list of images we try to retrieve
		// see: https://stackoverflow.com/a/2068371
		$images = [
			'maxresdefault',
			'hqdefault',
			'0',
		];
		$thumbnail_url = 'https://img.youtube.com/vi/%1$s/%2$s.jpg';
		
		foreach ( $images as $image ) {
			$thumbnail_path = $directory['base_dir'] . '/youtube-' . $id . '-' . $image . '.jpg';
			
			if ( ! \file_exists( $thumbnail_path ) ) {
				$file = \download_url( \sprintf( $thumbnail_url, $id, $image ) );
				
				if ( \is_wp_error( $file ) ) {
					continue;
				}
				
				global $wp_filesystem;
				
				// initialize the WP filesystem if not exists
				if ( empty( $wp_filesystem ) ) {
					\WP_Filesystem();
				}
				
				$wp_filesystem->move( $file, $thumbnail_path );
			}
			
			\update_post_meta( $post->ID, 'embed_privacy_thumbnail_youtube_' . $id, 'youtube-' . $id . '-' . $image . '.jpg' );
			\update_post_meta( $post->ID, 'embed_privacy_thumbnail_youtube_' . $id . '_url', $url );
			break;
		}
	}
}
