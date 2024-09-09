<?php
namespace epiphyt\Embed_Privacy;

use epiphyt\Embed_Privacy\integration\Amp;
use WP_Post;

/**
 * Frontend functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Frontend {
	/**
	 * @var		bool Whether the current request has printed Embed Privacy assets.
	 */
	private $is_printed = false;
	
	/**
	 * Initialize functionality.
	 */
	public function init() {
		\add_action( 'init', [ $this, 'register_assets' ] );
	}
	
	/**
	 * Handle printing assets.
	 */
	public function print_assets() {
		if ( $this->is_printed ) {
			return;
		}
		
		\wp_enqueue_script( 'embed-privacy' );
		\wp_enqueue_style( 'embed-privacy' );
		\wp_localize_script( 'embed-privacy', 'embedPrivacy', [
			'alwaysActiveProviders' => \array_keys( (array) Embed_Privacy::get_instance()->get_cookie() ), // deprecated
			'javascriptDetection' => \get_option( 'embed_privacy_javascript_detection' ),
		] );
		
		/**
		 * Fires after assets are printed.
		 * 
		 * @since	1.10.0
		 */
		\do_action( 'embed_privacy_print_assets' );
		
		$this->is_printed = true;
	}
	
	/**
	 * Register our assets for the frontend.
	 */
	public function register_assets() {
		if ( \is_admin() || \wp_doing_ajax() || \wp_doing_cron() ) {
			return;
		}
		
		$is_debug = \defined( 'WP_DEBUG' ) && \WP_DEBUG;
		$suffix = ( $is_debug ? '' : '.min' );
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/embed-privacy' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/embed-privacy' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy', $css_file_url, [], $file_version );
		
		if ( ! Amp::is_amp() ) {
			$js_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/embed-privacy' . $suffix . '.js';
			$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/js/embed-privacy' . $suffix . '.js' ) : \EMBED_PRIVACY_VERSION;
			
			\wp_register_script( 'embed-privacy', $js_file_url, [], $file_version, [ 'strategy' => 'defer' ] );
		}
		
		/**
		 * Fires after assets have been registered.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	bool	$is_debug Whether debug mode is enabled
		 * @param	string	$suffix A filename suffix
		 */
		\do_action( 'embed_privacy_register_assets', $is_debug, $suffix );
		
		$current_url = \sprintf(
			'http%1$s://%2$s%3$s',
			\is_ssl() ? 's' : '',
			! empty( $_SERVER['HTTP_HOST'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '',
			! empty( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''
		);
		
		if ( empty( $_SERVER['HTTP_HOST'] ) ) {
			return;
		}
		
		$post_id = \url_to_postid( $current_url );
		
		if ( $post_id ) {
			$post = \get_post( $post_id );
			
			if ( $post instanceof WP_Post && \has_shortcode( $post->post_content, 'embed_privacy_opt_out' ) ) {
				$this->print_assets();
			}
		}
	}
}
