<?php
namespace epiphyt\Embed_Privacy\integration;

/**
 * Cover Block integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.13.0
 */
final class Cover_Block {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'the_content', [ self::class, 'replace_background_dim' ], 1000 );
	}
	
	/**
	 * Replace the background dimming element within cover blocks.
	 * 
	 * That makes the overlay content accessible to allow interaction
	 * while preservice the dimming functionality.
	 * 
	 * @param	string	$content Post/page content
	 * @return	string Updated content
	 */
	public static function replace_background_dim( $content ) {
		if ( ! \str_contains( $content, 'embed-privacy-container' ) || ! \str_contains( $content, 'wp-block-cover' ) ) {
			return $content;
		}
		
		$use_errors = \libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $content . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		$xpath = new \DOMXPath( $dom );
		$class = static function ( $name ) {
			return "contains(concat(' ', normalize-space(@class), ' '), ' {$name} ')";
		};
		
		$covers = $xpath->query( "//*[{$class('wp-block-cover')}][.//*[{$class('embed-privacy-container')}]]" );
		
		foreach ( $covers as $cover ) {
			$overlay = $xpath->query( ".//*[{$class('wp-block-cover__background')}]", $cover )->item( 0 );
			$container = $xpath->query( ".//*[{$class('embed-privacy-container')}]", $cover )->item( 0 );
			
			if ( ! $overlay || ! $container ) {
				continue;
			}
			
			// Move the dim right before EP's overlay so the overlay paints on top of it.
			$embed_privacy_overlay = $xpath->query( ".//*[{$class('embed-privacy-overlay')}]", $container )->item( 0 );
			
			if ( $embed_privacy_overlay ) {
				$embed_privacy_overlay->parentNode->insertBefore( $overlay, $embed_privacy_overlay );
			}
			else {
				$container->appendChild( $overlay );
			}
		}
		
		// Serialize the inner markup only: drop the <html> wrapper and the charset meta.
		$html = '';
		
		foreach ( $dom->documentElement->childNodes as $node ) {
			if ( $node->nodeName === 'meta' ) {
				continue;
			}
			
			$html .= $dom->saveHTML( $node );
		}
		
		\libxml_use_internal_errors( $use_errors );
		
		return $html;
	}
}
