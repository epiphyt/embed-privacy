<?php
namespace epiphyt\Embed_Privacy\embed;

/**
 * Embed provider related functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Provider {
	/**
	 * Initialize functionality.
	 */
	public function init() {
		\add_filter( 'embed_privacy_provider_name', [ self::class, 'sanitize_title' ] );
	}
	
	/**
	 * Sanitize the embed provider title.
	 * 
	 * @param	string	$title Current provider title
	 * @return	string Sanitized provider title
	 */
	public static function sanitize_title( $title ) {
		return \preg_replace( '/-\d+$/', '', \sanitize_title( \strtolower( $title ) ) );
	}
}
