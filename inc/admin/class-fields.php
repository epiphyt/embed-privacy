<?php
namespace epiphyt\Embed_Privacy\admin;

use WP_Post;

/**
 * Admin fields functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Fields {
	/**
	 * @var		array List of fields
	 */
	public $fields = [];
	
	/**
	 * Initialize functions.
	 */
	public function init() {
		\add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		\add_action( 'do_meta_boxes', [ self::class, 'remove_default' ] );
		\add_action( 'init', [ $this, 'register_default' ] );
		\add_action( 'save_post_epi_embed', [ $this, 'save' ] );
		\add_filter( 'map_meta_cap', [ self::class, 'disallow_deleting_system_embeds' ], 10, 4 );
	}
	
	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		\add_meta_box( 'embed-privacy-custom-fields', \__( 'Embed Fields', 'embed-privacy' ), [ $this, 'get' ], 'epi_embed', 'normal', 'high' );
	}
	
	/**
	 * Disallow deletion of system embeds.
	 * 
	 * @param	array	$caps The current capabilities
	 * @param	string	$cap The capability to check
	 * @param	int		$user_id The user ID
	 * @param	array	$args Additional arguments
	 * @return	array The updated capabilities
	 */
	public static function disallow_deleting_system_embeds( array $caps, $cap, $user_id, array $args ) {
		if ( $cap !== 'delete_post' ) {
			return $caps;
		}
		
		$post_id = \reset( $args );
		
		if ( $post_id ) {
			$post = \get_post( $post_id );
			
			if (
				$post instanceof WP_Post
				&& $post->post_type === 'epi_embed'
				&& \get_post_meta( $post->ID, 'is_system', true ) === 'yes'
			) {
				$caps[] = 'do_not_allow';
				
				return $caps;
			}
		}
		
		return $caps;
	}
	
	/**
	 * Get the post meta fields HTML.
	 */
	public function get() {
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
							Field::get_image( $post->ID, $field );
							break;
						case 'input':
						default:
							Field::get( $field, $post->ID );
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
			\wp_die(
				new \WP_Error( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'invalid_fields',
					\esc_html__( 'Invalid value for additional Embed Privacy fields provided.', 'embed-privacy' )
				)
			);
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
	public function register_default() {
		$this->register( [ // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
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
			'content_item_name' => [
				'description' => \__( 'Name of a single content item of this provider.', 'embed-privacy' ),
				'field_type' => 'input',
				'name' => 'content_item_name',
				'title' => \__( 'Content Name', 'embed-privacy' ),
			],
			'regex_default' => [
				'description' => \sprintf(
					/* translators: link to documentation */
					\__( 'Regular expression that will be searched for in the content. See the %s for more information.', 'embed-privacy' ),
					'<a href="' . \esc_url(
						\sprintf(
							/* translators: plugin version */
							\__( 'https://epiph.yt/en/embed-privacy/documentation/?version=%s#regex-pattern', 'embed-privacy' ),
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
	public static function remove_default() {
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
	private static function sanitize_array( array $array ) {
		foreach ( $array as &$value ) {
			if ( \is_array( $value ) ) {
				$value = self::sanitize_array( $value );
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
	 * @since	1.12.0 Deprecated second parameter
	 * 
	 * @param	int				$post_id The ID of the post
	 * @param	\WP_Post|false	$deprecated Deprecated. The post object
	 */
	public function save( $post_id, $deprecated = false ) {
		if ( $deprecated !== false ) {
			\_doing_it_wrong(
				__METHOD__,
				\esc_html__( 'The second parameter is deprecated. Please remove it from your method call.', 'embed-privacy' ),
				'1.12.0'
			);
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
			&& \current_action() !== 'save_post_epi_embed'
		) {
			return;
		}
		
		// ignore actions to trash the post
		if (
			! empty( $_GET['action'] )
			&& \in_array( \sanitize_text_field( \wp_unslash( $_GET['action'] ) ), [ 'trash', 'untrash' ], true )
		) {
			return;
		}
		
		// ignore inline saves
		if ( ! empty( $_POST['action'] ) && \sanitize_text_field( \wp_unslash( $_POST['action'] ) ) === 'inline-save' ) {
			return;
		}
		
		// verify capability
		if (
			! \defined( 'WP_CLI' ) && ! \current_user_can( 'edit_posts', $post_id )
			|| \defined( 'WP_CLI' ) && ! \WP_CLI
		) {
			\wp_die( new \WP_Error( 403, \esc_html__( 'You are not allowed to edit an embed.', 'embed-privacy' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
				$value = self::sanitize_array( \wp_unslash( $_POST[ $field['name'] ] ) );
			}
			else if ( ! \str_contains( $field['name'], 'regex' ) ) {
				$value = \sanitize_text_field( \wp_unslash( $_POST[ $field['name'] ] ) );
			}
			else {
				$value = (string) \wp_unslash( $_POST[ $field['name'] ] );
			}
			// phpcs:enable
			
			\update_post_meta( $post_id, $field['name'], $value );
		}
		
		$files = self::validate_files();
		
		foreach ( $files as $field_name => $file ) {
			// upload file directly into library
			$attachment_id = self::upload_file( $file );
			
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
	public static function upload_file( array $file ) {
		// store file in the uploads folder
		$upload_file = \wp_upload_bits( $file['name'], null, $file['content'] );
		
		if ( isset( $upload_file['error'] ) && $upload_file['error'] ) {
			return 0;
		}
		
		// get attachment data
		$attachment = [
			'post_content' => '',
			'post_mime_type' => $upload_file['type'],
			'post_status' => 'inherit',
			'post_title' => \sanitize_title( $file['name'] ),
		];
		// save the file as attachment
		$attachment_id = \wp_insert_attachment( $attachment, $upload_file['file'] );
		
		if ( \is_wp_error( $attachment_id ) ) {
			return 0;
		}
		
		// make wp_generate_attachment_metadata() available
		// see https://wordpress.stackexchange.com/a/261262
		include_once \ABSPATH . 'wp-admin/includes/image.php';
		// generate meta data
		\wp_update_attachment_metadata( $attachment_id, \wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] ) );
		
		return $attachment_id;
	}
	
	/**
	 * Validate all files.
	 * 
	 * @return	array The updated form fields
	 */
	private static function validate_files() {
		global $wp_filesystem;
		
		// initialize the WP filesystem if not exists
		if ( empty( $wp_filesystem ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
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
			if ( ! \in_array( $key, $valid_files, true ) ) { // check valid files
				continue;
			}
			
			$validated[ $key ] = [
				'content' => $wp_filesystem->get_contents( $files['tmp_name'] ),
				'name' => $files['name'],
				'tmp_name' => $files['tmp_name'],
			];
		}
		
		// remove files once processed
		unset( $_FILES );
		
		return $validated;
	}
}
