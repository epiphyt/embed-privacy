<?php
namespace epiphyt\Embed_Privacy\handler;

use DOMDocument;

/**
 * oEmbed handler.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Oembed {
	/**
	 * Get the dimensions of an oEmbed.
	 * 
	 * @param	string	$content The content to get the title of
	 * @return	array The dimensions or an empty array
	 */
	public static function get_dimensions( $content ) {
		$use_error = \libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $content . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		\libxml_use_internal_errors( $use_error );
		
		foreach ( [ 'embed', 'iframe', 'img', 'object' ] as $tag ) {
			/** @var	\DOMElement $element */
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				$height = $element->getAttribute( 'height' );
				$width = $element->getAttribute( 'width' );
				
				if ( $height && $width ) {
					return [
						'height' => $height,
						'width' => $width,
					];
				}
			}
		}
		
		return [];
	}
	
	/**
	 * Get an oEmbed title by its title attribute.
	 * 
	 * @param	string	$content The content to get the title of
	 * @return	string The title or an empty string
	 */
	public static function get_title( $content ) {
		$use_error = \libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $content . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		\libxml_use_internal_errors( $use_error );
		
		foreach ( [ 'embed', 'iframe', 'object' ] as $tag ) {
			/** @var	\DOMElement $element */
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				$title = $element->getAttribute( 'title' );
				
				if ( $title ) {
					return $title;
				}
			}
		}
		
		return '';
	}
}
