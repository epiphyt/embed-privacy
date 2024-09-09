<?php
namespace epiphyt\Embed_Privacy\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\Thumbnail;

/**
 * Vimeo thumbnail implementation.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
final class Vimeo extends Thumbnail_Provider implements Thumbnail_Provider_Interface {
	/**
	 * @var		string[] List of valid domains for the thumbnail provider
	 */
	public static $domains = [
		'vimeo.com',
	];
	
	/**
	 * @var		string Thumbnail provider name
	 */
	public static $name = 'vimeo';
	
	/**
	 * {@inheritDoc}
	 */
	public static function get( $data, $url ) {
		if ( ! self::is_provider_embed( $url ) ) {
			return;
		}
		
		// the thumbnail URL has usually something like _295x166 in the end
		// remove this to get the maximum resolution
		$thumbnail_url = \substr( $data->thumbnail_url, 0, \strrpos( $data->thumbnail_url, '_' ) );
		$id = self::get_id( $url );
		
		if ( $id ) {
			self::save( $id, $url, $thumbnail_url );
		}
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_id( $content ) {
		$id = \str_replace(
			[
				'https://vimeo.com/',
				'https://player.vimeo.com/video/',
			],
			'',
			$content
		);
		
		if ( \str_contains( $id, '?' ) ) {
			$id = \substr( $id, 0, \strpos( $id, '?' ) );
		}
		
		return $id;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_title() {
		return \_x( 'Vimeo', 'embed provider', 'embed-privacy' );
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function save( $id, $url, $thumbnail_url = '' ) {
		$post = \get_post();
		
		if ( ! $post ) {
			return;
		}
		
		$thumbnail_path = Thumbnail::get_directory()['base_dir'] . '/' . self::$name . '-' . $id . '.jpg';
		
		if ( ! \file_exists( $thumbnail_path ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
			
			$file = \download_url( $thumbnail_url );
			
			if ( \is_wp_error( $file ) ) {
				return;
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
			self::$name . '-' . $id . '.jpg'
		);
		\update_post_meta( $post->ID, Thumbnail::METADATA_PREFIX . '_' . self::$name . '_' . $id . '_url', $url );
	}
}
