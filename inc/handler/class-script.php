<?php
namespace epiphyt\Embed_Privacy\handler;

/**
 * Script handler.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.12.0
 */
final class Script {
	public static function enqueue_inline( $script ) {
		if ( ! $script instanceof \_WP_Dependency ) {
			return;
		}
		
		foreach ( [ 'before', 'after' ] as $position ) {
			if ( ! empty( $script->extra[ $position ][1] ) ) {
				\wp_add_inline_script( 'embed-privacy', $script->extra[ $position ][1], $position );
			}
		}
	}
	public static function get_as_asset( $script, $type = 'script' ) {
		if ( ! $script instanceof \_WP_Dependency ) {
			return [];
		}
		
		$assets = [
			[
				'handle' => $script->handle,
				'src' => $script->src,
				'type' => $type,
				'version' => $script->ver,
			],
		];
		
		foreach ( [ 'before', 'after' ] as $position ) {
			if ( ! empty( $script->extra[ $position ][1] ) ) {
				$assets[] = [
					'data' => $script->extra[ $position ][1],
					'is_raw' => true,
					'type' => 'inline',
				];
			}
		}
		
		return $assets;
	}
}
