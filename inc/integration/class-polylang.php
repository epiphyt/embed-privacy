<?php
namespace epiphyt\Embed_Privacy\integration;

/**
 * Polylang integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Polylang {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'embed_privacy_provider_name', [ self::class, 'sanitize_title' ] );
		\add_filter( 'pll_get_post_types', [ self::class, 'register_post_type' ], 10, 2 );
	}
	
	/**
	 * Register post type in Polylang to allow translation.
	 * 
	 * @param	array	$post_types List of current translatable custom post types
	 * @param	bool	$is_settings Whether the current page is the settings page
	 * @return	array Updated list of translatable custom post types
	 */
	public static function register_post_type( array $post_types, $is_settings ) {
		if ( $is_settings ) {
			unset( $post_types['epi_embed'] );
		}
		else {
			$post_types['epi_embed'] = 'epi_embed';
		}
		
		return $post_types;
	}
	
	/**
	 * Sanitize the embed provider title.
	 * 
	 * @param	string	$title Current provider title
	 * @return	string Sanitized provider title
	 */
	public static function sanitize_title( $title ) {
		if (
			\is_plugin_active( 'polylang/polylang.php' )
			&& \function_exists( 'pll_current_language' )
			&& \str_ends_with( $title, '-' . \pll_current_language() )
		) {
			$title = \preg_replace( '/-' . \preg_quote( \pll_current_language(), '/' ) . '$/', '', $title );
		}
		
		return $title;
	}
}
