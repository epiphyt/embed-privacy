<?php
namespace epiphyt\Embed_Privacy\admin;

use epiphyt\Embed_Privacy\Embed_Privacy;

/**
 * Admin settings.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Settings {
	const CAPABILITY = 'manage_options';
	
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_action( 'admin_init', [ self::class, 'register' ] );
		\add_action( 'admin_menu', [ self::class, 'register_menu' ] );
	}
	
	/**
	 * Get settings page.
	 */
	public static function get_page() {
		if ( ! \current_user_can( self::CAPABILITY ) ) {
			return;
		}
		
		\settings_errors( 'embed_privacy_messages' );
		?>
		<div class="wrap">
			<h1><?php \esc_html_e( 'Embed Privacy', 'embed-privacy' ); ?> <a href="<?php echo \esc_url( \admin_url( 'edit.php?post_type=epi_embed' ) ); ?>" class="page-title-action"><?php \esc_html_e( 'Manage embeds', 'embed-privacy' ); ?></a></h1>
			
			<form action="options.php" method="post">
				<?php
				\settings_fields( 'embed_privacy' );
				\do_settings_sections( 'embed_privacy' );
				\submit_button( \esc_html__( 'Save Settings', 'embed-privacy' ) );
				?>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Register settings.
	 */
	public static function register() {
		\add_settings_section( 'embed_privacy_general', null, '__return_null', 'embed_privacy' );
		\add_settings_field(
			'embed_privacy_local_tweets',
			\__( 'Embeds', 'embed-privacy' ),
			[ Field::class, 'get' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => \__( 'By enabling this option, tweets are embedded locally as text without any connection to X, and no privacy overlay is required.', 'embed-privacy' ),
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
			[ Field::class, 'get' ],
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
			[ Field::class, 'get' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				/* translators: list of supported embed providers */
				'description' => \wp_sprintf(
					\__( 'Try to automatically download thumbnails of the embedded content and use them as background image of the overlay. Currently supported: %l.', 'embed-privacy' ),
					Embed_Privacy::get_instance()->thumbnail->get_provider_titles()
				),
				'name' => 'embed_privacy_download_thumbnails',
				'option_type' => 'option',
				'title' => \__( 'Download thumbnails', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_download_thumbnails' );
		\add_settings_field(
			'embed_privacy_preserve_data_on_uninstall',
			\__( 'Data handling', 'embed-privacy' ),
			[ Field::class, 'get' ],
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
	 * Register menu items.
	 */
	public static function register_menu() {
		\add_submenu_page(
			'options-general.php',
			\__( 'Embed Privacy', 'embed-privacy' ),
			\__( 'Embed Privacy', 'embed-privacy' ),
			self::CAPABILITY,
			'embed_privacy',
			[ self::class, 'get_page' ]
		);
	}
}
