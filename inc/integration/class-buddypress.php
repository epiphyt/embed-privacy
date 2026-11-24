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
		\add_filter( 'bp_embed_oembed_html', [ self::class, 'replace_activity_comment_oembed' ], 10, 3 );
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
	
	/**
	 * Replace oEmbeds in BuddyPress activity comments.
	 * 
	 * BuddyPress does not use the WordPress `embed_oembed_html` filter for embeds
	 * in activity comments, but its own `bp_embed_oembed_html` filter. Forward it
	 * to the WordPress filter so the regular oEmbed replacement applies there too.
	 * 
	 * @since	1.14.1
	 * 
	 * @param	string	$html Current embed HTML
	 * @param	string	$url URL of the embedded content
	 * @param	array	$attributes Embed attributes
	 * @return	string Updated embed HTML
	 */
	public static function replace_activity_comment_oembed( $html, $url, array $attributes ) {
		return \apply_filters( 'embed_oembed_html', $html, $url, $attributes );
	}
}
