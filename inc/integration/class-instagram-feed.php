<?php
namespace epiphyt\Embed_Privacy\integration;

/**
 * Instagram Feed integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.9
 */
final class Instagram_Feed {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'embed_privacy_should_replace_match', [ self::class, 'should_replace_match' ], 10, 3 );
	}
	
	/**
	 * Check the matched content whether it comes from Instagram Feed.
	 * 
	 * @param	bool									$should_replace Whether the replacement should take place
	 * @param	string									$matched_content Actual matched content
	 * @param	\epiphyt\Embed_privacy\embed\Provider	$provider Provider object
	 * @return	bool Whether the replacement should take place
	 */
	public static function should_replace_match( $should_replace, $matched_content, $provider ) {
		if ( ! $should_replace ) {
			return $should_replace;
		}
		
		if ( $provider->get_name() !== 'instagram' ) {
			return $should_replace;
		}
		
		return ! \str_contains( $matched_content, 'class="sbi_' );
	}
}
