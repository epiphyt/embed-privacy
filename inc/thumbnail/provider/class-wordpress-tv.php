<?php
namespace epiphyt\Embed_Privacy\thumbnail\provider;

use DOMDocument;
use DOMXPath;
use epiphyt\Embed_Privacy\thumbnail\Thumbnail;

/**
 * WordPress TV thumbnail implementation.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
final class WordPress_TV extends Thumbnail_Provider implements Thumbnail_Provider_Interface {
	/**
	 * @var		string[] List of valid domains for the thumbnail provider
	 */
	public static $domains = [
		'wordpress.tv',
	];
	
	/**
	 * @var		string Thumbnail provider name
	 */
	public static $name = 'wordpress-tv';
	
	/**
	 * {@inheritDoc}
	 */
	public static function get( $data, $url ) {
		if ( ! self::is_provider_embed( $url ) ) {
			return;
		}
		
		$id = self::get_id( $data->html );
		
		if ( $id ) {
			self::save( $id, $url );
		}
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_id( $content ) {
		$id = '';
		
		if ( \str_contains( $content, '/video.wordpress.com/embed/' ) ) {
			$extracted = \preg_replace( '/.*video\.wordpress\.com\/embed\//', '', $content ); // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
			$parts = \explode( '?', $extracted );
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
		return \_x( 'WordPress TV', 'embed provider', 'embed-privacy' );
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
		$thumbnail_path = $directory['base_dir'] . '/' . self::$name . '-' . $id . '.jpg';
		
		if ( ! \file_exists( $thumbnail_path ) ) {
			$use_errors = \libxml_use_internal_errors( true );
			$dom = new DOMDocument();
			// download embedded page
			$request = \wp_remote_get( $url );
			
			$dom->loadHTML(
				\wp_remote_retrieve_body( $request ),
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
			$xpath = new DOMXPath( $dom );
			// get thumbnail URL from og:image meta
			$og_image = $xpath->evaluate( '//meta[@property="og:image"]/@content' )->item( 0 );
			
			\libxml_use_internal_errors( $use_errors );
			
			if ( $og_image === null ) {
				return;
			}
			
			$file = \download_url( $og_image->value );
			
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
