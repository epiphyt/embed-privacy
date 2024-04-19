<?php
namespace epiphyt\Embed_Privacy\thumbnail\provider;

/**
 * Thumbnail provider interface.
 */
interface Thumbnail_Provider_Interface {
	/**
	 * Get the thumbnail from a source string.
	 * 
	 * @param	object	$data A data object result from an oEmbed provider
	 * @param	string	$url The URL of the content to be embedded
	 */
	public static function get( $data, $url );
	
	/**
	 * Get the thumbnail ID from an embed content.
	 * 
	 * @param	string	$content Embed content/URL to get the thumbnail ID from
	 * @return	string Thumbnail ID
	 */
	public static function get_id( $content );
	
	/**
	 * Get a thumbnail path.
	 * 
	 * @param	string	$filename Thumbnail filename
	 * @return	string Absolute thumbnail path
	 */
	public static function get_path( $filename );
	
	/**
	 * Get the thumbnail provider title.
	 * 
	 * @return	string Thumbnail provider title
	 */
	public static function get_title();
	
	/**
	 * Get a thumbnail URL.
	 * 
	 * @param	string	$filename Thumbnail filename
	 * @return	string Thumbnail URL
	 */
	public static function get_url( $filename );
	
	/**
	 * Check whether the given URL is from this provider.
	 * 
	 * @param	string	$url The URL of the content to be embedded
	 * @return	bool Whether the given URL is from this provider
	 */
	public static function is_provider_embed( $url );
	
	/**
	 * Download and save a thumbnail.
	 * 
	 * @param	string	$id Embed ID
	 * @param	string	$url Embed URL
	 * @param	string	$thumbnail_url Optional thumbnail URL if already available
	 */
	public static function save( $id, $url, $thumbnail_url = '' );
}
