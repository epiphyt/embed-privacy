<?php
namespace epiphyt\Embed_Privacy\data;

/**
 * Embed cache related functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.11.0
 */
final class Embed_Cache {
	/**
	 * Get an embed cache entry.
	 * 
	 * @param	string	$url Embed URL
	 * @return	mixed Embed cache entry
	 */
	public static function get( $url ) {
		return \get_transient( self::get_key( $url ) );
	}
	
	/**
	 * Get an embed cache key.
	 * 
	 * @param	string	$url Embed URL
	 * @return	string Embed cache key
	 */
	public static function get_key( $url ) {
		return 'embed_privacy_embed_' . \md5( $url );
	}
	
	/**
	 * Set an embed cache entry.
	 * 
	 * @param	string	$url Embed URL
	 * @param	mixed	$data Data to cache
	 * @return	bool Whether the value was set
	 */
	public static function set( $url, $data ) {
		return \set_transient( self::get_key( $url ), $data, \WEEK_IN_SECONDS );
	}
}
