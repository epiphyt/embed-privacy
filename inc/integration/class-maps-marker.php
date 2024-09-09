<?php
namespace epiphyt\Embed_Privacy\integration;

use DOMDocument;
use epiphyt\Embed_Privacy\data\Replacer;
use epiphyt\Embed_Privacy\embed\Provider;
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
		\add_filter( 'embed_privacy_overlay_provider', [ self::class, 'set_provider' ], 10, 2 );
	}
	
	/**
	 * Get the map dimensions.
	 * 
	 * @param	string	$content Embedded content
	 * @return	array Embed dimensions (height and width)
	 */
	private static function get_dimensions( $content ) {
		$height = '';
		$width = '100%';
		$use_errors = \libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $content . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		
		/** @var	\DOMElement $element */
		foreach ( $dom->getElementsByTagName( 'div' ) as $element ) {
			if ( $element->getAttribute( 'class' ) === 'mmp-map' ) {
				$style = $element->getAttribute( 'style' );
				\preg_match( '/height:\s*(?<height>\d+)/', $style, $height_matches );
				\preg_match( '/width:\s*(?<width>\d+)/', $style, $width_matches );
				
				if ( ! empty( $height_matches['height'] ) ) {
					$height = $height_matches['height'];
				}
				
				if ( ! empty( $width_matches['width'] ) ) {
					$width = $width_matches['width'];
				}
			}
		}
		
		\libxml_use_internal_errors( $use_errors );
		
		return [
			'height' => $height,
			'width' => $width,
		];
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
		
		$attributes = self::get_dimensions( $output );
		$attributes['is_oembed'] = true;
		
		return Replacer::replace_oembed( $output, '', $attributes );
	}
	
	/**
	 * Set the Maps Marker Pro provider.
	 * 
	 * @param	\epiphyt\Embed_Privacy\embed\Provider|null	$provider Current provider
	 * @param	string										$content Embedded content
	 * @return	\epiphyt\Embed_Privacy\embed\Provider|null Updated provider
	 */
	public static function set_provider( $provider, $content ) {
		if (
			! $provider
			&& ( \str_contains( $content, 'maps-marker-pro' ) || \str_contains( $content, '[mapsmarker' ) )
		) {
			$provider = new Provider();
			$provider->set_name( 'maps-marker-pro' );
			$provider->set_pattern( '/maps-marker-pro|^\[mapsmarker/' );
			$provider->set_title( \__( 'Maps Marker Pro', 'embed-privacy' ) );
		}
		
		return $provider;
	}
}
