<?php
namespace epiphyt\Embed_Privacy\integration;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\embed\Template;

/**
 * Enfold integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.13.0
 */
final class Enfold {
	/**
	 * @var		int Depth of [av_video] shortcode
	 */
	public static $av_video_depth = 0;
	
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'do_shortcode_tag', [ self::class, 'decrement_depth' ], 10, 2 );
		\add_filter( 'embed_privacy_custom_embed_replacement', [ self::class, 'maybe_set_original_content' ], 10, 2 );
		\add_filter( 'embed_privacy_custom_oembed_replacement', [ self::class, 'maybe_set_original_content' ], 10, 2 );
		\add_filter( 'embed_privacy_ignored_shortcodes', [ self::class, 'set_ignored_shortcode' ] );
		\add_filter( 'pre_do_shortcode_tag', [ self::class, 'increment_depth' ], 10, 2 );
		\add_filter( 'avf_sc_video_output', [ self::class, 'set_video_output' ], 10, 2 );
	}
	
	/**
	 * Decrement [av_video] shortcode depth
	 * 
	 * @param	string	$output Shortcode output
	 * @param	string	$tag Shortcode tag name
	 * @return	string Shortcode output
	 */
	public static function decrement_depth( $output, $tag ) {
		if ( $tag === 'av_video' && self::$av_video_depth > 0 ) {
			--self::$av_video_depth;
		}
		
		return $output;
	}
	
	/**
	 * Increment [av_video] shortcode depth
	 * 
	 * @param	string	$output Shortcode output
	 * @param	string	$tag Shortcode tag name
	 * @return	string Shortcode output
	 */
	public static function increment_depth( $output, $tag ) {
		if ( $tag === 'av_video' ) {
			++self::$av_video_depth;
		}
		
		return $output;
	}
	
	/**
	 * Maybe set original content for this embed.
	 * If we're in context of the [av_video] shortcode, return the original.
	 * Otherwise the custom one.
	 * 
	 * @param	string	$new_content The new content
	 * @param	string	$original_content The original content
	 * @return	string Either the new or original content
	 */
	static function maybe_set_original_content( $new_content, $original_content ) {
		if ( self::$av_video_depth ) {
			return $original_content;
		}
		
		return $new_content;
	}
	
	/**
	 * Ignore the Avia video shortcode.
	 * 
	 * @param	string[]	$ignored_shortcodes List of shortcodes
	 * @return	string[] Updated list of shortcodes
	 */
	public static function set_ignored_shortcode( array $ignored_shortcodes ) {
		$ignored_shortcodes[] = 'av_video';
		
		return $ignored_shortcodes;
	}
	
	/**
	 * Set video shortcode output.
	 * 
	 * @param	string	$output Current shortcode output
	 * @param	array	$atts Shortcode attributes
	 * @return	string Updated shortcode output
	 */
	static function set_video_output( $output, array $atts ) {
		if ( empty( $atts['src'] ) ) {
			return $output;
		}
		
		$current_provider = null;
		
		foreach ( Providers::get_instance()->get_list() as $provider ) {
			if ( empty( $provider->get_pattern() ) ) {
				continue;
			}
			
			if ( \preg_match( $provider->get_pattern(), $atts['src'] ) ) {
				$current_provider = $provider;
			}
		}
		
		if ( ! $current_provider instanceof Provider ) {
			return $output;
		}
		
		return Template::get( $current_provider, $output, $atts );
	}
}
