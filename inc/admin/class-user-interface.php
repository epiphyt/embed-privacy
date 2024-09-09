<?php
namespace epiphyt\Embed_Privacy\admin;

/**
 * Admin user interface functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class User_Interface {
	/**
	 * Initialize functions.
	 */
	public static function init() {
		\add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		\add_filter( 'plugin_row_meta', [ self::class, 'add_meta_link' ], 10, 2 );
	}
	
	/**
	 * Add plugin meta links.
	 * 
	 * @param	array	$input Registered links.
	 * @param	string	$file  Current plugin file.
	 * @return	array Merged links
	 */
	public static function add_meta_link( $input, $file ) {
		// bail on other plugins
		if ( $file !== \plugin_basename( \EPI_EMBED_PRIVACY_BASE ) ) {
			return $input;
		}
		
		return \array_merge(
			$input,
			[
				'<a href="https://epiph.yt/en/embed-privacy/documentation/?version=' . \rawurlencode( \EMBED_PRIVACY_VERSION ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'Documentation', 'embed-privacy' ) . '</a>',
			]
		);
	}
	
	/**
	 * Enqueue admin assets.
	 * 
	 * @param	string	$hook The current hook
	 */
	public static function enqueue_assets( $hook ) {
		// we need it just on the post page
		if ( ( $hook !== 'post.php' && $hook !== 'post-new.php' ) || \get_current_screen()->id !== 'epi_embed' ) {
			return;
		}
		
		$suffix = \defined( 'WP_DEBUG' ) && \WP_DEBUG || \defined( 'SCRIPT_DEBUG' ) && \SCRIPT_DEBUG ? '' : '.min';
		$script_path = \EPI_EMBED_PRIVACY_BASE . 'assets/js/admin/image-upload' . $suffix . '.js';
		$script_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/admin/image-upload' . $suffix . '.js';
		
		\wp_enqueue_script( 'embed-privacy-admin-image-upload', $script_url, [ 'jquery' ], \filemtime( $script_path ), true );
		
		$style_path = \EPI_EMBED_PRIVACY_BASE . 'assets/style/embed-privacy-admin' . $suffix . '.css';
		$style_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/embed-privacy-admin' . $suffix . '.css';
		
		\wp_enqueue_style( 'embed-privacy-admin-style', $style_url, [], \filemtime( $style_path ) );
	}
}
