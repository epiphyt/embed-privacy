<?php
namespace epiphyt\Embed_Privacy;
use function array_pop;
use function define;
use function defined;
use function explode;
use function file_exists;
use function plugin_dir_url;
use function spl_autoload_register;
use function str_replace;
use function strtolower;
use const WP_PLUGIN_DIR;

/*
Plugin Name:	Embed Privacy
Description:	Embed Privacy prevents from loading external embeds directly and lets the user control which one should be loaded.
Version:		1.4.7
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
along with Embed Privacy. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// exit if ABSPATH is not defined
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EPI_EMBED_PRIVACY_BASE' ) ) define( 'EPI_EMBED_PRIVACY_BASE', WP_PLUGIN_DIR . '/embed-privacy/' );
if ( ! defined( 'EPI_EMBED_PRIVACY_URL' ) ) define( 'EPI_EMBED_PRIVACY_URL', plugin_dir_url( EPI_EMBED_PRIVACY_BASE . 'embed-privacy.php' ) );

/**
 * Autoload all necessary classes.
 * 
 * @param	string		$class The class name of the autoloaded class
 */
spl_autoload_register( function( $class ) {
	$path = explode( '\\', $class );
	$filename = str_replace( '_', '-', strtolower( array_pop( $path ) ) );
	$class = str_replace(
		[ 'epiphyt\embed_privacy\\', '\\', '_' ],
		[ '', '/', '-' ],
		strtolower( $class )
	);
	$class = str_replace( $filename, 'class-' . $filename, $class );
	$maybe_file = __DIR__ . '/inc/' . $class . '.php';
	
	if ( file_exists( $maybe_file ) ) {
		require_once( __DIR__ . '/inc/' . $class . '.php' );
	}
} );

Embed_Privacy_Widget_Output_Filter::get_instance();
$embed_privacy = Embed_Privacy::get_instance();
$embed_privacy->set_plugin_file( __FILE__ );
$embed_privacy->init();
