<?php
namespace epiphyt\Embed_Privacy\integration;

use epiphyt\Embed_Privacy\data\Replacer;
use epiphyt\Embed_Privacy\System;

/**
 * wpForo Embeds integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.11.0
 */
final class Wpforo_Embeds {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_action( 'embed_privacy_print_assets', [ self::class, 'enqueue_assets' ] );
		\add_action( 'embed_privacy_register_assets', [ self::class, 'register_assets' ], 10, 2 );
		\add_filter( 'wpforo_content_after', [ self::class, 'replace' ], 10 ); // use _after filter to process data after wpautop()
	}
	
	/**
	 * Enqueue assets.
	 */
	public static function enqueue_assets() {
		if ( System::is_plugin_active( 'wpforo-embeds/wpforoembeds.php' ) ) {
			\wp_enqueue_style( 'embed-privacy-wpforo-embeds' );
		}
	}
	
	/**
	 * Register assets.
	 * 
	 * @param	bool	$is_debug Whether debug mode is enabled
	 * @param	string	$suffix A filename suffix
	 */
	public static function register_assets( $is_debug, $suffix ) {
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/wpforo-embeds' . $suffix . '.css';
		$file_version = $is_debug ? (string) \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/wpforo-embeds' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-wpforo-embeds', $css_file_url, [], $file_version );
	}
	
	/**
	 * Replace activity stream content.
	 * 
	 * @param	string	$content Current activity stream content
	 * @return	string Updated activity stream content
	 */
	public static function replace( $content ) {
		return Replacer::replace_embeds( $content );
	}
}
