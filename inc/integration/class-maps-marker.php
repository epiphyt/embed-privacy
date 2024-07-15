<?php
namespace epiphyt\Embed_Privacy\integration;

use DOMDocument;
use epiphyt\Embed_Privacy\Embed_Privacy;

/**
 * Maps Marker integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Maps_Marker {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'do_shortcode_tag', [ self::class, 'replace' ], 10, 2 );
	}
	
	/**
	 * Replace Maps Marker (Pro) shortcodes.
	 * 
	 * @param	string	$output Shortcode output
	 * @param	string	$tag Shortcode tag
	 * @return	string Updated shortcode output
	 */
	public static function replace( $output, $tag ) {
		if ( $tag !== 'mapsmarker' ) {
			return $output;
		}
		
		if ( Embed_Privacy::get_instance()->is_ignored_request ) {
			return $output;
		}
		
		$embed_provider = Embed_Privacy::get_instance()->get_embed_by_name( 'maps-marker' );
		
		if ( \get_post_meta( $embed_provider->ID, 'is_disabled', true ) ) {
			return $output;
		}
		
		return Embed_Privacy::get_instance()->get_output_template( $embed_provider->post_title, $embed_provider->post_name, $output );
	}
}
