<?php
namespace epiphyt\Embed_Privacy\integration;

/**
 * ActivityPub integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Activitypub {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'embed_privacy_is_ignored_request', [ self::class, 'set_ignored_request' ] );
	}
	
	/**
	 * Set whether the current request is an ActivityPub request and thus should be ignored.
	 * Return the unaltered value if it's already ignored.
	 * 
	 * @param	bool	$is_ignored Whether the current request is ignored
	 * @return	bool Whether the current request is ignored
	 */
	public static function set_ignored_request( $is_ignored ) {
		if ( $is_ignored ) {
			return $is_ignored;
		}
		
		return \function_exists( 'Activitypub\is_activitypub_request' ) && \Activitypub\is_activitypub_request();
	}
}
