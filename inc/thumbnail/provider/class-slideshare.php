<?php
namespace epiphyt\Embed_Privacy\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\Thumbnail;

/**
 * SlideShare thumbnail implementation.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
final class SlideShare extends Thumbnail_Provider implements Thumbnail_Provider_Interface {
	/**
	 * @var		string[] List of valid domains for the thumbnail provider
	 */
	public static $domains = [
		'slideshare.net',
	];
	
	/**
	 * @var		string Thumbnail provider name
	 */
	public static $name = 'slideshare';
	
	/**
	 * {@inheritDoc}
	 */
	public static function get( $data, $url ) {
		if ( ! self::is_provider_embed( $url ) ) {
			return;
		}
		
		$id = self::get_id( $data->html );
		
		if ( $id ) {
			$thumbnail_url = \preg_replace( '/\?.*/', '', $data->thumbnail_url );
			
			self::save( $id, $url, $thumbnail_url );
		}
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_id( $content ) {
		if ( \str_contains( $content, '/embed_code/key/' ) ) {
			$extracted = \preg_replace( '/.*\/embed_code\/key\//', '', $content );
			$parts = \explode( '"', $extracted );
			$id = isset( $parts[0] ) ? $parts[0] : '';
		}
		else {
			// get ID from meta key by embed URL
			$metadata = Thumbnail::get_metadata( self::$name );
			
			foreach ( $metadata as $meta ) {
				if ( ! \str_contains( $meta['meta_value'], $content ) ) {
					continue;
				}
				
				$id = \str_replace(
					[
						Thumbnail::METADATA_PREFIX . '_' . self::$name . '_',
						'_url',
					],
					'',
					$meta['meta_key']
				);
			}
		}
		
		return $id;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_title() {
		return \_x( 'SlideShare', 'embed provider', 'embed-privacy' );
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function save( $id, $url, $thumbnail_url = '' ) {
		$post = \get_post();
		
		if ( ! $post ) {
			return;
		}
		
		$filename = self::$name . '-' . $id . '.jpg';
		$thumbnail_path = Thumbnail::get_directory()['base_dir'] . '/' . $filename;
		
		if ( ! \file_exists( $thumbnail_path ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
			
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
		
		\update_post_meta( $post->ID, Thumbnail::METADATA_PREFIX . '_' . self::$name . '_' . $id, $filename );
		\update_post_meta( $post->ID, Thumbnail::METADATA_PREFIX . '_' . self::$name . '_' . $id . '_url', $url );
	}
}
