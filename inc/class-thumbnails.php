<?php
namespace epiphyt\Embed_Privacy;

use epiphyt\Embed_Privacy\thumbnail\provider\SlideShare;
use epiphyt\Embed_Privacy\thumbnail\provider\Vimeo;
use epiphyt\Embed_Privacy\thumbnail\provider\YouTube;
use epiphyt\Embed_Privacy\thumbnail\Thumbnail;

/**
 * Thumbnails for Embed Privacy.
 * 
 * @deprecated	1.9.0 Use the functionality of epiphyt\Embed_Privacy\thumbnail\Thumbnail instead
 * @since		1.5.0
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
	 * Thumbnails constructor.
	 * 
	 * @deprecated	1.9.0 Use the functionality of epiphyt\Embed_Privacy\thumbnail\Thumbnail instead
	 */
	public function __construct() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative class */
				\esc_html__( 'Use the functionality of %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\Thumbnail'
			),
			'1.9.0'
		);
		
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 * 
	 * @deprecated	1.9.0 Use the functionality of epiphyt\Embed_Privacy\thumbnail\Thumbnail instead
	 */
	public function init() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative class */
				\esc_html__( 'Use the functionality of %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\Thumbnail'
			),
			'1.9.0'
		);
	}
	
	/**
	 * Check and delete orphaned thumbnails.
	 * 
	 * @deprecated	1.9.0 Use epiphyt\Embed_Privacy\thumbnail\Thumbnail::delete_orphaned() instead
	 * 
	 * @param	int			$post_id The post ID
	 * @param	\WP_Post	$post The post object
	 */
	public function check_orphaned( $post_id, $post ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\Thumbnail::delete_orphaned()'
			),
			'1.9.0'
		);
		
		Embed_Privacy::get_instance()->thumbnail->delete_orphaned( $post_id, $post );
	}
	
	/**
	 * Delete thumbnails for a given post ID.
	 * 
	 * @deprecated	1.9.0 Use epiphyt\Embed_Privacy\thumbnail\Thumbnail::delete_thumbnails() instead
	 * 
	 * @param	int		$post_id Post ID
	 */
	public function delete_thumbnails( $post_id ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\Thumbnail::delete_thumbnails()'
			),
			'1.9.0'
		);
		
		Thumbnail::delete_thumbnails( $post_id );
	}
	
	/**
	 * Get path and URL to an embed thumbnail.
	 * 
	 * @deprecated	1.9.0 Use epiphyt\Embed_Privacy\thumbnail\Thumbnail::get_data() instead
	 * 
	 * @param	\WP_Post	$post Post object
	 * @param	string		$url Embedded URL
	 * @return	array Thumbnail path and URL
	 */
	public function get_data( $post, $url ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\Thumbnail::get_data()'
			),
			'1.9.0'
		);
		
		return Embed_Privacy::get_instance()->thumbnail->get_data( $post, $url );
	}
	
	/**
	 * Get the thumbnail directory and URL.
	 * Since we don't want to have a directory per site in a network, we need to
	 * get rid of the site ID in the path.
	 * 
	 * @deprecated	1.9.0 Use epiphyt\Embed_Privacy\thumbnail\Thumbnail::get_directory() instead
	 * @since		1.7.3
	 * 
	 * @return	string[] Thumbnail directory and URL
	 */
	public function get_directory() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\Thumbnail::get_directory()'
			),
			'1.9.0'
		);
		
		return Thumbnail::get_directory();
	}
	
	/**
	 * Get embed thumbnails from the embed provider.
	 * 
	 * @deprecated	1.9.0 Use epiphyt\Embed_Privacy\thumbnail\Thumbnail::get_from_provider() instead
	 * 
	 * @param	string	$output The returned oEmbed HTML
	 * @param	object	$data A data object result from an oEmbed provider
	 * @param	string	$url The URL of the content to be embedded
	 * @return	string The returned oEmbed HTML
	 */
	public function get_from_provider( $output, $data, $url ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\Thumbnail::get_from_provider()'
			),
			'1.9.0'
		);
		
		return Embed_Privacy::get_instance()->thumbnail->get_from_provider( $output, $data, $url );
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
	 * Get a list of supported embed providers for thumbnails.
	 * 
	 * @deprecated	1.9.0
	 * 
	 * @return	array A list of supported embed providers
	 */
	public function get_supported_providers() {
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method is outdated and will be removed in the future.', 'embed-privacy' ),
			'1.9.0'
		);
		
		$providers = Embed_Privacy::get_instance()->thumbnail->get_provider_titles();
		
		/**
		 * Filter the supported providers.
		 * 
		 * @deprecated	1.9.0
		 * @since		1.7.0
		 * 
		 * @param	array	$supported_providers Current supported providers
		 */
		$providers = \apply_filters_deprecated( 'embed_privacy_thumbnail_supported_providers', $providers, '1.9.0' );
		
		return $providers;
	}
	
	/**
	 * Download and save a SlideShare thumbnail.
	 * 
	 * @deprecated	1.9.0 Use epiphyt\Embed_Privacy\thumbnail\provider\SlideShare::save() instead
	 * @since		1.7.0
	 * 
	 * @param	string	$id SlideShare embed ID
	 * @param	string	$url SlideShare deck URL
	 * @param	string	$thumbnail_url SlideShare thumbnail URL
	 */
	public function set_slideshare_thumbnail( $id, $url, $thumbnail_url ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\provider\SlideShare::save()'
			),
			'1.9.0'
		);
		
		SlideShare::save( $id, $url, $thumbnail_url );
	}
	
	/**
	 * Download and save a Vimeo thumbnail.
	 * 
	 * @deprecated	1.9.0 Use epiphyt\Embed_Privacy\thumbnail\provider\Vimeo::save() instead
	 * 
	 * @param	string	$id Vimeo video ID
	 * @param	string	$url Vimeo video URL
	 * @param	string	$thumbnail_url Vimeo thumbnail URL
	 */
	public function set_vimeo_thumbnail( $id, $url, $thumbnail_url ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\provider\Vimeo::save()'
			),
			'1.9.0'
		);
		
		Vimeo::save( $id, $url, $thumbnail_url );
	}
	
	/**
	 * Download and save a YouTube thumbnail.
	 * 
	 * @deprecated	1.9.0 Use epiphyt\Embed_Privacy\thumbnail\provider\YouTube::save() instead
	 * 
	 * @param	string	$id YouTube video ID
	 * @param	string	$url YouTube video URL
	 */
	public function set_youtube_thumbnail( $id, $url ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead.', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\thumbnail\provider\YouTube::save()'
			),
			'1.9.0'
		);
		
		YouTube::save( $id, $url );
	}
}
