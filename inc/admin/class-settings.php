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
			
			<form action="options.php" method="post" class="embed-privacy__settings-form">
				<?php
				\settings_fields( 'embed_privacy' );
				\do_settings_sections( 'embed_privacy' );
				\submit_button( \esc_html__( 'Save Settings', 'embed-privacy' ) );
				?>
			</form>
			
			<h2><?php \esc_html_e( 'Support data', 'embed-privacy' ); ?></h2>
			<p><?php \esc_html_e( 'If you file a support request, please include the following data.', 'embed-privacy' ); ?></p>
			
			<div class="embed-privacy__copy-to-clipboard--container">
				<button type="button" class="button embed-privacy__support-data--copy-to-clipboard embed-privacy__copy-to-clipboard" data-copy="embed-privacy__support-data--code" data-status="embed-privacy__copy-to-clipboard--status--support-data"><?php \esc_html_e( 'Copy support data to clipboard', 'embed-privacy' ); ?></button>
				<p class="embed-privacy__copy-to-clipboard--status embed-privacy__copy-to-clipboard--status--support-data" role="status" aria-live="polite" aria-atomic="true"></p>
			</div>
			<pre class="embed-privacy__support-data--code-container"><code class="embed-privacy__support-data--code"><?php echo \esc_html( Support_Data::get() ); ?></code></pre>
		</div>
		<?php
	}
	
	/**
	 * Register settings.
	 */
	public static function register() {
		\add_settings_section( 'embed_privacy_general', null, '__return_null', 'embed_privacy' );
		\add_settings_field(
			'embed_privacy_local_activitypub_posts',
			\__( 'Embeds', 'embed-privacy' ),
			[ Field::class, 'get' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => \__( 'By enabling this option, ActivityPub posts are embedded locally as text without any connection to the particular ActivityPub server, and no privacy overlay is required.', 'embed-privacy' ),
				'name' => 'embed_privacy_local_activitypub_posts',
				'option_type' => 'option',
				'title' => \__( 'Local ActivityPub posts', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_local_activitypub_posts' );
		\add_settings_field(
			'embed_privacy_local_tweets',
			\__return_empty_string(),
			[ Field::class, 'get' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => \__( 'By enabling this option, X posts are embedded locally as text without any connection to X, and no privacy overlay is required.', 'embed-privacy' ),
				'name' => 'embed_privacy_local_tweets',
				'option_type' => 'option',
				'title' => \__( 'Local X posts', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_local_tweets' );
		\add_settings_field(
			'embed_privacy_disable_link',
			\__return_empty_string(),
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
			\__return_empty_string(),
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
			'embed_privacy_force_script_loading',
			\__return_empty_string(),
			[ Field::class, 'get' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => \__( 'This loads the embed scripts on every page. Only use this option if your website loads content dynamically via JavaScript, that can include embedded content.', 'embed-privacy' ),
				'name' => 'embed_privacy_force_script_loading',
				'option_type' => 'option',
				'title' => \__( 'Force script loading', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		\register_setting( 'embed_privacy', 'embed_privacy_force_script_loading' );
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
