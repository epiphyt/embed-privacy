<?php
namespace epiphyt\Embed_Privacy;
use WP_Error;
use function __;
use function add_action;
use function add_meta_box;
use function addslashes;
use function apply_filters;
use function current_user_can;
use function defined;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function filemtime;
use function get_current_screen;
use function get_post_meta;
use function in_array;
use function is_array;
use function is_wp_error;
use function plugin_dir_path;
use function plugin_dir_url;
use function preg_match;
use function remove_meta_box;
use function sanitize_text_field;
use function sanitize_title;
use function trim;
use function update_post_meta;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function WP_Filesystem;
use function wp_generate_attachment_metadata;
use function wp_get_attachment_image;
use function wp_insert_attachment;
use function wp_parse_args;
use function wp_unslash;
use function wp_update_attachment_metadata;
use function wp_upload_bits;

/**
 * Custom fields for Embed Privacy.
 * 
 * @since	1.2.0
 *
 * @author		Epiphyt
 * @license		GPL2
 * @package		epiphyt\Embed_Privacy
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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
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
	 * Enqueue admin assets.
	 *
	 * @param	string		$hook The current hook
	 */
	public function enqueue_admin_assets( $hook ) {
		// we need it just on the post page
		if ( ( $hook !== 'post.php' && $hook !== 'post-new.php' ) || get_current_screen()->id !== 'epi_embed' ) {
			return;
		}
		
		$suffix = ( defined( 'WP_DEBUG' ) && WP_DEBUG ? '' : '.min' );
		$script_path = plugin_dir_path( Embed_Privacy::get_instance()->plugin_file ) . 'assets/js/admin/image-upload' . $suffix . '.js';
		$script_url = plugin_dir_url( Embed_Privacy::get_instance()->plugin_file ) . 'assets/js/admin/image-upload' . $suffix . '.js';
		
		wp_enqueue_script( 'embed-privacy-admin-image-upload', $script_url, [ 'jquery' ], filemtime( $script_path ), true );
		
		$style_path = plugin_dir_path( Embed_Privacy::get_instance()->plugin_file ) . 'assets/style/embed-privacy-admin' . $suffix . '.css';
		$style_url = plugin_dir_url( Embed_Privacy::get_instance()->plugin_file ) . 'assets/style/embed-privacy-admin' . $suffix . '.css';
		
		wp_enqueue_style( 'embed-privacy-admin-style', $style_url, [], filemtime( $style_path ) );
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
				$this->get_the_background_image_field( $post->ID );
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
	private function get_the_background_image_field( $post_id ) {
		$this->get_the_image_field( $post_id, [
			'name' => 'background_image',
			'title' => __( 'Background Image', 'embed-privacy' ),
		] );
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
	
	/**
	 * Output an image field.
	 * 
	 * @param	int		$post_id The current post ID
	 * @param	array	$attributes An array with attributes
	 */
	public function get_the_image_field( $post_id, array $attributes ) {
		$attributes = wp_parse_args( $attributes, [
			'classes' => '',
			'description' => '',
			'name' => '',
			'single' => true,
			'title' => '',
		] );
		
		if ( empty( $attributes['name'] ) || empty( $attributes['title'] ) ) {
			return;
		}
		
		$attributes['name'] = 'embed_privacy_' . $attributes['name'];
		$attributes['value'] = (string) get_post_meta( $post_id, $attributes['name'], $attributes['single'] );
		$attributes['value'] = wp_get_attachment_image( (int) $attributes['value'] );
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $attributes['name'] ); ?>"><?php echo esc_html( $attributes['title'] ); ?></label>
			</th>
			<td class="embed-privacy-image-item">
				<input type="hidden" name="<?php echo esc_attr( $attributes['name'] ); ?>" value="<?php echo esc_attr( $attributes['value'] ); ?>" class="embed-privacy-image-input">
				
				<div class="embed-privacy-image-input-container<?php echo ( ! empty( $attributes['value'] ) ? ' embed-privacy-hidden' : '' ); ?>">
					<button type="button" class="button button-secondary embed-privacy-image-upload"><?php esc_html_e( 'Upload or choose file', 'embed-privacy' ); ?></button>
				</div>
				
				<div class="embed-privacy-image-container<?php echo ( empty( $attributes['value'] ) ? ' embed-privacy-hidden' : '' ); ?>">
					<?php echo $attributes['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="dashicons dashicons-no embed-privacy-icon embed-privacy-remove-image"></span>
				</div>
				
				<?php if ( ! empty( $attributes['description'] ) ) : ?>
				<p><?php echo esc_html( $attributes['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
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
				$value = sanitize_text_field( wp_unslash( $field ) );
				// add slashes, so that \/ becomes \\/
				// otherwise \/ becomes / while storing into the database
				$value = addslashes( $value );
			}
			
			update_post_meta( $post_id, $key, $value );
		}
		
		$files = $this->validate_files();
		
		foreach ( $files as $field_name => $file ) {
			// upload file directly into library
			$attachment_id = $this->upload_file( $file );
			
			if ( $attachment_id ) {
				$attachment_id_list[ $field_name ] = $attachment_id;
			}
		}
		
		// store or remove attachment IDs in the database
		if ( ! empty( $attachment_id_list ) ) {
			foreach ( $attachment_id_list as $field_name => $attachment_ids ) {
				// add uploaded files to POST data to prevent deleting data on
				// second execution of save_post
				$_POST[ $field_name ] = $attachment_ids;
				
				update_post_meta( $post_id, $field_name, $attachment_ids );
			}
		}
	}
	
	/**
	 * Upload a file as attachment.
	 * 
	 * @param	array		$file The file to upload
	 * @return	int The attachment ID
	 */
	private function upload_file( array $file ) {
		// store file in the uploads folder
		$upload_file = wp_upload_bits( $file['name'], null, $file['content'] );
		
		if ( isset( $upload_file['error'] ) && $upload_file['error'] ) {
			return 0;
		}
		
		// get attachment data
		$attachment = [
			'post_mime_type' => $upload_file['type'],
			'post_title' => sanitize_title( $file['name'] ),
			'post_content' => '',
			'post_status' => 'inherit',
		];
		// save the file as attachment
		$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'] );
		
		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}
		
		// make wp_generate_attachment_metadata() available
		// see https://wordpress.stackexchange.com/a/261262
		include_once( ABSPATH . 'wp-admin/includes/image.php' );
		// generate meta data
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] ) );
		
		return $attachment_id;
	}
	
	/**
	 * Validate all files.
	 * 
	 * @return	array The updated form fields
	 */
	private function validate_files() {
		global $wp_filesystem;
		
		// initialize the WP filesystem if not exists
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			WP_Filesystem();
		}
		
		/**
		 * Set the option names to look for files.
		 * 
		 * @param	array	The default name list
		 */
		$valid_files = apply_filters( 'embed_privacy_valid_files', [ 'embed_privacy_background_image' ] );
		$validated = [];
		
		if ( empty( $_FILES ) ) return $validated;
		
		foreach ( $_FILES as $key => $files ) {
			// check valid files
			if ( ! in_array( $key, $valid_files, true ) ) {
				continue;
			}
			
			$validated[ $key ] = [
				'name' => $files['name'],
				'content' => $wp_filesystem->get_contents( $files['tmp_name'] ),
				'tmp_name' => $files['tmp_name'],
			];
		}
		
		// remove files once processed
		unset( $_FILES );
		
		return $validated;
	}
}
