<?php
namespace epiphyt\Embed_Privacy;

/*
Plugin Name:		Embed Privacy
Plugin URL:			https://epiph.yt/en/embed-privacy/
Description:		Embed Privacy prevents from loading external embeds directly and lets the user control which one should be loaded.
Version:			1.10.10
Author:				Epiphyt
Author URI:			https://epiph.yt/en/
License:			GPL2
License URI:		https://www.gnu.org/licenses/gpl-2.0.html
Requires at least:	5.9
Requires PHP:		5.6
Tested up to:		6.7
Text Domain:		embed-privacy

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
\defined( 'ABSPATH' ) || exit;

\define( 'EMBED_PRIVACY_VERSION', '1.10.10' );

if ( ! \defined( 'EPI_EMBED_PRIVACY_BASE' ) ) {
	\define( 'EPI_EMBED_PRIVACY_BASE', \WP_PLUGIN_DIR . '/embed-privacy/' );
}

if ( ! \defined( 'EPI_EMBED_PRIVACY_FILE' ) ) {
	\define( 'EPI_EMBED_PRIVACY_FILE', __FILE__ );
}

if ( ! \defined( 'EPI_EMBED_PRIVACY_URL' ) ) {
	\define( 'EPI_EMBED_PRIVACY_URL', \plugin_dir_url( \EPI_EMBED_PRIVACY_BASE . 'embed-privacy.php' ) );
}

if ( ! \class_exists( 'DOMDocument' ) ) {
	/**
	 * Disable the plugin if the php-dom extension is missing.
	 */
	function disable_plugin() {
		?>
		<div class="notice notice-error">
			<p><?php \esc_html_e( 'The PHP extension "Document Object Model" (php-dom) is missing. Embed Privacy requires this extension to be installed and enabled. Please ask your hosting provider to install and enable it. Embed Privacy disables itself now. Please re-enable it again if the extension is installed and enabled.', 'embed-privacy' ); ?></p>
		</div>
		<?php
		\deactivate_plugins( \plugin_basename( __FILE__ ) );
	}
	
	\add_action( 'admin_notices', __NAMESPACE__ . '\disable_plugin' );
}

/**
 * Autoload all necessary classes.
 * 
 * @param	string	$class_name The class name of the auto-loaded class
 */
\spl_autoload_register( static function( $class_name ) {
	$path = \explode( '\\', $class_name );
	$filename = \str_replace( '_', '-', \strtolower( \array_pop( $path ) ) );
	$class_name = \str_replace(
		[ 'epiphyt\embed_privacy\\', '\\', '_' ],
		[ '', '/', '-' ],
		\strtolower( $class_name )
	);
	$name_pos = \strrpos( $class_name, $filename );
	
	if ( $name_pos !== false ) {
		$class_name = \substr_replace( $class_name, 'class-' . $filename, $name_pos, \strlen( $filename ) );
	}
	
	$maybe_file = __DIR__ . '/inc/' . $class_name . '.php';
	
	if ( \file_exists( $maybe_file ) ) {
		require_once $maybe_file;
	}
} );

Embed_Privacy::get_instance()->set_plugin_file( __FILE__ );
Embed_Privacy::get_instance()->init();
