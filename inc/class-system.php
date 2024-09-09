<?php
namespace epiphyt\Embed_Privacy;

/**
 * System functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.2
 */
final class System {
	/**
	 * Determines whether a plugin is active.
	 * 
	 * Basically a wrapper around core's is_plugin_active(), but with auto-loading.
	 * 
	 * @param	string	$plugin Path to the plugin file relative to the plugins directory
	 * @return	bool Whether the plugin is active
	 */
	public static function is_plugin_active( $plugin ) {
		if ( ! \function_exists( 'is_plugin_active' ) ) {
			include_once \ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		return \is_plugin_active( $plugin );
	}
}
