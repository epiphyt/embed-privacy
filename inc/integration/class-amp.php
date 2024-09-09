<?php
namespace epiphyt\Embed_Privacy\integration;

/**
 * AMP integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Amp {
	/**
	 * Determine whether this is an AMP response.
	 * Note that this must only be called after the parse_query action.
	 * 
	 * @return	bool True if the current page is an AMP page, false otherwise
	 */
	public static function is_amp() {
		/** @disregard	P1010 */
		return \function_exists( 'is_amp_endpoint' ) && \is_amp_endpoint();
	}
}
