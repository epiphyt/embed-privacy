<?php
namespace epiphyt\Embed_Privacy;
use WP_Error;
use function __;
use function add_action;
use function add_meta_box;
use function apply_filters;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_post_meta;
use function is_array;
use function preg_match;
use function remove_meta_box;
use function sanitize_text_field;
use function trim;
use function update_post_meta;
use function wp_die;
use function wp_parse_args;
use function wp_unslash;

/**
 * Custom fields for Embed Privacy.
 * 
 * @since	1.2.0
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
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'do_meta_boxes', [ $this, 'remove_default_fields' ] );
		add_action( 'save_post', [ $this, 'save_fields' ], 10, 2 );
	}
	
	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box( 'embed-privacy-custom-fields', __( 'Embed Fields', 'embed-privacy' ), [ $this, 'get_the_fields_html' ], 'epi_embed', 'normal', 'high' );
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
	 * Get the post meta fields HTML.
	 */
	public function get_the_fields_html() {
		global $post;
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<?php
				$this->get_the_privacy_policy_url_field( $post->ID );
				$this->get_the_default_regex_field( $post->ID );
				$this->get_the_gutenberg_regex_field( $post->ID );
				
				/**
				 * Output additional fields.
				 * 
				 * @param	int		$post_id The current post ID
				 */
				$fields = apply_filters( 'embed_privacy_editor_fields', $post->ID );
				
				if ( $fields !== $post->ID ) {
					echo $fields;
				}
				?>
			</tbody>
		</table>
		<?php
	}
	
	/**
	 * Output the default Regex field.
	 * 
	 * @param	int		$post_id The current post ID
	 */
	private function get_the_default_regex_field( $post_id ) {
		$this->get_the_single_field_html( $post_id, [
			'description' => __( 'Regular expression that will be be searched for in the content.', 'embed-privacy' ),
			'name' => 'regex_default',
			'title' => __( 'Default Regex', 'embed-privacy' ),
		] );
	}
	
	public function get_the_image_field( $post_id, array $attributes ) {
		
	}
	
	/**
	 * Output the Gutenberg Regex field.
	 * 
	 * @param	int		$post_id The current post ID
	 */
	private function get_the_gutenberg_regex_field( $post_id ) {
		$this->get_the_single_field_html( $post_id, [
			'description' => __( 'Regular expression that will be be searched for in the content of Block Editor posts.', 'embed-privacy' ),
			'name' => 'regex_gutenberg',
			'title' => __( 'Block Editor Regex', 'embed-privacy' ),
		] );
	}
	
	/**
	 * Output the privacy policy URL field.
	 * 
	 * @param	int		$post_id The current post ID
	 */
	private function get_the_privacy_policy_url_field( $post_id ) {
		$this->get_the_single_field_html( $post_id, [
			'description' => __( 'Link to the privacy policy URL.', 'embed-privacy' ),
			'name' => 'privacy_policy_url',
			'title' => __( 'Privacy Policy URL', 'embed-privacy' ),
			'type' => 'url',
		] );
	}
	
	/**
	 * Output a single input field depending on given attributes.
	 * 
	 * @param	int		$post_id The current post ID
	 * @param	array	$attributes An array with attribues
	 */
	public function get_the_single_field_html( $post_id, array $attributes ) {
		$attributes = wp_parse_args( $attributes, [
			'classes' => 'regular-text',
			'description' => '',
			'name' => '',
			'single' => true,
			'title' => '',
			'type' => 'text',
		] );
		
		if ( empty( $attributes['name'] ) || empty( $attributes['title'] ) ) {
			return;
		}
		
		$attributes['name'] = 'embed_privacy_' . $attributes['name'];
		$attributes['value'] = (string) get_post_meta( $post_id, $attributes['name'], $attributes['single'] );
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $attributes['name'] ); ?>"><?php echo esc_html( $attributes['title'] ); ?></label>
			</th>
			<td>
				<input type="<?php echo esc_attr( $attributes['type'] ); ?>" name="<?php echo esc_attr( $attributes['name'] ); ?>" id="<?php echo esc_attr( $attributes['name'] ); ?>" value="<?php echo esc_attr( $attributes['value'] ); ?>" class="<?php echo esc_attr( $attributes['classes'] ); ?>">
				<?php if ( ! empty( $attributes['description'] ) ) : ?>
				<p><?php echo esc_html( $attributes['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
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
	 * Sanitize an array recursively.
	 * 
	 * @param	array	$array The array to sanitize
	 * @return	array The sanitized array
	 */
	private function sanitize_array( array $array ) {
		foreach ( $array as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = $this->sanitize_array( $value );
			}
			else {
				$value = trim( sanitize_text_field( wp_unslash( $value ) ) );
			}
		}
		
		return $array;
	}
	
	/**
	 * Save the fields as post meta.
	 *
	 * @param	int			$post_id The ID of the post
	 * @param	\WP_Post	$post The post object
	 */
	public function save_fields( $post_id, $post ) {
		// ignore other post types
		if ( $post->post_type !== 'epi_embed' ) {
			return;
		}
		
		// verify capability
		if ( ! current_user_can( 'edit_posts', $post_id ) ) {
			wp_die( new WP_Error( 403, esc_html__( 'You are not allowed to edit an embed.', 'embed-privacy' ) ) );
		}
		
		foreach ( $_POST as $key => $field ) {
			// ignore POST fields that don't belong to Embed Privacy
			if ( ! preg_match( '/^embed_privacy_/', $key ) ) {
				continue;
			}
			
			// sanitizing
			if ( is_array( $field ) ) {
				$value = $this->sanitize_array( $field );
			}
			else {
				$value = trim( sanitize_text_field( wp_unslash( $field ) ) );
			}
			
			update_post_meta( $post_id, $key, $value );
		}
	}
}
