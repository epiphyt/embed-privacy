<?php
namespace epiphyt\Embed_Privacy\admin;

use WP_Screen;

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
		$screen = \get_current_screen();
		$suffix = \defined( 'WP_DEBUG' ) && \WP_DEBUG || \defined( 'SCRIPT_DEBUG' ) && \SCRIPT_DEBUG ? '' : '.min';
		
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}
		
		if ( ( $hook === 'post.php' && $hook === 'post-new.php' ) || $screen->id === 'epi_embed' ) {
			$script_path = \EPI_EMBED_PRIVACY_BASE . 'assets/js/admin/image-upload' . $suffix . '.js';
			$script_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/admin/image-upload' . $suffix . '.js';
			
			\wp_enqueue_script( 'embed-privacy-admin-image-upload', $script_url, [ 'jquery' ], (string) \filemtime( $script_path ), true );
			
			$style_path = \EPI_EMBED_PRIVACY_BASE . 'assets/style/embed-privacy-admin' . $suffix . '.css';
			$style_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/embed-privacy-admin' . $suffix . '.css';
			
			\wp_enqueue_style( 'embed-privacy-admin-style', $style_url, [], (string) \filemtime( $style_path ) );
		}
		
		if ( $screen->id === 'settings_page_embed_privacy' ) {
			$script_path = \EPI_EMBED_PRIVACY_BASE . 'assets/js/admin/clipboard' . $suffix . '.js';
			$script_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/admin/clipboard' . $suffix . '.js';
			
			\wp_enqueue_script( 'embed-privacy-admin-clipboard', $script_url, [], (string) \filemtime( $script_path ), true );
			\wp_localize_script(
				'embed-privacy-admin-clipboard',
				'embedPrivacyAdminSettings',
				[
					'supportDataCopiedToClipboardFailure' => \__( 'Support data could not be copied to clipboard!', 'embed-privacy' ),
					'supportDataCopiedToClipboardSuccess' => \__( 'Support data copied to clipboard!', 'embed-privacy' ),
				]
			);
			
			$style_path = \EPI_EMBED_PRIVACY_BASE . 'assets/style/settings' . $suffix . '.css';
			$style_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/settings' . $suffix . '.css';
			
			\wp_enqueue_style( 'embed-privacy-admin-settings', $style_url, [], (string) \filemtime( $style_path ) );
		}
	}
}
