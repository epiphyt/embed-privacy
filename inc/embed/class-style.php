<?php
namespace epiphyt\Embed_Privacy\embed;

use WP_Theme_JSON_Resolver;

/**
 * Styles of an embed.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Style {
	/**
	 * @var		\epiphyt\Embed_Privacy\embed\Assets Assets object
	 */
	private $assets = null;
	
	/**
	 * @var		array List of CSS properties and values divided by elements
	 */
	private $styling = [];
	
	/**
	 * Construct the object.
	 * 
	 * @param	string			$provider Provider name
	 * @param	\WP_Post|null	$embed_post Settings of the embed provider
	 * @param	array			$attributes Additional embed attributes
	 */
	public function __construct( $provider, $embed_post = null, $attributes = [] ) {
		$this->assets = new Assets( $provider, $embed_post, $attributes );
		
		$this->register_from_assets();
		$this->register_from_attributes( $attributes );
	}
	
	/**
	 * Get the style for an element.
	 * 
	 * @param	string	$element Element to get the style for
	 * @return	string Style as CSS
	 */
	public function get( $element ) {
		$style = '';
		
		if ( empty( $this->styling[ $element ] ) ) {
			return $style;
		}
		
		foreach ( $this->styling[ $element ] as $property => $value ) {
			$style .= \sprintf( '%1$s: %2$s; ', $property, $value );
		}
		
		return \trim( $style );
	}
	
	/**
	 * Register a style for an element.
	 * 
	 * @param	string	$element Element the style is for
	 * @param	string	$property CSS property
	 * @param	string	$value CSS value
	 */
	public function register( $element, $property, $value ) {
		$this->styling[ $element ][ $property ] = $value;
	}
	
	/**
	 * Register style from embed assets.
	 */
	private function register_from_assets() {
		$background = $this->assets->get_thumbnail();
		$logo = $this->assets->get_logo();
		
		if ( empty( $background['path'] ) ) {
			$background = $this->assets->get_background();
		}
		
		if ( ! empty( $background['path'] ) ) {
			$this->styling['container']['background-image'] = \sprintf(
				'url(%1$s?ver=%2$s)',
				$background['url'],
				$background['version']
			);
		}
		
		if ( ! empty( $logo['path'] ) ) {
			$this->styling['logo']['background-image'] = \sprintf( 'url(%1$s?ver=%2$s)', $logo['url'], $logo['version'] );
		}
	}
	
	/**
	 * Register style from embed attributes.
	 * 
	 * @param	array	$attributes Embed attributes
	 */
	private function register_from_attributes( $attributes ) {
		if (
			! empty( $attributes['height'] )
			&& ! empty( $attributes['width'] )
			&& empty( $attributes['ignore_aspect_ratio'] )
		) {
			// if height is in percentage, we cannot determine the aspect ratio
			if ( \str_contains( $attributes['height'], '%' ) ) {
				$attributes['ignore_aspect_ratio'] = true;
			}
			
			// if width is in percentage, we need to use the content width
			// since we cannot determine the actual width
			if ( \str_contains( $attributes['width'], '%' ) ) {
				global $content_width;
				
				if ( $content_width === null ) {
					$theme_json_data = WP_Theme_JSON_Resolver::get_theme_data();
					
					if ( ! empty( $theme_json_data->get_settings()['layout']['contentSize'] ) ) {
						$content_width = (int) \preg_replace( '/[^\d]*/', '',  $theme_json_data->get_settings()['layout']['contentSize'] );
					}
				}
				
				if ( $content_width === null ) {
					$content_width = 800;
				}
				
				/**
				 * Filter the theme content width, which is used to determine the correct aspect ratio.
				 * 
				 * @since	1.10.0
				 * 
				 * @param	int		$content_width Current content width
				 */
				$content_width = (int) \apply_filters( 'embed_privacy_theme_content_width', (int) $content_width );
				
				$attributes['width'] = $content_width;
			}
			
			$this->register( 'container', 'aspect-ratio', $attributes['width'] . '/' . $attributes['height'] );
		}
	}
}
