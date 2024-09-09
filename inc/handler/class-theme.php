<?php
namespace epiphyt\Embed_Privacy\handler;

/**
 * Theme handler.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Theme {
	/**
	 * Check if the current theme is matching your name.
	 * 
	 * @param	string	$name The theme name to test
	 * @return	bool True if the current theme is matching, false otherwise
	 */
	public static function is( $name ) {
		$name = \strtolower( $name );
		$theme_name = \strtolower( \wp_get_theme()->get( 'Name' ) );
		$theme_template = \strtolower( \wp_get_theme()->get( 'Template' ) );
		
		return $theme_name === $name || $theme_template === $name;
	}
}
