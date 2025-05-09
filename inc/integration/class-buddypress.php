<?php
namespace epiphyt\Embed_Privacy\integration;

use epiphyt\Embed_Privacy\data\Replacer;
use epiphyt\Embed_Privacy\Embed_Privacy;

/**
 * BuddyPress integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.11.0
 */
final class Buddypress {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_action( 'bp_enqueue_community_scripts', [ self::class, 'register_scripts' ], 2 );
		\add_action( 'bp_enqueue_community_scripts', [ self::class, 'enqueue_assets' ] );
		\add_filter( 'bp_get_activity_content_body', [ self::class, 'replace_activity_content' ] );
	}
	
	/**
	 * Enqueue Embed Privacy assets for BuddyPress.
	 */
	public static function enqueue_assets() {
		Embed_Privacy::get_instance()->frontend->print_assets();
	}
	
	/**
	 * Register Embed Privacy assets for BuddyPress.
	 */
	public static function register_scripts() {
		Embed_Privacy::get_instance()->frontend->register_assets();
	}
	
	/**
	 * Replace activity stream content.
	 * 
	 * @param	string	$content Current activity stream content
	 * @return	string Updated activity stream content
	 */
	public static function replace_activity_content( $content ) {
		return Replacer::replace_embeds( $content );
	}
}
