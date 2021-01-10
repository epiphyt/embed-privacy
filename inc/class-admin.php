<?php
namespace epiphyt\Embed_Privacy;
use function __;
use function add_filter;
use function add_settings_field;
use function add_settings_section;
use function add_submenu_page;
use function current_user_can;
use function do_settings_sections;
use function esc_html__;
use function register_setting;
use function settings_errors;use function settings_fields;
use function submit_button;

/**
 * Admin related methods for Embed Privacy.
 * 
 * @since	1.2.0
 *
 * @author		Epiphyt
 * @license		GPL2
 * @package		epiphyt\Embed_Privacy
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
		add_action( 'admin_init', [ $this, 'init_settings' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		
		add_filter( 'allowed_options', [ $this, 'allow_options' ] );
	}
	
	/**
	 * Register our options to save.
	 * 
	 * @param	array	$allowed_options Current allowed options
	 * @return	array Updated allowed options
	 */
	public function allow_options( array $allowed_options ) {
		$allowed_options['embed_privacy'] = [
			'embed_privacy_javascript_detection',
		];
		
		return $allowed_options;
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
	 * @return	\epiphyt\Embed_Privacy\Admin
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
		register_setting( 'embed_privacy', 'embed_privacy_options' );
		add_settings_section(
			'embed_privacy_general',
			__( 'Embed Privacy', 'embed-privacy' ),
			null,
			'embed_privacy'
		);
		add_settings_field(
			'embed_privacy_javascript_detection',
			__( 'JavaScript detection', 'embed-privacy' ),
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => __( 'On activating the JavaScript detection, the check for a provider that is always active is done only via JavaScript and not serverside. By using a caching plugin, enabling this option is recommended.', 'embed-privacy' ),
				'name' => 'embed_privacy_javascript_detection',
				'option_type' => 'option',
				'title' => __( 'JavaScript detection for active providers', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
	}
	
	/**
	 * Output the options HTML.
	 */
	public function options_html() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// show error/update messages
		settings_errors( 'embed_privacy_messages' );
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'embed_privacy' );
			do_settings_sections( 'embed_privacy' );
			submit_button( esc_html__( 'Save Settings', 'embed-privacy' ) );
			?>
		</form>
		<?php
	}
	
	/**
	 * Register menu entries.
	 */
	public function register_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Embed Privacy', 'embed-privacy' ),
			__( 'Embed Privacy', 'embed-privacy' ),
			'manage_options',
			'embed_privacy',
			[ $this, 'options_html' ]
		);
	}
}
