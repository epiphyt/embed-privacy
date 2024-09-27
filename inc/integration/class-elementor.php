<?php
namespace epiphyt\Embed_Privacy\integration;

use DOMDocument;
use DOMElement;
use DOMNode;
use Elementor\Plugin;
use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\embed\Template;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\System;

/**
 * Elementor integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Elementor {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_action( 'embed_privacy_print_assets', [ self::class, 'enqueue_assets' ] );
		\add_action( 'embed_privacy_register_assets', [ self::class, 'register_assets' ], 10, 2 );
		\add_filter( 'embed_privacy_overlay_replaced_content', [ self::class, 'replace_youtube' ] );
	}
	
	/**
	 * Enqueue assets.
	 */
	public static function enqueue_assets() {
		if ( self::is_used() ) {
			\wp_enqueue_script( 'embed-privacy-elementor-video' );
			\wp_enqueue_style( 'embed-privacy-elementor' );
		}
	}
	
	/**
	 * Get an overlay for Elementor YouTube videos.
	 * 
	 * @param	string	$content The content
	 * @return	string The content with an embed overlay (if needed)
	 */
	private static function get_youtube_overlay( $content ) {
		$provider = Providers::get_instance()->get_by_name( 'youtube' );
		$replacements = [];
		
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$use_errors = \libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . $content . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		$template_dom = new DOMDocument();
		
		/** @var	\DOMElement $element */
		foreach ( $dom->getElementsByTagName( 'div' ) as $element ) {
			if ( ! \str_contains( $element->getAttribute( 'data-settings' ), 'youtube_url' ) ) {
				continue;
			}
			
			$settings = \json_decode( $element->getAttribute( 'data-settings' ) );
			$args = [];
			
			if ( ! empty( $settings->youtube_url ) ) {
				$args['embed_url'] = $settings->youtube_url;
			}
			
			// get overlay template as DOM element
			$template_dom->loadHTML(
				'<html><meta charset="utf-8">' . Template::get( $provider, $dom->saveHTML( $element ), $args ) . '</html>',
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
			$overlay = null;
			
			/** @var	\DOMElement $div */
			foreach ( $template_dom->getElementsByTagName( 'div' ) as $div ) {
				if ( \stripos( $div->getAttribute( 'class' ), 'embed-privacy-container' ) !== false ) {
					$overlay = $div;
					break;
				}
			}
			
			// store the elements to replace (see regressive loop down below)
			if ( $overlay instanceof DOMNode || $overlay instanceof DOMElement ) {
				$replacements[] = [
					'element' => $element,
					'replace' => $dom->importNode( $overlay, true ),
				];
			}
		}
		
		if ( ! empty( $replacements ) ) {
			Embed_Privacy::get_instance()->did_replacements = \array_merge( Embed_Privacy::get_instance()->did_replacements, $replacements );
			Embed_Privacy::get_instance()->has_embed = true;
			$elements = $dom->getElementsByTagName( 'div' );
			$i = $elements->length - 1;
			
			// use regressive loop for replaceChild()
			// see: https://www.php.net/manual/en/domnode.replacechild.php#50500
			while ( $i > -1 ) {
				$element = $elements->item( $i );
				
				foreach ( $replacements as $replacement ) {
					if ( $replacement['element'] === $element ) {
						$element->parentNode->replaceChild( $replacement['replace'], $replacement['element'] );
					}
				}
				
				--$i;
			}
			
			$content = \str_replace( [ '<html><meta charset="utf-8">', '</html>' ], '', $dom->saveHTML( $dom->documentElement ) );
		}
		
		\libxml_use_internal_errors( $use_errors );
		// phpcs:enable
		
		return $content;
	}
	
	/**
	 * Check if a post is written in Elementor.
	 * 
	 * @return	bool Whether Elementor has been used
	 */
	public static function is_used() {
		return System::is_plugin_active( 'elementor/elementor.php' )
			&& \get_the_ID()
			&& Plugin::$instance->documents->get( \get_the_ID() )->is_built_with_elementor();
	}
	
	/**
	 * Register assets.
	 * 
	 * @param	bool	$is_debug Whether debug mode is enabled
	 * @param	string	$suffix A filename suffix
	 */
	public static function register_assets( $is_debug, $suffix ) {
		$js_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/js/elementor-video' . $suffix . '.js';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/js/elementor-video' . $suffix . '.js' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_script( 'embed-privacy-elementor-video', $js_file_url, [], $file_version, [ 'strategy' => 'defer' ] );
		
		$css_file_url = \EPI_EMBED_PRIVACY_URL . 'assets/style/elementor' . $suffix . '.css';
		$file_version = $is_debug ? \filemtime( \EPI_EMBED_PRIVACY_BASE . 'assets/style/elementor' . $suffix . '.css' ) : \EMBED_PRIVACY_VERSION;
		
		\wp_register_style( 'embed-privacy-elementor', $css_file_url, [], $file_version );
	}
	
	/**
	 * Replace YouTube videos.
	 * As they are not embedded via regular iframe, we need to handle them manually.
	 * 
	 * @param	string	$content Current replaced content
	 * @return	string Updated replaced content
	 */
	public static function replace_youtube( $content ) {
		if ( ! self::is_used() ) {
			return $content;
		}
		
		if ( \str_contains( $content, 'youtube.com\/watch' ) ) {
			$content = self::get_youtube_overlay( $content );
			Embed_Privacy::get_instance()->frontend->print_assets();
		}
		
		return $content;
	}
}
