<?php
namespace epiphyt\Embed_Privacy\handler;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\Embed_Privacy;

/**
 * Shortcode handler.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Shortcode {
	/**
	 * @var		string[] List of ignored shortcodes
	 */
	private $ignored_shortcodes = [
		'embed_privacy_opt_out',
		'grw',
	];
	
	/**
	 * Initialize functionality.
	 */
	public function init() {
		\add_shortcode( 'embed_privacy_opt_out', [ self::class, 'opt_out' ] );
		\add_filter( 'the_content', [ $this, 'print_assets_for_shortcode' ] );
	}
	
	/**
	 * Get a list with ignored shortcodes.
	 * 
	 * @return	string[] List with ignored shortcodes
	 */
	public function get_ignored() {
		/**
		 * Filter the ignored shortcodes list.
		 * 
		 * @since	1.6.0
		 * 
		 * @param	string[]	$ignored_shortcodes Current list of ignored shortcodes
		 */
		$this->ignored_shortcodes = \apply_filters( 'embed_privacy_ignored_shortcodes', $this->ignored_shortcodes );
		
		return $this->ignored_shortcodes;
	}
	
	/**
	 * Display an Opt-out shortcode.
	 * 
	 * @param	array|string	$attributes Shortcode attributes
	 * @return	string The shortcode output
	 */
	public static function opt_out( $attributes ) {
		$attributes = \shortcode_atts( [
			'headline' => \__( 'Embed providers', 'embed-privacy' ),
			'show_all' => 0,
			'subline' => \__( 'Enable or disable embed providers globally. By enabling a provider, its embedded content will be displayed directly on every page without asking you anymore.', 'embed-privacy' ),
		], $attributes );
		$cookie = Embed_Privacy::get_instance()->get_cookie();
		$embed_providers = Providers::get_instance()->get_list();
		$enabled_providers = \array_keys( (array) $cookie );
		
		if ( empty( $embed_providers ) ) {
			return '';
		}
		
		$headline = '<h3>' . \esc_html( $attributes['headline'] ) . '</h3>' . \PHP_EOL;
		
		/**
		 * Filter the opt-out headline.
		 * 
		 * @param	string	$headline Current headline HTML
		 * @param	array	$attributes Shortcode attributes
		 */
		$headline = \apply_filters( 'embed_privacy_opt_out_headline', $headline, $attributes );
		
		/**
		 * Filter the opt-out subline.
		 * 
		 * @param	string	$subline Current subline HTML
		 * @param	array	$attributes Shortcode attributes
		 */
		$subline = \apply_filters( 'embed_privacy_opt_out_subline', '<p>' . \esc_html( $attributes['subline'] ) . '</p>' . \PHP_EOL, $attributes );
		
		$output = '<div class="embed-privacy-opt-out" data-show-all="' . ( $attributes['show_all'] ? 1 : 0 ) . '">' . \PHP_EOL . $headline . $subline;
		
		foreach ( $embed_providers as $provider ) {
			$is_checked = false;
			
			if ( $attributes['show_all'] ) {
				$is_checked = \in_array( $provider->get_name(), $enabled_providers, true );
			}
			
			$is_hidden = ! $attributes['show_all'] && ! \in_array( $provider->get_name(), $enabled_providers, true );
			$microtime = \str_replace( '.', '', \microtime( true ) );
			$output .= '<span class="embed-privacy-provider' . ( $is_hidden ? ' is-hidden' : '' ) . '">' . \PHP_EOL;
			$output .= '<label class="embed-privacy-opt-out-label" for="embed-privacy-provider-' . \esc_attr( $provider->get_name() ) . '-' . $microtime . '" data-embed-provider="' . \esc_attr( $provider->get_name() ) . '">';
			$output .= '<input type="checkbox" id="embed-privacy-provider-' . \esc_attr( $provider->get_name() ) . '-' . $microtime . '" ' . \checked( $is_checked, true, false ) . ' class="embed-privacy-opt-out-input" data-embed-provider="' . \esc_attr( $provider->get_name() ) . '"> ';
			$output .= \sprintf(
				/* translators: embed provider title */
				\esc_html__( 'Load all embeds from %s', 'embed-privacy' ),
				\esc_html( $provider->get_title() )
			);
			$output .= '</label><br>' . \PHP_EOL;
			$output .= '</span>' . \PHP_EOL;
		}
		
		$output .= '</div>' . \PHP_EOL;
		
		return $output;
	}
	
	/**
	 * Print Embed Privacy assets if page contains the shortcode.
	 * 
	 * @since	1.11.1
	 * 
	 * @param	string	$content Current page content
	 * @return	string Current page content
	 */
	public function print_assets_for_shortcode( $content ) {
		if ( \has_shortcode( $content, 'embed_privacy_opt_out' ) ) {
			Embed_Privacy::get_instance()->frontend->print_assets();
		}
		
		return $content;
	}
}
