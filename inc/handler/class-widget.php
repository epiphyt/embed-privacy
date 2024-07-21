<?php
namespace epiphyt\Embed_Privacy\handler;

use epiphyt\Embed_Privacy\Replacer;

/**
 * Widget handler.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Widget {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'embed_privacy_widget_output', [ Replacer::class, 'replace_embeds' ] );
	}
}
