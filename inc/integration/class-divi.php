<?php
namespace epiphyt\Embed_Privacy\integration;

use epiphyt\Embed_Privacy\handler\Theme;

/**
 * Divi integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Divi {
	/**
	 * Initialize functionality.
	 */
	public function init() {
		\add_action( 'embed_privacy_register_assets', [ self::class, 'register_assets' ], 10, 2 );
		\add_filter( 'embed_privacy_print_assets', [ self::class, 'enqueue_assets' ] );
		\add_filter( 'et_builder_resolve_dynamic_content', [ self::class, 'add_dynamic_content_filter' ], 5 );
		\add_filter( 'et_builder_resolve_dynamic_content', [ self::class, 'remove_dynamic_content_filter' ], \PHP_INT_MAX );
	}
	
	/**
	 * Add filter for dynamic content.
	 * 
	 * @since	1.10.9
	 * 
	 * @param	string	$content Current dynamic content
	 * @return	string Current dynamic content
	 */
	public static function add_dynamic_content_filter( $content ) {
		\add_filter( 'wp_kses_allowed_html', [ self::class, 'allow_script_in_post' ], 10, 2 );
		
		return $content;
	}
	
	/**
	 * Allow script tags in post, since Divi runs a wp_kses_post over the embed.
	 * 
	 * @since	1.10.9
	 * 
	 * @param	array	$html List of allowed HTML tags and attributes
	 * @param	string	$context Current context
	 * @return	array Updated list of allowed HTML
	 */
	public static function allow_script_in_post( array $html, $context ) {
		if ( $context === 'post' ) {
			if ( ! isset( $html['input'] ) ) {
				$html['input'] = [
					'class' => true,
					'date-*' => true,
					'id' => true,
					'type' => true,
					'value' => true,
				];
			}
			
			$html['script'] = [
				'type' => true,
			];
		}
		
		return $html;
	}
	
	/**
	 * Enqueue assets.
	 */
	public static function enqueue_assets() {
		if ( Theme::is( 'Divi' ) ) {
			\wp_enqueue_script( 'embed-privacy-divi' );
			\wp_enqueue_style( 'embed-privacy-divi' );
		}
	}
	
	/**
	 * Register assets.
	 * 
	 * @param	bool	$is_debug Whether debug mode is enabled
	 * @param	string	$suffix A filename suffix
	 */
	public static function register_assets( $is_debug, $suffix ) {
		$js_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/divi' . $suffix . '.js';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/js/divi' . $suffix . '.js' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_script( 'embed-privacy-divi', $js_file_url, [], $file_version, [ 'strategy' => 'defer' ] );
		
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/divi' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/divi' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-divi', $css_file_url, [], $file_version );
	}
	
	/**
	 * Remove filter for dynamic content.
	 * 
	 * @since	1.10.9
	 * 
	 * @param	string	$content Current dynamic content
	 * @return	string Current dynamic content
	 */
	public static function remove_dynamic_content_filter( $content ) {
		\remove_filter( 'wp_kses_allowed_html', [ self::class, 'allow_script_in_post' ], 10 );
		
		return $content;
	}
}
