<?php
namespace epiphyt\Embed_Privacy;

/*
Plugin Name:	Embed Privacy
Description:	Embed Privacy prevents from loading external embeds directly and lets the user control which one should be loaded.
Version:		1.0.0
Author:			Epiphyt
Author URI:		https://epiph.yt
License:		GPL2
License URI:	https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:	embed-privacy
Domain Path:	/languages

Embed Privacy is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Embed Privacy is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Fury. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// exit if ABSPATH is not defined
\defined( 'ABSPATH' ) || exit;

if ( ! \defined( 'EPI_EMBED_PRIVACY_BASE' ) ) \define( 'EPI_EMBED_PRIVACY_BASE', \plugin_dir_path( __FILE__ ) );
if ( ! \defined( 'EPI_EMBED_PRIVACY_URL' ) ) \define( 'EPI_EMBED_PRIVACY_URL', \plugin_dir_url( __FILE__ ) );

/**
 * Autoload all necessary classes.
 * 
 * @param	string		$class The class name of the autoloaded class
 */
\spl_autoload_register( function( string $class ) {
	$path = \explode( '\\', $class );
	$filename = \str_replace( '_', '-', \strtolower( \array_pop( $path ) ) );
	$class = \str_replace(
		[ 'epiphyt\embed_privacy\\', '\\', '_' ],
		[ '', '/', '-' ],
		\strtolower( $class )
	);
	$class = \str_replace( $filename, 'class-' . $filename, $class );
	$maybe_file = __DIR__ . '/inc/' . $class . '.php';
	
	if ( \file_exists( $maybe_file ) ) {
		require_once( __DIR__ . '/inc/' . $class . '.php' );
	}
} );

$embed_privacy = new Embed_Privacy();

\register_activation_hook( __FILE__, [ $embed_privacy, 'clear_embed_cache' ] );
\register_deactivation_hook( __FILE__, [ $embed_privacy, 'clear_embed_cache' ] );
