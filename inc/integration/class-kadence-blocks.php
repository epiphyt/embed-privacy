<?php
namespace epiphyt\Embed_Privacy\integration;

use epiphyt\Embed_Privacy\System;

/**
 * Kadence Blocks integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Kadence_Blocks {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_action( 'embed_privacy_print_assets', [ self::class, 'enqueue_assets' ] );
		\add_action( 'embed_privacy_register_assets', [ self::class, 'register_assets' ], 10, 2 );
	}
	
	/**
	 * Enqueue assets.
	 */
	public static function enqueue_assets() {
		if ( System::is_plugin_active( 'kadence-blocks/kadence-blocks.php' ) ) {
			\wp_enqueue_style( 'embed-privacy-kadence-blocks' );
		}
	}
	
	/**
	 * Register assets.
	 * 
	 * @param	bool	$is_debug Whether debug mode is enabled
	 * @param	string	$suffix A filename suffix
	 */
	public static function register_assets( $is_debug, $suffix ) {
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/kadence-blocks' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/kadence-blocks' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-kadence-blocks', $css_file_url, [], $file_version );
	}
}
