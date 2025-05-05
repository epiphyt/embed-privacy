<?php
namespace epiphyt\Embed_Privacy\data;

use epiphyt\Embed_Privacy\embed\Replacement;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\handler\Oembed;

/**
 * Replacer functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Replacer {
	/**
	 * Extend a regular expression pattern with certain tags.
	 * 
	 * @param	string										$pattern Pattern to extend
	 * @param	\epiphyt\Embed_privacy\embed\Provider|null	$provider Current embed provider
	 * @return	string Extended pattern
	 */
	public static function extend_pattern( $pattern, $provider ) {
		if ( empty( $pattern ) ) {
			return $pattern;
		}
		
		if ( \str_contains( $pattern, '<' ) && \str_contains( $pattern, '>' ) ) {
			return $pattern;
		}
		
		$allowed_tags = [
			'blockquote',
			'div',
			'embed',
			'iframe',
			'object',
		];
		
		/**
		 * Filter allowed HTML tags in regular expressions.
		 * Only elements matching these tags get processed.
		 * 
		 * @deprecated	1.10.0 Use embed_privacy_replacer_matcher_elements instead
		 * @since		1.6.0
		 * 
		 * @param	string[]	$allowed_tags The allowed tags
		 * @param	string		$provider_name The embed provider without spaces and in lowercase
		 * @return	array A list of allowed tags
		 */
		$allowed_tags = \apply_filters_deprecated(
			'embed_privacy_matcher_elements',
			[
				$allowed_tags,
				$provider->get_name(),
			],
			'1.10.0',
			'embed_privacy_replacer_matcher_elements'
		);
		
		/**
		 * Filter allowed HTML tags in regular expressions.
		 * Only elements matching these tags get processed.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	string[]									$allowed_tags List of allowed tags
		 * @param	\epiphyt\Embed_privacy\embed\Provider|null	$provider Embed provider
		 * @return	array Updated list of allowed tags
		 */
		$allowed_tags = (array) \apply_filters( 'embed_privacy_replacer_matcher_elements', $allowed_tags, $provider );
		
		$tags_regex = '(' . \implode( '|', \array_filter( $allowed_tags, static function( $tag ) {
			return \preg_quote( $tag, '/' );
		} ) ) . ')';
		$pattern = '/<' . $tags_regex . '([^"]*)"([^<]*)(?<original_pattern>' . \trim( $pattern, '/' ) . ')([^"]*)"([^>]*)(>(.*?)<\/' . $tags_regex . ')?>/';
		
		return $pattern;
	}
	
	/**
	 * Replace embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @param	string	$content The original content
	 * @param	string	$tag The shortcode tag if called via do_shortcode
	 * @return	string The updated content
	 */
	public static function replace_embeds( $content, $tag = '' ) {
		$embed_privacy = Embed_Privacy::get_instance();
		
		// do nothing in admin
		if ( ! $embed_privacy->use_cache ) {
			return $content;
		}
		
		if ( $embed_privacy->is_ignored_request ) {
			return $content;
		}
		
		// do nothing for ignored shortcodes
		if ( ! empty( $tag ) && \in_array( $tag, $embed_privacy->shortcode->get_ignored(), true ) ) {
			return $content;
		}
		
		// check content for already available embeds
		if ( ! $embed_privacy->has_embed && \str_contains( $content, '<div class="embed-privacy-overlay">' ) ) {
			$embed_privacy->has_embed = true;
		}
		
		/**
		 * Filter a custom replacement. If a non-empty string is returned,
		 * this string will be used as replacement.
		 * 
		 * @since	1.11.0
		 * 
		 * @param	string	$custom_replacement Current custom replacement
		 * @param	string	$content The original content
		 * @param	string	$tag The shortcode tag if called via do_shortcode
		 */
		$custom_replacement = \apply_filters( 'embed_privacy_custom_embed_replacement', '', $content, $tag );
		
		if ( ! empty( $custom_replacement ) && \is_string( $custom_replacement ) ) {
			return $custom_replacement;
		}
		
		$new_content = $content;
		$replacement = new Replacement( $new_content );
		$new_content = $replacement->get();
		
		while ( $new_content !== $content ) {
			$content = $new_content;
			$replacement = new Replacement( $new_content );
			$new_content = $replacement->get();
		}
		
		return $new_content;
	}
	
	/**
	 * Replace oembed embeds with a container and hide the embed with an HTML comment.
	 * 
	 * @param	string	$output The original output
	 * @param	string	$url The URL to the embed
	 * @param	array	$attributes Additional attributes of the embed
	 * @return	string The updated embed code
	 */
	public static function replace_oembed( $output, $url, array $attributes ) {
		$embed_privacy = Embed_Privacy::get_instance();
		
		// do nothing in admin
		if ( ! $embed_privacy->use_cache ) {
			return $output;
		}
		
		if ( $embed_privacy->is_ignored_request ) {
			return $output;
		}
		
		// ignore embeds without host (ie. relative URLs)
		if ( ! empty( $url ) && empty( \wp_parse_url( $url, \PHP_URL_HOST ) ) ) {
			return $output;
		}
		
		// check the current host
		// see: https://github.com/epiphyt/embed-privacy/issues/24
		if ( \str_contains( $url, \wp_parse_url( \home_url(), \PHP_URL_HOST ) ) ) {
			return $output;
		}
		
		$replacement = new Replacement( $output, $url );
		
		foreach ( $replacement->get_providers() as $provider ) {
			// make sure to only run once
			if ( \str_contains( $output, 'data-embed-provider="' . $provider->get_name() . '"' ) ) {
				return $output;
			}
			
			$embed_title = Oembed::get_title( $output );
			/* translators: embed title */
			$attributes['embed_title'] = ! empty( $embed_title ) ? $embed_title : '';
			$attributes['embed_url'] = $url;
			$attributes['is_oembed'] = true;
			$attributes['strip_newlines'] = true;
			
			// the default dimensions are useless
			// so ignore them if recognized as such
			$defaults = \wp_embed_defaults( $url );
			
			if (
				! empty( $attributes['height'] ) && $attributes['height'] === $defaults['height']
				&& ! empty( $attributes['width'] ) && $attributes['width'] === $defaults['width']
			) {
				unset( $attributes['height'], $attributes['width'] );
				
				$dimensions = Oembed::get_dimensions( $output );
				
				if ( ! empty( $dimensions ) ) {
					$attributes = \array_merge( $attributes, $dimensions );
				}
			}
			
			/**
			 * Filter a custom replacement. If a non-empty string is returned,
			 * this string will be used as replacement.
			 * 
			 * @since	1.11.0
			 * 
			 * @param	string									$custom_replacement Current custom replacement
			 * @param	string									$output The original output
			 * @param	\epiphyt\Embed_privacy\embed\Provider	$provider Current provider
			 * @param	string									$url The URL to the embed
			 * @param	array									$attributes Additional attributes of the embed
			 */
			$custom_replacement = \apply_filters( 'embed_privacy_custom_oembed_replacement', '', $output, $provider, $url, $attributes );
			
			if ( ! empty( $custom_replacement ) && \is_string( $custom_replacement ) ) {
				return $custom_replacement;
			}
			
			$output = $replacement->get( $attributes, $provider );
		}
		
		return $output;
	}
	
	/**
	 * Replace video shortcode embeds.
	 * 
	 * @param	string	$output Video shortcode HTML output
	 * @param	array	$attributes Array of video shortcode attributes
	 * @return	string Updated embed code
	 */
	public static function replace_video_shortcode( $output, array $attributes ) {
		$url = isset( $attributes['src'] ) ? $attributes['src'] : '';
		
		if ( empty( $url ) && ! empty( $attributes['mp4'] ) ) {
			$url = $attributes['mp4'];
		}
		else if ( empty( $url ) && ! empty( $attributes['m4v'] ) ) {
			$url = $attributes['m4v'];
		}
		else if ( empty( $url ) && ! empty( $attributes['webm'] ) ) {
			$url = $attributes['webm'];
		}
		else if ( empty( $url ) && ! empty( $attributes['ogv'] ) ) {
			$url = $attributes['ogv'];
		}
		else if ( empty( $url ) && ! empty( $attributes['flv'] ) ) {
			$url = $attributes['flv'];
		}
		
		// ignore relative URLs
		if ( empty( \wp_parse_url( $url, \PHP_URL_HOST ) ) ) {
			return $output;
		}
		
		return self::replace_oembed( $output, $url, $attributes );
	}
}
