<?php
namespace epiphyt\Embed_Privacy\integration;

use epiphyt\Embed_Privacy\embed\Replacement;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\handler\Script;

/**
 * Out of the Block: OpenStreetMap integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.12.0
 */
final class OOTB_OpenStreetMap {
	private $scripts = null;
	
	/**
	 * Initialize functionality.
	 */
	public function init() {
		// if ( ! \defined( 'JETPACK__VERSION' ) ) {
		// 	return;
		// }
		
		\add_action( 'wp_enqueue_scripts', [ $this, 'deregister_assets' ], 100 );
		\add_filter( 'embed_privacy_overlay_replaced_content', [ $this, 'replace_map' ] );
	}
	
	/**
	 * Deregister assets.
	 */
	public function deregister_assets() {
		\wp_dequeue_script( 'ootb-openstreetmap-view-script' );
		
		global $wp_scripts;
		
		$ootb_assets = new \OOTB\Assets();
		$ootb_assets->script_variables();
		Script::enqueue_inline( $wp_scripts->registered['ootb-openstreetmap-view-script'] );
	}
	
	/**
	 * Replace Open Street Map.
	 * 
	 * @param	string	$content Current replaced content
	 * @return	string Updated replaced content
	 */
	public function replace_map( $content ) {
		if ( ! \str_contains( $content, 'class="ootb-openstreetmap--map"' ) ) {
			return $content;
		}
		
		\remove_filter( 'embed_privacy_overlay_replaced_content', [ $this, 'replace_map' ] );
		
		global $wp_scripts;
		
		$attributes = [
			'additional_checks' => [
				[
					'attribute' => 'class',
					'compare' => '===',
					'type' => 'attribute',
					'value' => 'ootb-openstreetmap--map',
				],
			],
			'assets' => Script::get_as_asset( $wp_scripts->registered['ootb-openstreetmap-view-script'] ),
			'elements' => [
				'div',
			],
		];
		
		$overlay = new Replacement( $content );
		$new_content = $overlay->get( $attributes );
		
		if ( $new_content !== $content ) {
			Embed_Privacy::get_instance()->has_embed = true;
			$content = $new_content;
		}
		
		\add_filter( 'embed_privacy_overlay_replaced_content', [ $this, 'replace_map' ] );
		
		return $content;
	}
}
