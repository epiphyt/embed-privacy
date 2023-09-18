<?php
namespace epiphyt\Embed_Privacy;

use WP_Error;

/**
 * Custom fields for Embed Privacy.
 * 
 * @since	1.2.0
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
	 * Post Type constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 */
	public function init() {
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		\add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		\add_action( 'do_meta_boxes', [ $this, 'remove_default_fields' ] );
		\add_action( 'init', [ $this, 'register_default_fields' ] );
		\add_action( 'save_post', [ $this, 'save_fields' ], 10, 2 );
	}
	
	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		\add_meta_box( 'embed-privacy-custom-fields', \__( 'Embed Fields', 'embed-privacy' ), [ $this, 'get_the_fields_html' ], 'epi_embed', 'normal', 'high' );
	}
	
	/**
	 * Enqueue admin assets.
	 * 
	 * @param	string	$hook The current hook
	 */
	public function enqueue_admin_assets( $hook ) {
		// we need it just on the post page
		if ( ( $hook !== 'post.php' && $hook !== 'post-new.php' ) || \get_current_screen()->id !== 'epi_embed' ) {
			return;
		}
		
		$suffix = ( \defined( 'WP_DEBUG' ) && WP_DEBUG ? '' : '.min' );
		$script_path = \plugin_dir_path( Embed_Privacy::get_instance()->plugin_file ) . 'assets/js/admin/image-upload' . $suffix . '.js';
		$script_url = \plugin_dir_url( Embed_Privacy::get_instance()->plugin_file ) . 'assets/js/admin/image-upload' . $suffix . '.js';
		
		\wp_enqueue_script( 'embed-privacy-admin-image-upload', $script_url, [ 'jquery' ], \filemtime( $script_path ), true );
		
		$style_path = \plugin_dir_path( Embed_Privacy::get_instance()->plugin_file ) . 'assets/style/embed-privacy-admin' . $suffix . '.css';
		$style_url = \plugin_dir_url( Embed_Privacy::get_instance()->plugin_file ) . 'assets/style/embed-privacy-admin' . $suffix . '.css';
		
		\wp_enqueue_style( 'embed-privacy-admin-style', $style_url, [], \filemtime( $style_path ) );
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
	 */
	public function get_the_fields_html() {
		global $post;
		
		foreach ( $this->fields as $field ) {
			if ( $field['field_type'] !== 'input' || empty( $field['type'] ) || $field['type'] !== 'hidden' ) {
				continue;
			}
			
			$field['value'] = (string) \get_post_meta( $post->ID, $field['name'], true );
			?>
			<input type="hidden" name="<?php echo \esc_attr( $field['name'] ); ?>" value="<?php echo \esc_attr( $field['value'] ); ?>">
			<?php
		}
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<?php
				foreach ( $this->fields as $field ) {
					// set default field type if no one is available
					if ( empty( $field['field_type'] ) ) {
						$field['field_type'] = 'input';
					}
					
					switch ( $field['field_type'] ) {
						case 'image':
							$this->get_the_image_field_html( $post->ID, $field );
							break;
						case 'input':
						default:
							$this->get_the_input_field_html( $post->ID, $field );
							break;
					}
				}
				
				/**
				 * Output additional fields.
				 * 
				 * @param	int		$post_id The current post ID
				 */
				$fields = \apply_filters( 'embed_privacy_editor_fields', $post->ID );
				
				if ( $fields !== $post->ID ) {
					echo $fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</tbody>
		</table>
		<?php
	}
	
	/**
	 * Output an image field.
	 * 
	 * @param	int		$post_id The current post ID
	 * @param	array	$attributes An array with attributes
	 */
	public function get_the_image_field_html( $post_id, array $attributes ) {
		$attributes = \wp_parse_args( $attributes, [
			'classes' => '',
			'description' => '',
			'name' => '',
			'single' => true,
			'title' => '',
		] );
		
		if ( empty( $attributes['name'] ) || empty( $attributes['title'] ) ) {
			return;
		}
		
		$attributes['value'] = (string) \get_post_meta( $post_id, $attributes['name'], $attributes['single'] );
		$image = \wp_get_attachment_image( (int) $attributes['value'] );
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo \esc_attr( $attributes['name'] ); ?>"><?php echo \esc_html( $attributes['title'] ); ?></label>
			</th>
			<td class="embed-privacy-image-item">
				<input type="hidden" name="<?php echo \esc_attr( $attributes['name'] ); ?>" value="<?php echo \esc_attr( $attributes['value'] ); ?>" class="embed-privacy-image-input">
				
				<div class="embed-privacy-image-input-container<?php echo ( ! empty( $attributes['value'] ) ? ' embed-privacy-hidden' : '' ); ?>">
					<button type="button" class="button button-secondary embed-privacy-image-upload"><?php \esc_html_e( 'Upload or choose file', 'embed-privacy' ); ?></button>
				</div>
				
				<div class="embed-privacy-image-container<?php echo ( empty( $attributes['value'] ) ? ' embed-privacy-hidden' : '' ); ?>">
					<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="dashicons dashicons-no embed-privacy-icon embed-privacy-remove-image"></span>
				</div>
				
				<?php if ( ! empty( $attributes['description'] ) ) : ?>
				<p><?php echo \esc_html( $attributes['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
	
	/**
	 * Output a single input field depending on given attributes.
	 * 
	 * @param	int		$post_id The current post ID
	 * @param	array	$attributes An array with attribues
	 */
	public function get_the_input_field_html( $post_id, array $attributes ) {
		$attributes = \wp_parse_args( $attributes, [
			'classes' => 'regular-text',
			'description' => '',
			'name' => '',
			'option_type' => 'meta',
			'single' => true,
			'title' => '',
			'type' => 'text',
			'validation' => '',
		] );
		
		if ( empty( $attributes['name'] ) || empty( $attributes['title'] ) ) {
			return;
		}
		
		if ( $attributes['option_type'] === 'meta' ) {
			$current_value = (string) \get_post_meta( $post_id, $attributes['name'], $attributes['single'] );
		}
		else {
			$current_value = (string) \get_option( $attributes['name'] );
		}
		
		if ( ! \in_array( $attributes['type'], [ 'checkbox', 'hidden', 'radio' ], true ) ) :
		\ob_start();
		?>
		<input type="<?php echo \esc_attr( $attributes['type'] ); ?>" name="<?php echo \esc_attr( $attributes['name'] ); ?>" id="<?php echo \esc_attr( $attributes['name'] ); ?>" value="<?php echo esc_attr( $current_value ); ?>" class="<?php echo esc_attr( $attributes['classes'] ); ?>">
		<?php if ( ! empty( $attributes['description'] ) ) : ?>
		<p>
			<?php
			if ( empty( $attributes['validation'] ) ) {
				echo \esc_html( $attributes['description'] );
			}
			else if ( $attributes['validation'] === 'allow-links' ) {
				echo \wp_kses( $attributes['description'], [
					'a' => [
						'href' => true,
						'rel' => true,
						'target' => true,
					],
				] );
			}
			?>
		</p>
		<?php
		endif;
		$input = \ob_get_clean();
		
		if ( $attributes['option_type'] === 'option' ) {
			echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			
			return;
		}
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo \esc_attr( $attributes['name'] ); ?>"><?php echo \esc_html( $attributes['title'] ); ?></label>
			</th>
			<td>
				<?php echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php
		else :
		// set default value for checkboxes and radio buttons
		if ( empty( $attributes['value'] ) ) {
			$attributes['value'] = 'yes';
		}
		
		\ob_start();
		?>
		<label for="<?php echo \esc_attr( $attributes['name'] ); ?>"><input type="<?php echo \esc_attr( $attributes['type'] ); ?>" name="<?php echo \esc_attr( $attributes['name'] ); ?>" id="<?php echo \esc_attr( $attributes['name'] ); ?>" value="<?php echo \esc_attr( $attributes['value'] ); ?>" class="<?php echo \esc_attr( $attributes['classes'] ); ?>"<?php \checked( $current_value, $attributes['value'] ); ?>> <?php echo \esc_html( $attributes['title'] ); ?></label>
		<?php if ( ! empty( $attributes['description'] ) ) : ?>
		<p>
			<?php
			if ( empty( $attributes['validation'] ) ) {
				echo \esc_html( $attributes['description'] );
			}
			else if ( $attributes['validation'] === 'allow-links' ) {
				echo \wp_kses( $attributes['description'], [
					'a' => [
						'href' => true,
						'rel' => true,
						'target' => true,
					],
				] );
			}
			?>
		</p>
		<?php
		endif;
		$input = \ob_get_clean();
		
		if ( $attributes['option_type'] === 'option' ) {
			echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			
			return;
		}
		?>
		<tr>
			<th scope="row"></th>
			<td>
				<?php echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php
		endif;
	}
	
	/**
	 * Register fields.
	 * 
	 * @param	array	$fields Fields to register
	 */
	public function register( array $fields = [] ) {
		/**
		 * Register additional fields.
		 * Use \epiphyt\Embed_Privacy\Fields::get_instance()->register( $fields )
		 * if possible (be careful, as this needs a call after textdomain has been loaded).
		 * 
		 * @param	array	$fields Additional fields
		 */
		$additional_fields = \apply_filters( 'embed_privacy_register_fields', [] );
		
		if ( ! \is_array( $additional_fields ) ) {
			\wp_die( new WP_Error( 'invalid_fields', \esc_html__( 'Invalid value for additional Embed Privacy fields provided.', 'embed-privacy' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		
		// merge fields
		$this->fields = \array_merge( $this->fields, $fields, $additional_fields );
		
		/**
		 * Filter all registered fields.
		 * 
		 * @param	array	$fields Registered fields
		 */
		$this->fields = \apply_filters( 'embed_privacy_fields', $this->fields );
	}
	
	/**
	 * Register default fields.
	 */
	public function register_default_fields() {
		$this->register( [
			'privacy_policy_url' => [
				'description' => \__( 'Link to the embed provider’s privacy policy URL.', 'embed-privacy' ),
				'field_type' => 'input',
				'name' => 'privacy_policy_url',
				'title' => \__( 'Privacy Policy URL', 'embed-privacy' ),
				'type' => 'url',
			],
			'background_image' => [
				'field_type' => 'image',
				'name' => 'background_image',
				'title' => \__( 'Background Image', 'embed-privacy' ),
			],
			'regex_default' => [
				'description' => \sprintf(
					/* translators: link to documentation */
					__( 'Regular expression that will be searched for in the content. See the %s for more information.', 'embed-privacy' ),
					'<a href="' . \esc_url(
						\sprintf(
							/* translators: plugin version */
							__( 'https://epiph.yt/en/embed-privacy/documentation/?version=%s#regex-pattern', 'embed-privacy' ),
							\EMBED_PRIVACY_VERSION
						)
					) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'documentation', 'embed-privacy' ) . '</a>'
				),
				'field_type' => 'input',
				'name' => 'regex_default',
				'title' => \__( 'Regex Pattern', 'embed-privacy' ),
				'validation' => 'allow-links',
			],
			'is_disabled' => [
				'field_type' => 'input',
				'name' => 'is_disabled',
				'title' => \__( 'Disable embed provider', 'embed-privacy' ),
				'type' => 'checkbox',
			],
			'is_system' => [
				'field_type' => 'input',
				'name' => 'is_system',
				'title' => '',
				'type' => 'hidden',
			],
		] );
	}
	
	/**
	 * Remove default meta box "Custom Fields”.
	 */
	public function remove_default_fields() {
		foreach ( [ 'normal', 'advanced', 'side' ] as $context ) {
			\remove_meta_box( 'postcustom', 'epi_embed', $context );
		}
	}
	
	/**
	 * Sanitize an array recursively.
	 * 
	 * @param	array	$array The array to sanitize
	 * @return	array The sanitized array
	 */
	private function sanitize_array( array $array ) {
		foreach ( $array as &$value ) {
			if ( \is_array( $value ) ) {
				$value = $this->sanitize_array( $value );
			}
			else {
				$value = \trim( \sanitize_text_field( \wp_unslash( $value ) ) );
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
		
		if (
			(
				// plugin update
				(
					! isset( $_GET['activate'] )
					|| $_GET['activate'] !== 'true'
				)
				// manual post update
				|| (
					! \get_current_screen()
					|| empty( \get_current_screen()->action )
					|| (
						\get_current_screen()->action !== 'add'
						&& ! \check_admin_referer( 'update-post_' . $post_id )
					)
				)
			)
			&& \current_action() !== 'save_post'
		) {
			return;
		}
		
		// ignore actions to trash the post
		if ( ! empty( $_GET['action'] ) && \in_array( \sanitize_text_field( \wp_unslash( $_GET['action'] ) ), [ 'trash', 'untrash' ], true ) ) {
			return;
		}
		
		// verify capability
		if (
			! \defined( 'WP_CLI' ) && ! \current_user_can( 'edit_posts', $post_id )
			|| \defined( 'WP_CLI' ) && ! \WP_CLI
		) {
			\wp_die( new WP_Error( 403, \esc_html__( 'You are not allowed to edit an embed.', 'embed-privacy' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		
		if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
			return;
		}
		
		foreach ( $this->fields as $field ) {
			if ( empty( $_POST[ $field['name'] ] ) ) {
				\delete_post_meta( $post_id, $field['name'] );
				
				continue;
			}
			
			// sanitizing
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( \is_array( $_POST[ $field['name'] ] ) ) {
				$value = $this->sanitize_array( \wp_unslash( $_POST[ $field['name'] ] ) );
			}
			else if ( \strpos( $field['name'], 'regex' ) === false ) {
				$value = \sanitize_text_field( \wp_unslash( $_POST[ $field['name'] ] ) );
			}
			else {
				$value = (string) \wp_unslash( $_POST[ $field['name'] ] );
			}
			// phpcs:enable
			
			\update_post_meta( $post_id, $field['name'], $value );
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
				
				\update_post_meta( $post_id, $field_name, $attachment_ids );
			}
		}
	}
	
	/**
	 * Upload a file as attachment.
	 * 
	 * @param	array	$file The file to upload
	 * @return	int The attachment ID
	 */
	public function upload_file( array $file ) {
		// store file in the uploads folder
		$upload_file = \wp_upload_bits( $file['name'], null, $file['content'] );
		
		if ( isset( $upload_file['error'] ) && $upload_file['error'] ) {
			return 0;
		}
		
		// get attachment data
		$attachment = [
			'post_mime_type' => $upload_file['type'],
			'post_title' => \sanitize_title( $file['name'] ),
			'post_content' => '',
			'post_status' => 'inherit',
		];
		// save the file as attachment
		$attachment_id = \wp_insert_attachment( $attachment, $upload_file['file'] );
		
		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}
		
		// make wp_generate_attachment_metadata() available
		// see https://wordpress.stackexchange.com/a/261262
		include_once ABSPATH . 'wp-admin/includes/image.php';
		// generate meta data
		\wp_update_attachment_metadata( $attachment_id, \wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] ) );
		
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
			require_once ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}
		
		/**
		 * Set the option names to look for files.
		 * 
		 * @param	array	The default name list
		 */
		$valid_files = \apply_filters( 'embed_privacy_valid_files', [ 'background_image' ] );
		$validated = [];
		
		if ( empty( $_FILES ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $validated;
		}
		
		foreach ( $_FILES as $key => $files ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// check valid files
			if ( ! \in_array( $key, $valid_files, true ) ) {
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
