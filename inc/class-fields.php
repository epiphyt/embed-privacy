<?php
namespace epiphyt\Embed_Privacy;

use epiphyt\Embed_Privacy\admin\Field;
use epiphyt\Embed_Privacy\admin\User_Interface;

/**
 * Custom fields for Embed Privacy.
 * 
 * @deprecated	1.10.0
 * @since		1.2.0
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Fields {
	/**
	 * @var		array Fields to output
	 */
	public $fields = [];
	
	/**
	 * @var		\epiphyt\Embed_Privacy\Fields
	 */
	private static $instance;
	
	/**
	 * Fields constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::init() instead
	 */
	public function init() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::init()'
			),
			'1.10.0'
		);
	}
	
	/**
	 * Add meta boxes.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::add_meta_boxes() instead
	 */
	public function add_meta_boxes() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::add_meta_boxes()'
			),
			'1.10.0'
		);
		Embed_Privacy::get_instance()->fields->add_meta_boxes();
	}
	
	/**
	 * Enqueue admin assets.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\User_Interface::enqueue_assets() instead
	 * 
	 * @param	string	$hook The current hook
	 */
	public function enqueue_admin_assets( $hook ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\User_Interface::enqueue_assets()'
			),
			'1.10.0'
		);
		User_Interface::enqueue_assets( $hook );
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Embed_Privacy\Fields The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Get the post meta fields HTML.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::get() instead
	 */
	public function get_the_fields_html() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::get()'
			),
			'1.10.0'
		);
		Embed_Privacy::get_instance()->fields->get();
	}
	
	/**
	 * Output an image field.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Field::get_image() instead
	 * 
	 * @param	int		$post_id The current post ID
	 * @param	array	$attributes An array with attributes
	 */
	public function get_the_image_field_html( $post_id, array $attributes ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Field::get_image()'
			),
			'1.10.0'
		);
		Field::get_image( $post_id, $attributes );
	}
	
	/**
	 * Output a single input field depending on given attributes.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Field::get() instead
	 * 
	 * @param	int		$post_id The current post ID
	 * @param	array	$attributes An array with attributes
	 */
	public function get_the_input_field_html( $post_id, array $attributes ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Field::get()'
			),
			'1.10.0'
		);
		Field::get( $attributes, $post_id );
	}
	
	/**
	 * Register fields.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::register() instead
	 * 
	 * @param	array	$fields Fields to register
	 */
	public function register( array $fields = [] ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::register()'
			),
			'1.10.0'
		);
		Embed_Privacy::get_instance()->fields->register( $fields );
	}
	
	/**
	 * Register default fields.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::register_default() instead
	 */
	public function register_default_fields() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::register_default()'
			),
			'1.10.0'
		);
		Embed_Privacy::get_instance()->fields->register_default();
	}
	
	/**
	 * Remove default meta box "Custom Fields”.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::remove_default() instead
	 */
	public function remove_default_fields() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::remove_default()'
			),
			'1.10.0'
		);
		Embed_Privacy::get_instance()->fields->remove_default();
	}
	
	/**
	 * Save the fields as post meta.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::save() instead
	 * 
	 * @param	int			$post_id The ID of the post
	 * @param	\WP_Post	$post The post object
	 */
	public function save_fields( $post_id, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::save()'
			),
			'1.10.0'
		);
		Embed_Privacy::get_instance()->fields->save( $post_id );
	}
	
	/**
	 * Upload a file as attachment.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\admin\Fields::upload_file() instead
	 * 
	 * @param	array	$file The file to upload
	 * @return	int The attachment ID
	 */
	public function upload_file( array $file ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\admin\Fields::upload_file()'
			),
			'1.10.0'
		);
		
		return Embed_Privacy::get_instance()->fields->upload_file( $file );
	}
}
