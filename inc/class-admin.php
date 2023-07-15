<?php
namespace epiphyt\Embed_Privacy;

use WP_Post;

/**
 * Admin related methods for Embed Privacy.
 * 
 * @since	1.2.0
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Admin {
	/**
	 * @var		array Admin to output
	 */
	public $fields = [];
	
	/**
	 * @var		\epiphyt\Embed_Privacy\Admin
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
		\add_action( 'admin_init', [ $this, 'init_settings' ] );
		\add_action( 'admin_menu', [ $this, 'register_menu' ] );
		\add_filter( 'map_meta_cap', [ $this, 'disallow_deleting_system_embeds' ], 10, 4 );
		\add_filter( 'plugin_row_meta', [ $this, 'add_meta_link' ], 10, 2 );
	}
	
	/**
	 * Add plugin meta links.
	 * 
	 * @since	1.6.0
	 * 
	 * @param	array	$input Registered links.
	 * @param	string	$file  Current plugin file.
	 * @return	array Merged links
	 */
	public function add_meta_link( $input, $file ) {
		// bail on other plugins
		if ( $file !== \plugin_basename( Embed_Privacy::get_instance()->plugin_file ) ) {
			return $input;
		}
		
		return \array_merge(
			$input,
			[
				'<a href="https://epiph.yt/en/embed-privacy/documentation/?version=' . \rawurlencode( \EMBED_PRIVACY_VERSION ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'Documentation', 'embed-privacy' ) . '</a>',
			]
		);
	}
	
	/**
	 * Disallow deletion of system embeds.
	 * 
	 * @since	1.4.0
	 * 
	 * @param	array	$caps The current capabilities
	 * @param	string	$cap The capability to check
	 * @param	int		$user_id The user ID
	 * @param	array	$args Additional arguments
	 * @return	array The updated capabilities
	 * @noinspection PhpUnusedParameterInspection
	 */
	function disallow_deleting_system_embeds( array $caps, $cap, $user_id, array $args ) {
		if ( $cap !== 'delete_post' ) {
			return $caps;
		}
		
		$post_id = \reset( $args );
		
		if ( $post_id ) {
			$post = \get_post( $post_id );
			
			if ( $post instanceof WP_Post && $post->post_type === 'epi_embed' && \get_post_meta( $post->ID, 'is_system', true ) === 'yes' ) {
				$caps[] = 'do_not_allow';
				
				return $caps;
			}
		}
		
		return $caps;
	}
	
	/**
	 * Wrapping function to get a field HTML.
	 * 
	 * @param	array	$attributes Field attributes
	 */
	public function get_field( array $attributes ) {
		Fields::get_instance()->get_the_input_field_html( 0, $attributes );
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
	 */
	public function init_settings() {
		\add_settings_section(
			'embed_privacy_general',
			null,
			'__return_null',
			'embed_privacy'
		);
		\add_settings_field(
			'embed_privacy_javascript_detection',
			__( 'JavaScript detection', 'embed-privacy' ),
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => \__( 'By enabling this option, checks for embed providers are made via JavaScript on the client-side rather than on your server. Enabling this option is recommended when using a caching plugin.', 'embed-privacy' ),
				'name' => 'embed_privacy_javascript_detection',
				'option_type' => 'option',
				'title' => \__( 'JavaScript detection for active providers', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_javascript_detection' );
		\add_settings_field(
			'embed_privacy_local_tweets',
			__( 'Embeds', 'embed-privacy' ),
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => \__( 'By enabling this option, tweets are embedded locally as text without any connection to Twitter, and no privacy overlay is required.', 'embed-privacy' ),
				'name' => 'embed_privacy_local_tweets',
				'option_type' => 'option',
				'title' => \__( 'Local tweets', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_local_tweets' );
		\add_settings_field(
			'embed_privacy_disable_link',
			null,
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => \__( 'Disable the direct link on the lower right corner that opens the embed directly.', 'embed-privacy' ),
				'name' => 'embed_privacy_disable_link',
				'option_type' => 'option',
				'title' => \__( 'Disable direct link', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_disable_link' );
		\add_settings_field(
			'embed_privacy_download_thumbnails',
			null,
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				/* translators: list of supported embed providers */
				'description' => \wp_sprintf( \__( 'Try to automatically download thumbnails of the embedded content and use them as background image of the overlay. Currently supported: %l.', 'embed-privacy' ), Thumbnails::get_instance()->get_supported_providers() ),
				'name' => 'embed_privacy_download_thumbnails',
				'option_type' => 'option',
				'title' => \__( 'Download thumbnails', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_download_thumbnails' );
		\add_settings_field(
			'embed_privacy_preserve_data_on_uninstall',
			__( 'Data handling', 'embed-privacy' ),
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => \__( 'By enabling this option, all plugin data is preserved on uninstall.', 'embed-privacy' ),
				'name' => 'embed_privacy_preserve_data_on_uninstall',
				'option_type' => 'option',
				'title' => \__( 'Preserve data on uninstall', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_preserve_data_on_uninstall' );
	}
	
	/**
	 * Output the options HTML.
	 */
	public function options_html() {
		// check user capabilities
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// show error/update messages
		\settings_errors( 'embed_privacy_messages' );
		?>
		<div class="wrap">
			<h1><?php \esc_html_e( 'Embed Privacy', 'embed-privacy' ); ?> <a href="<?php echo \esc_url( \admin_url( 'edit.php?post_type=epi_embed' ) ); ?>" class="page-title-action"><?php \esc_html_e( 'Manage embeds', 'embed-privacy' ); ?></a></h1>
			<form action="options.php" method="post">
				<?php
				\settings_fields( 'embed_privacy' );
				\do_settings_sections( 'embed_privacy' );
				\submit_button( esc_html__( 'Save Settings', 'embed-privacy' ) );
				?>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Register menu entries.
	 */
	public function register_menu() {
		\add_submenu_page(
			'options-general.php',
			\__( 'Embed Privacy', 'embed-privacy' ),
			\__( 'Embed Privacy', 'embed-privacy' ),
			'manage_options',
			'embed_privacy',
			[ $this, 'options_html' ]
		);
	}
}
