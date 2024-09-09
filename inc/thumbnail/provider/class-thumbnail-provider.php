<?php
namespace epiphyt\Embed_Privacy\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\Thumbnail;

/**
 * An abstract implementation of a thumbnail provider.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
abstract class Thumbnail_Provider implements Thumbnail_Provider_Interface {
	/**
	 * @var		string[] List of valid domains for the thumbnail provider
	 */
	public static $domains = [];
	
	/**
	 * @var		string Thumbnail provider name
	 */
	public static $name = '';
	
	/**
	 * {@inheritDoc}
	 */
	public static function get( $data, $url ) { } // phpcs:ignore SlevomatCodingStandard.Functions.DisallowEmptyFunction.EmptyFunction
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_id( $url ) { } // phpcs:ignore SlevomatCodingStandard.Functions.DisallowEmptyFunction.EmptyFunction
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_path( $filename ) {
		return Thumbnail::get_directory()['base_dir'] . '/' . $filename;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_title() {
		return '';
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function get_url( $filename ) {
		return Thumbnail::get_directory()['base_url'] . '/' . $filename;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function is_provider_embed( $url ) {
		$is_provider_embed = false;
		
		foreach ( static::$domains as $domain ) {
			if ( \str_contains( $url, $domain ) ) {
				$is_provider_embed = true;
				break;
			}
		}
		
		return $is_provider_embed;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function save( $id, $url, $thumbnail_url = '' ) { } // phpcs:ignore SlevomatCodingStandard.Functions.DisallowEmptyFunction.EmptyFunction
}
