<?php
namespace epiphyt\Embed_Privacy\handler;

/**
 * Feed handler.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.11.2
 */
final class Feed {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'embed_privacy_template_markup', [ self::class, 'replace_template' ], 10, 3 );
	}
	
	/**
	 * Replace template in feeds with a link.
	 * 
	 * @param	string									$markup Embed overlay markup
	 * @param	\epiphyt\Embed_Privacy\embed\Provider	$provider Embed provider
	 * @param	array									$attributes Embed attributes
	 * @return	string Link markup 
	 */
	public static function replace_template( $markup, $provider, $attributes ) {
		if ( ! \is_feed() ) {
			return $markup;
		}
		
		return self::get_link_markup( $attributes, $provider );
	}
	
	/**
	 * Get link markup for the embedded content.
	 * 
	 * @param	mixed[]									$attributes Embed attributes
	 * @param	\epiphyt\Embed_Privacy\embed\Provider	$provider Embed provider
	 * @return	string Link markup
	 */
	public static function get_link_markup( $attributes, $provider ) {
		return \sprintf(
			'<span class="embed-privacy-url"><a href="%1$s">%2$s</a></span>',
			\esc_url( $attributes['embed_url'] ),
			\sprintf(
				/* translators: embed provider title */
				\esc_html__( 'Open embedded content from %s', 'embed-privacy' ),
				\esc_html( $provider->get_title() )
			)
		);
	}
}
