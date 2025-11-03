<?php
namespace epiphyt\Embed_Privacy\admin;

use epiphyt\Embed_Privacy\data\Providers;

/**
 * Admin support data related functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.11.0
 */
final class Support_Data {
	const CAPABILITY = 'manage_options';
	
	/**
	 * Get support data.
	 * 
	 * @return	string Support data
	 */
	public static function get() {
		$output = self::get_system_data();
		$output .= self::get_plugin_data();
		$output .= self::get_theme_data();
		$output .= self::get_provider_data();
		
		return \trim( $output );
	}
	
	/**
	 * Get database data.
	 * 
	 * @return	string Database name and version
	 */
	private static function get_database_data() {
		/** @var \epiphyt\Embed_Privacy\admin\wpdb $wpdb */
		global $wpdb;
		
		// phpcs:disable WordPress.DB.RestrictedFunctions.mysql_mysql_get_server_info, WordPress.DB.RestrictedFunctions.mysql_mysqli_get_server_info
		if ( empty( $wpdb->use_mysqli ) && \function_exists( 'mysql_get_server_info' ) ) {
			/** @disregard P1010 existence is checked above */
			$mysql_server_type = \mysql_get_server_info( $wpdb->dbh ); // phpcs:ignore PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved
		}
		else {
			$mysql_server_type = \mysqli_get_server_info( $wpdb->dbh );
		}
		// phpcs:enable WordPress.DB.RestrictedFunctions.mysql_mysql_get_server_info, WordPress.DB.RestrictedFunctions.mysql_mysqli_get_server_info
		
		$name = \stristr( $mysql_server_type, 'mariadb' ) ? 'MariaDB' : 'MySQL';
		$version = $wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		
		return $name . ' ' . $version;
	}
	
	/**
	 * Get a formatted heading.
	 * 
	 * @param	string	$title Title to format
	 * @return	string Formatted heading
	 */
	private static function get_heading( $title ) {
		$output = $title . \PHP_EOL;
		$output .= \str_repeat( '-', \mb_strlen( $title ) ) . \PHP_EOL;
		
		return $output;
	}
	
	/**
	 * Get plugin support data.
	 * 
	 * @return	string Plugin support data
	 */
	private static function get_plugin_data() {
		$active_plugins = \wp_get_active_and_valid_plugins();
		$data = [];
		$output = self::get_heading( \__( 'Active plugins', 'embed-privacy' ) );
		
		foreach ( $active_plugins as $plugin ) {
			$plugin_data = \get_plugin_data( $plugin, false, false );
			$data[] = [
				'name' => $plugin_data['Name'],
				'plugin_uri' => $plugin_data['PluginURI'],
				'version' => $plugin_data['Version'],
			];
		}
		
		$output .= \var_export( $data, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		
		return $output . \PHP_EOL . \PHP_EOL;
	}
	
	/**
	 * Get embed provider data.
	 * 
	 * @return	string Provider data
	 */
	private static function get_provider_data() {
		$output = self::get_heading( \__( 'Active providers', 'embed-privacy' ) );
		$providers = Providers::get_instance()->get_list();
		$data = [];
		
		foreach ( $providers as $provider ) {
			$data[] = [
				'is_disabled' => $provider->is_disabled(),
				'name' => $provider->get_name(),
				'pattern' => $provider->get_pattern(),
				'title' => $provider->get_title(),
			];
		}
		
		$output .= \var_export( $data, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		
		return $output . \PHP_EOL . \PHP_EOL;
	}
	
	/**
	 * Get theme support data.
	 * 
	 * @return	string Theme support data
	 */
	private static function get_theme_data() {
		$active_themes = \wp_get_active_and_valid_themes();
		$data = [];
		$output = self::get_heading( \__( 'Active themes', 'embed-privacy' ) );
		
		foreach ( $active_themes as $theme ) {
			$theme_data = \wp_get_theme( '', \dirname( $theme ) );
			$data[] = [
				'name' => $theme_data->get( 'Name' ),
				'theme_uri' => $theme_data->get( 'ThemeURI' ),
				'version' => $theme_data->get( 'Version' ),
			];
		}
		
		$output .= \var_export( $data, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		
		return $output . \PHP_EOL . \PHP_EOL;
	}
	
	/**
	 * Get system data.
	 * 
	 * @return	string System data
	 */
	private static function get_system_data() {
		$output = self::get_heading( \__( 'System data', 'embed-privacy' ) );
		/* translators: version */
		$output .= \sprintf( \__( 'Embed Privacy migrate version: %s', 'embed-privacy' ), \get_option( 'embed_privacy_migrate_version' ) ) . \PHP_EOL;
		/* translators: true/false */
		$output .= \sprintf( \__( 'Embed Privacy is migrating: %s', 'embed-privacy' ), \get_option( 'embed_privacy_is_migrating' ) ? 'true' : 'false' ) . \PHP_EOL;
		/* translators: count */
		$output .= \sprintf( \__( 'Embed Privacy migration count: %s', 'embed-privacy' ), \get_option( 'embed_privacy_migration_count' ) ?: 0 ) . \PHP_EOL;
		/* translators: version */
		$output .= \sprintf( \__( 'WordPress version: %s', 'embed-privacy' ), self::get_version() ) . \PHP_EOL;
		/* translators: site URL */
		$output .= \sprintf( \__( 'Site URL: %s', 'embed-privacy' ), \site_url() ) . \PHP_EOL;
		/* translators: home URL */
		$output .= \sprintf( \__( 'Home URL: %s', 'embed-privacy' ), \home_url() ) . \PHP_EOL;
		/* translators: memory limit */
		$output .= \sprintf( \__( 'WordPress memory limit: %s', 'embed-privacy' ), \WP_MAX_MEMORY_LIMIT ) . \PHP_EOL;
		/* translators: memory limit */
		$output .= \sprintf( \__( 'PHP memory limit: %s', 'embed-privacy' ), \function_exists( 'ini_get' ) ? \ini_get( 'memory_limit' ) : \__( 'unknown', 'embed-privacy' ) ) . \PHP_EOL;
		/* translators: time limit */
		$output .= \sprintf( \__( 'PHP time limit: %s', 'embed-privacy' ), \function_exists( 'ini_get' ) ? \ini_get( 'max_execution_time' ) : \__( 'unknown', 'embed-privacy' ) ) . \PHP_EOL;
		/* translators: version */
		$output .= \sprintf( \__( 'PHP version: %s', 'embed-privacy' ), \phpversion() ) . \PHP_EOL;
		/* translators: database data */
		$output .= \sprintf( \__( 'Database: %s', 'embed-privacy' ), self::get_database_data() ) . \PHP_EOL;
		
		return $output . \PHP_EOL;
	}
	
	/**
	 * Get WordPress version.
	 * Copy of wp_get_wp_version() from WordPress 6.7.
	 * 
	 * @return	string Current WordPress version
	 */
	private static function get_version() {
		if ( \function_exists( 'wp_get_wp_version' ) ) {
			return \wp_get_wp_version();
		}
		
		static $wp_version;
		
		if ( ! isset( $wp_version ) ) {
			require \ABSPATH . \WPINC . '/version.php';
		}
		
		return $wp_version;
	}
}
