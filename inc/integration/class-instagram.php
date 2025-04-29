<?php
namespace epiphyt\Embed_Privacy\integration;

use epiphyt\Embed_Privacy\embed\Replacement;
use epiphyt\Embed_Privacy\Embed_Privacy;

/**
 * Instagram integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.11.0
 */
final class Instagram {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'embed_privacy_overlay_replaced_content', [ self::class, 'replace_posts' ] );
	}
	
	/**
	 * Replace Instagram posts.
	 * 
	 * @param	string	$content Current replaced content
	 * @return	string Updated replaced content
	 */
	public static function replace_posts( $content ) {
		if ( ! \str_contains( $content, 'instagram.com/embed.js' ) ) {
			return $content;
		}
		
		\remove_filter( 'embed_privacy_overlay_replaced_content', [ self::class, 'replace_posts' ] );
		
		$attributes = [
			'additional_checks' => [
				[
					'attribute' => 'class',
					'compare' => '===',
					'type' => 'attribute',
					'value' => 'instagram-media',
				],
			],
			'assets' => [],
			'elements' => [
				'blockquote',
			],
			'regex' => '/<blockquote class="instagram-media"([^>]+)>([\S\s]*)instagram\.com\/embed\.js"><\/script>/',
		];
		
		$overlay = new Replacement( $content );
		$new_content = $overlay->get( $attributes );
		
		if ( $new_content !== $content ) {
			Embed_Privacy::get_instance()->has_embed = true;
			$content = $new_content;
		}
		
		return $content;
	}
}
