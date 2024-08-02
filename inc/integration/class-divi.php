<?php
namespace epiphyt\Embed_Privacy\integration;

use DOMDocument;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\handler\Theme;
use epiphyt\Embed_Privacy\Replacer;

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
		\add_filter( 'embed_privacy_print_assets', [ self::class, 'enqueue_assets' ] );
		\add_action( 'embed_privacy_register_assets', [ self::class, 'register_assets' ], 10, 2 );
		\add_filter( 'et_builder_get_oembed', [ $this, 'replace' ], 10, 2 );
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
	 * Replace embeds in Divi Builder.
	 * 
	 * @param	string	$item_embed The original output
	 * @param	string	$url The URL of the embed
	 * @return	string The updated embed code
	 */
	public function replace( $item_embed, $url ) {
		if ( Embed_Privacy::get_instance()->is_ignored_request ) {
			return $item_embed;
		}
		
		$attributes = [];
		$use_internal_errors = \libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $item_embed . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		
		/** @var \DOMElement $iframe */
		foreach ( $dom->getElementsByTagName( 'iframe' ) as $iframe ) {
			$attributes['height'] = $iframe->hasAttribute( 'height' ) ? $iframe->getAttribute( 'height' ) : 0;
			$attributes['width'] = $iframe->hasAttribute( 'width' ) ? $iframe->getAttribute( 'width' ) : 0;
		}
		
		\libxml_use_internal_errors( $use_internal_errors );
		
		return Replacer
		
		return Embed_Privacy::get_instance()->replace_embeds_oembed( $item_embed, $url, $attributes );
	}
}
