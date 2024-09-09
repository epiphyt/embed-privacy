<?php
namespace epiphyt\Embed_Privacy\integration;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Twitter integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Twitter {
	/**
	 * Transform a tweet into a local one.
	 * 
	 * @param	string	$html Embed code
	 * @return	string Local embed
	 */
	public static function get_local_tweet( $html ) {
		\libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $html . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// remove script tag
		foreach ( $dom->getElementsByTagName( 'script' ) as $script ) {
			$script->parentNode->removeChild( $script );
		}
		
		$xpath = new DOMXPath( $dom );
		
		// get text node, which represents the author name
		// and give it a span with class
		foreach ( $xpath->query( '//blockquote/text()' ) as $node ) {
			$author_node = $dom->createElement( 'span', $node->nodeValue );
			$author_node->setAttribute( 'class', 'embed-privacy-author-meta' );
			$node->parentNode->replaceChild( $author_node, $node );
		}
		
		// wrap author name by a meta div
		/** @var	\DOMElement $node */
		foreach ( $dom->getElementsByTagName( 'span' ) as $node ) {
			if ( $node->getAttribute( 'class' ) !== 'embed-privacy-author-meta' ) {
				continue;
			}
			
			// create meta cite
			$parent_node = $dom->createElement( 'cite' );
			$parent_node->setAttribute( 'class', 'embed-privacy-tweet-meta' );
			// append created cite to blockquote
			$node->parentNode->appendChild( $parent_node );
			// move author meta inside meta cite
			$parent_node->appendChild( $node );
		}
		
		/** @var	\DOMElement $link */
		foreach ( $dom->getElementsByTagName( 'a' ) as $link ) {
			if ( ! \preg_match( '/https?:\/\/twitter.com\/([^\/]+)\/status\/(\d+)/', $link->getAttribute( 'href' ) ) ) {
				continue;
			}
			
			// modify date in link to tweet
			$l10n_date = \wp_date( \get_option( 'date_format' ), \strtotime( $link->nodeValue ) );
			
			if ( \is_string( $l10n_date ) ) {
				$link->nodeValue = $l10n_date;
			}
			
			// move link inside meta div
			if ( isset( $parent_node ) && $parent_node instanceof DOMElement ) {
				$parent_node->appendChild( $link );
			}
		}
		
		$content = $dom->saveHTML( $dom->documentElement );
		// phpcs:enable
		
		return \str_replace( [ '<html><meta charset="utf-8">', '</html>' ], [ '<div class="embed-privacy-local-tweet">', '</div>' ], $content );
	}
}
