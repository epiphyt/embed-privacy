<?php
namespace epiphyt\Embed_Privacy\integration;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\embed\Template;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\handler\Theme;

/**
 * Divi integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Divi {
	/**
	 * Initialize functionality.
	 */
	public function init() {
		\add_action( 'embed_privacy_register_assets', [ self::class, 'register_assets' ], 10, 2 );
		\add_filter( 'embed_privacy_print_assets', [ self::class, 'enqueue_assets' ] );
		\add_filter( 'et_builder_resolve_dynamic_content', [ self::class, 'add_dynamic_content_filter' ], 5 );
		\add_filter( 'et_builder_resolve_dynamic_content', [ self::class, 'remove_dynamic_content_filter' ], \PHP_INT_MAX );
		\add_filter( 'et_module_process_display_conditions', [ self::class, 'replace_google_maps' ], 10, 3 );
		\add_filter( 'et_pb_enqueue_google_maps_script', '__return_false' );
	}
	
	/**
	 * Add filter for dynamic content.
	 * 
	 * @since	1.10.9
	 * 
	 * @param	string	$content Current dynamic content
	 * @return	string Current dynamic content
	 */
	public static function add_dynamic_content_filter( $content ) {
		\add_filter( 'wp_kses_allowed_html', [ self::class, 'allow_script_in_post' ], 10, 2 );
		
		return $content;
	}
	
	/**
	 * Allow script tags in post, since Divi runs a wp_kses_post over the embed.
	 * 
	 * @since	1.10.9
	 * 
	 * @param	array	$html List of allowed HTML tags and attributes
	 * @param	string	$context Current context
	 * @return	array Updated list of allowed HTML
	 */
	public static function allow_script_in_post( array $html, $context ) {
		if ( $context === 'post' ) {
			if ( ! isset( $html['input'] ) ) {
				$html['input'] = [
					'class' => true,
					'date-*' => true,
					'id' => true,
					'type' => true,
					'value' => true,
				];
			}
			
			$html['script'] = [
				'type' => true,
			];
		}
		
		return $html;
	}
	
	/**
	 * Enqueue assets.
	 */
	public static function enqueue_assets() {
		if ( Theme::is( 'Divi' ) ) {
			\wp_enqueue_script( 'embed-privacy-divi' );
			\wp_enqueue_style( 'embed-privacy-divi' );
		}
	}
	
	/**
	 * Replace Google Maps Markup in Divi.
	 * 
	 * @param	string				$output Current output
	 * @param	string				$render_method Divi render method
	 * @param	\ET_Builder_Module	$module Module instance
	 * @return	string Updated output
	 */
	public static function replace_google_maps( $output, $render_method, $module ) {
		if ( $render_method === 'render_as_builder_data' ) {
			return $output;
		}
		
		if ( ! $module instanceof \ET_Builder_Module_Map ) {
			return $output;
		}
		
		global $wp_scripts;
		Embed_Privacy::get_instance()->has_embed = true;
		Embed_Privacy::get_instance()->frontend->print_assets();
		
		$output .= \sprintf(
			'<script id="%1$s">
				$.ajax( {
					url: "%2$s",
					dataType: "script",
					success: function() {
						embed_privacy_et_pb_init_maps();
					},
				} );
			</script>',
			\esc_attr( $wp_scripts->registered['google-maps-api']->handle . '-embed-privacy' ),
			\esc_url(
				\add_query_arg(
					[
						'key' => \et_pb_get_google_api_key(),
						'v' => 3,
						'ver' => $wp_scripts->registered['google-maps-api']->ver,
					],
					$wp_scripts->registered['google-maps-api']->src
				)
			)
		);
		$output .= "<script>
		var embed_privacy_et_pb_map=$('.et_pb_map_container');
		var embed_privacy_et_pb_init_maps = function embed_privacy_et_pb_init_maps() {
			embed_privacy_et_pb_map.each(function() {
				embed_privacy_et_pb_map_init($(this));
			});
		};
		</script>";
		
		return Template::get( Providers::get_instance()->get_by_name( 'google-maps' ), $output );
	}
	
	/**
	 * Register assets.
	 * 
	 * @param	bool	$is_debug Whether debug mode is enabled
	 * @param	string	$suffix A filename suffix
	 */
	public static function register_assets( $is_debug, $suffix ) {
		$js_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/divi' . $suffix . '.js';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/js/divi' . $suffix . '.js' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_script( 'embed-privacy-divi', $js_file_url, [], $file_version, [ 'strategy' => 'defer' ] );
		
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/divi' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/divi' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-divi', $css_file_url, [], $file_version );
	}
	
	/**
	 * Remove filter for dynamic content.
	 * 
	 * @since	1.10.9
	 * 
	 * @param	string	$content Current dynamic content
	 * @return	string Current dynamic content
	 */
	public static function remove_dynamic_content_filter( $content ) {
		\remove_filter( 'wp_kses_allowed_html', [ self::class, 'allow_script_in_post' ], 10 );
		
		return $content;
	}
}
