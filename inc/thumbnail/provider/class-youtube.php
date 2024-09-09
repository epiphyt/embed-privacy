<?php
namespace epiphyt\Embed_Privacy\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\Thumbnail;

/**
 * YouTube thumbnail implementation.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
final class YouTube extends Thumbnail_Provider implements Thumbnail_Provider_Interface {
	/**
	 * @var		string[] List of valid domains for the thumbnail provider
	 */
	public static $domains = [
		'youtu.be',
		'youtube.com',
	];
	
	/**
	 * @var		string Thumbnail provider name
	 */
	public static $name = 'youtube';
	
	/**
	 * {@inheritDoc}
	 */
	public static function get( $data, $url ) {
		if ( ! self::is_provider_embed( $url ) ) {
			return;
		}
		
		$id = self::get_id_by_thumbnail_url( $data->thumbnail_url );
		
		if ( $id ) {
			self::save( $id, $url );
		}
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_id( $content ) {
		$id = \str_replace(
			[
				'https://www.youtube.com/watch?v=',
				'https://www.youtube.com/embed/',
				'https://youtu.be/',
			],
			'',
			$content
		);
		$id = \str_contains( $id, '?' ) ? \substr( $id, 0, \strpos( $id, '?' ) ) : $id;
		
		return $id;
	}
	
	/**
	 * Get the thumbnail ID from a thumbnail URL.
	 * 
	 * @param	string	$url Thumbnail URL to get the thumbnail ID from
	 * @return	string Thumbnail ID
	 */
	public static function get_id_by_thumbnail_url( $url ) {
		// format: <id>/<thumbnail-name>.jpg
		$extracted = \str_replace( 'https://i.ytimg.com/vi/', '', $url );
		// first part is the ID
		$parts = \explode( '/', $extracted );
		
		return isset( $parts[0] ) ? $parts[0] : '';
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_title() {
		return \_x( 'YouTube', 'embed provider', 'embed-privacy' );
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function save( $id, $url, $thumbnail_url = '' ) {
		$post = \get_post();
		
		if ( ! $post ) {
			return;
		}
		
		require_once \ABSPATH . 'wp-admin/includes/file.php';
		
		$directory = Thumbnail::get_directory();
		// list of images we try to retrieve
		// see: https://stackoverflow.com/a/2068371
		$images = [
			'maxresdefault',
			'hqdefault',
			'0',
		];
		$thumbnail_url = 'https://img.youtube.com/vi/%1$s/%2$s.jpg';
		
		foreach ( $images as $image ) {
			$thumbnail_path = $directory['base_dir'] . '/' . self::$name . '-' . $id . '-' . $image . '.jpg';
			
			if ( ! \file_exists( $thumbnail_path ) ) {
				$file = \download_url( \sprintf( $thumbnail_url, $id, $image ) );
				
				if ( \is_wp_error( $file ) ) {
					continue;
				}
				
				/** @var	\WP_Filesystem_Direct $wp_filesystem */
				global $wp_filesystem;
				
				// initialize the WP filesystem if not exists
				if ( empty( $wp_filesystem ) ) {
					\WP_Filesystem();
				}
				
				$wp_filesystem->move( $file, $thumbnail_path );
			}
			
			\update_post_meta(
				$post->ID,
				Thumbnail::METADATA_PREFIX . '_' . self::$name . '_' . $id,
				self::$name . '-' . $id . '-' . $image . '.jpg'
			);
			\update_post_meta( $post->ID, Thumbnail::METADATA_PREFIX . '_' . self::$name . '_' . $id . '_url', $url );
			break;
		}
	}
}
