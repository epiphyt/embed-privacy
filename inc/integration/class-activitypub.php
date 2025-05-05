<?php
namespace epiphyt\Embed_Privacy\integration;

use Activitypub\Signature;
use DateTimeImmutable;
use epiphyt\Embed_Privacy\data\Embed_Cache;

/**
 * ActivityPub integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Activitypub {
	/**
	 * @var		string Local toot template
	 */
	private static $toot_template = '<blockquote class="embed-privacy__toot">
		%1$s
		<cite class="embed-privacy__toot--meta">
			<span class="embed-privacy__toot--author-meta"><a href="%3$s">%2$s</a></span>
			<span class="embed-privacy__toot--date-meta"><a href="%5$s">%4$s</a></span>
		</cite>
	</blockquote>';
	
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'pre_oembed_result', [ self::class, 'maybe_set_cache' ], 9, 2 );
		\add_filter( 'embed_privacy_custom_oembed_replacement', [ self::class, 'maybe_set_local_toot' ], 10, 4 );
		\add_filter( 'embed_privacy_is_ignored_request', [ self::class, 'set_ignored_request' ] );
	}
	
	/**
	 * Get remote embed data.
	 * 
	 * @param	string	$url URL to retrieve embed data from
	 * @return	?\stdClass JSON object or null
	 */
	private static function get_remote_embed_data( $url ) {
		$date = \gmdate( 'D, d M Y H:i:s T' );
		$headers = [
			'Accept' => 'application/activity+json',
			'Content-Type' => 'application/activity+json',
			'Date' => $date,
		];
		
		if ( \method_exists( '\Activitypub\Signature', 'generate_signature' ) ) {
			$headers['Signature'] = Signature::generate_signature( -1, 'get', $url, $date );
		}
		
		$request = \wp_safe_remote_get(
			$url,
			[
				'headers' => $headers,
			]
		);
		
		if ( \is_wp_error( $request ) ) {
			return null;
		}
		
		$response = \wp_remote_retrieve_body( $request );
		$json = \json_decode( $response );
		
		if ( ! $json ) {
			return null;
		}
		
		return $json;
	}
	
	/**
	 * Get a local toot.
	 * 
	 * @param	\stdClass	$data ActivityPub response object
	 * @return	string Local toot markup
	 */
	private static function get_local_toot( $data ) {
		$date = new DateTimeImmutable( $data->published );
		$date = $date->setTimezone( \wp_timezone() );
		
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return \sprintf(
			self::$toot_template,
			$data->content,
			self::get_username_from_attributed_to( $data->attributedTo ),
			$data->attributedTo,
			$date->format( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ) ),
			$data->url
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
	
	/**
	 * Extract the username from the 'attributedTo' field.
	 * 
	 * @param	string	$attributed_to Content of the 'attributedTo' field
	 * @return	string Username in Webfinger format
	 */
	private static function get_username_from_attributed_to( $attributed_to ) {
		$parts = \wp_parse_url( $attributed_to );
		$username = '';
		
		if ( isset( $parts['path'] ) ) {
			if ( \strpos( $parts['path'], '/users/' ) === 0 ) {
				$username = '@' . \str_replace( '/users/', '', $parts['path'] );
			}
			else if ( \strpos( $parts['path'], '/author/' ) === 0 ) {
				$username = '@' . \str_replace( '/author/', '', $parts['path'] );
			}
			
			$username = \rtrim( $username, '/' );
		}
		
		if ( isset( $parts['host'] ) ) {
			$username .= '@' . $parts['host'];
		}
		
		return $username;
	}
	
	/**
	 * Check, whether the current content is a valid ActivityPub embed.
	 * 
	 * @param	mixed	$content Given content
	 * @return	bool Whether the current content is a valid ActivityPub embed
	 */
	public static function is_valid_embed( $content ) {
		if ( ! \is_object( $content ) ) {
			return false;
		}
		
		$supported_types = [
			'Article',
			'Document',
			'Event',
			'Note',
			'Page',
			'Tombstone',
		];
		
		$has_context = isset( $content->{'@context'} ) && $content->{'@context'}[0] === 'https://www.w3.org/ns/activitystreams';
		$is_type = \in_array( $content->type, $supported_types, true );
		
		return $has_context && $is_type;
	}
	
	/**
	 * Maybe set a cache for an ActivityPub embed.
	 * Creates a cache entry only if there is no oEmbed result yet.
	 * 
	 * @param	?string	$result Current oEmbed result
	 * @param	string	$url Current oEmbed URL
	 * @return	string|false|null oEmbed result
	 */
	public static function maybe_set_cache( $result, $url ) {
		if ( ! \get_option( 'embed_privacy_local_activitypub_posts' ) ) {
			return $result;
		}
		
		if ( $result !== null ) {
			return $result;
		}
		
		$cache = Embed_Cache::get( $url );
		
		if ( $cache === false ) {
			$cache = self::get_remote_embed_data( $url );
			
			Embed_Cache::set( $url, $cache );
		}
		
		return $result;
	}
	
	/**
	 * Set a local toot if it's a valid ActivityPub embed.
	 * 
	 * @param	string									$custom_replacement Current custom replacement
	 * @param	string									$content The original content
	 * @param	\epiphyt\Embed_privacy\embed\Provider	$provider Current provider
	 * @param	string									$url Embed URL
	 * @return	string Local toot or original embed
	 */
	public static function maybe_set_local_toot( $custom_replacement, $content, $provider, $url ) {
		if ( ! \get_option( 'embed_privacy_local_activitypub_posts' ) ) {
			return $custom_replacement;
		}
		
		$cache = Embed_Cache::get( $url );
		
		if ( ! self::is_valid_embed( $cache ) ) {
			return $custom_replacement;
		}
		
		return self::get_local_toot( $cache );
	}
	
	/**
	 * Set whether the current request is an ActivityPub request and thus should be ignored.
	 * Return the unaltered value if it's already ignored.
	 * 
	 * @param	bool	$is_ignored Whether the current request is ignored
	 * @return	bool Whether the current request is ignored
	 */
	public static function set_ignored_request( $is_ignored ) {
		if ( $is_ignored ) {
			return $is_ignored;
		}
		
		return \function_exists( 'Activitypub\is_activitypub_request' ) && \Activitypub\is_activitypub_request();
	}
}
