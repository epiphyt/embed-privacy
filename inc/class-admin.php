<?php
namespace epiphyt\Embed_Privacy;

use epiphyt\Embed_Privacy\admin\Field;
use epiphyt\Embed_Privacy\admin\Fields;
use epiphyt\Embed_Privacy\admin\Settings;
use epiphyt\Embed_Privacy\admin\User_Interface;

/**
 * Admin related methods for Embed Privacy.
 * 
 * @deprecated	1.10.0
 * @since		1.2.0
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Admin {
	/**
	 * @deprecated	1.10.0
	 * @var			array Admin to output
	 */
	public $fields = [];
	
	/**
	 * @var		\epiphyt\Embed_Privacy\Admin
	 */
	private static $instance;
	
	/**
	 * Admin constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 * 
	 * @deprecated	1.10.0
	 */
	public function init() {
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method is outdated and will be removed in the future.', 'embed-privacy' ),
			'1.10.0'
		);
	}
	
	/**
	 * Add plugin meta links.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\admin\User_Interface::add_meta_link() instead
	 * @since		1.6.0
	 * 
	 * @param	array	$input Registered links.
	 * @param	string	$file  Current plugin file.
	 * @return	array Merged links
	 */
	public function add_meta_link( $input, $file ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\admin\User_Interface::add_meta_link()'
			),
			'1.10.0'
		);
		
		return User_Interface::add_meta_link( $input, $file );
	}
	
	/**
	 * Disallow deletion of system embeds.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::disallow_deleting_system_embeds() instead
	 * @since		1.4.0
	 * 
	 * @param	array	$caps The current capabilities
	 * @param	string	$cap The capability to check
	 * @param	int		$user_id The user ID
	 * @param	array	$args Additional arguments
	 * @return	array The updated capabilities
	 */
	function disallow_deleting_system_embeds( array $caps, $cap, $user_id, array $args ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::disallow_deleting_system_embeds()'
			),
			'1.10.0'
		);
		
		return Fields::disallow_deleting_system_embeds( $caps, $cap, $user_id, $args );
	}
	
	/**
	 * Wrapping function to get a field HTML.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Field::get() instead
	 * 
	 * @param	array	$attributes Field attributes
	 */
	public function get_field( array $attributes ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Field::get()'
			),
			'1.10.0'
		);
		Field::get( $attributes );
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Embed_Privacy\Admin The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Initialize the settings page.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Settings::register() instead
	 */
	public function init_settings() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Settings::register()'
			),
			'1.10.0'
		);
		Settings::register();
	}
	
	/**
	 * Output the options HTML.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Settings::get_page() instead
	 */
	public function options_html() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Settings::get_page()'
			),
			'1.10.0'
		);
		Settings::get_page();
	}
	
	/**
	 * Register menu entries.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Settings::register_menu() instead
	 */
	public function register_menu() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Settings::register_menu()'
			),
			'1.10.0'
		);
		Settings::register_menu();
	}
}
