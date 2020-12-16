<?php
namespace epiphyt\Embed_Privacy;
use WP_Error;
use WP_Post;
use function add_action;
use function current_user_can;
use function esc_html__;
use function remove_meta_box;
use function wp_die;

/**
 * Custom fields for Embed Privacy.
 *
 * @author	Matthias Kittsteiner
 * @license	GPL2 <https://www.gnu.org/licenses/gpl-2.0.html>
 */
class Fields {
	/**
	 * @var		\epiphyt\Embed_Privacy\Fields
	 */
	private static $instance;
	
	/**
	 * Post Type constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 */
	public function init() {
		add_action( 'do_meta_boxes', [ $this, 'remove_default_fields' ] );
		add_action( 'save_post', [ $this, 'save_fields' ], 10, 2 );
	}
	
	/**
	 * Get a unique instance of the class.
	 *
	 * @return	\epiphyt\Embed_Privacy\Fields
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Remove default meta box "Custom Fields”.
	 */
	public function remove_default_fields() {
		foreach ( [ 'normal', 'advanced', 'side' ] as $context ) {
			remove_meta_box( 'postcustom', 'epi_embed', $context );
		}
	}
	
	/**
	 * Save the fields as post meta.
	 *
	 * @param	int			$post_id The ID of the post
	 * @param	\WP_Post	$post The post object
	 */
	public function save_fields( $post_id, WP_Post $post ) {
		// ignore other post types
		if ( $post->post_type !== 'epi_embed' ) {
			return;
		}
		
		// verify capability
		if ( ! current_user_can( 'edit_posts', $post_id ) ) {
			wp_die( new WP_Error( 403, esc_html__( 'You are not allowed to edit an embed.', 'embed-privacy' ) ) );
		}
		
		// TODO: Process fields
	}
}
