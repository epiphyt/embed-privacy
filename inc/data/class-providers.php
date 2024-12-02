<?php
namespace epiphyt\Embed_Privacy\data;

use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\Embed_Privacy;
use WP_Post;

/**
 * Embed provider related functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Providers {
	/**
	 * @var		\epiphyt\Embed_Privacy\data\Providers
	 */
	public static $instance;
	
	/**
	 * @var		\epiphyt\Embed_privacy\embed\Provider[][] List of embed providers
	 */
	private $list = [];
	
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'embed_privacy_provider_name', [ self::class, 'sanitize_name' ] );
	}
	
	/**
	 * Get an embed provider by its name.
	 * 
	 * @param	string	$name The name to search for
	 * @return	\epiphyt\Embed_privacy\embed\Provider The embed provider
	 */
	public function get_by_name( $name ) {
		$provider = new Provider();
		
		if ( empty( $name ) ) {
			return $provider;
		}
		
		$embed_providers = $this->get_list();
		$name = self::sanitize_name( $name );
		
		foreach ( $embed_providers as $embed_provider ) {
			if ( $embed_provider->get_name() !== $name ) {
				continue;
			}
			
			$provider = $embed_provider;
			break;
		}
		
		return $provider;
	}
	
	/**
	 * Get a provider by its post object.
	 * 
	 * @param	\WP_Post	$post Post object
	 * @return	\epiphyt\Embed_privacy\embed\Provider Embed provider instance
	 */
	public static function get_by_post( $post ) {
		return new Provider( $post );
	}
	
	/**
	 * Get a list of providers by their post objects.
	 * 
	 * @param	\WP_Post[]	$posts List of post objects
	 * @return	\epiphyt\Embed_privacy\embed\Provider[] List of embed provider instances
	 */
	public static function get_by_posts( $posts ) {
		return \array_map( [ self::class, 'get_by_post' ], $posts );
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Embed_Privacy\data\Providers The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Get a specific type of embeds.
	 * 
	 * For more information on the accepted arguments in $args, see the
	 * {@link https://developer.wordpress.org/reference/classes/wp_query/
	 * WP_Query} documentation in the Developer Handbook.
	 * 
	 * @param	string	$type The embed type
	 * @param	array	$args Additional arguments
	 * @return	\epiphyt\Embed_Privacy\embed\Provider[] A list of providers
	 */
	public function get_list( $type = 'all', $args = [] ) {
		if ( ! empty( $this->list ) && isset( $this->list[ $type ] ) ) {
			return $this->list[ $type ];
		}
		
		if ( $type === 'all' && isset( $this->list['custom'] ) && isset( $this->list['oembed'] ) ) {
			$this->list[ $type ] = \array_merge( $this->list['custom'], $this->list['oembed'] );
			
			return $this->list[ $type ];
		}
		
		if ( ! empty( $args ) ) {
			$hash = \hash( 'md5', \wp_json_encode( $args ) );
		}
		
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		switch ( $type ) {
			case 'custom':
				$custom_providers = \get_posts( \array_merge( [
					'meta_query' => [ // phpcs:ignore SlevomatCodingStandard.Arrays.DisallowPartiallyKeyed.DisallowedPartiallyKeyed
						'relation' => 'OR',
						[ // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found, Universal.Arrays.MixedArrayKeyTypes.ImplicitNumericKey
							'compare' => 'NOT EXISTS',
							'key' => 'is_system',
							'value' => 'yes',
						],
						[ // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found
							'compare' => '!=',
							'key' => 'is_system',
							'value' => 'yes',
						],
					],
					'no_found_rows' => true,
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
					'update_post_term_cache' => false,
				], $args ) );
				$google_provider = \get_posts( \array_merge( [
					'meta_key' => 'is_system',
					'meta_value' => 'yes',
					'name' => 'google-maps',
					'no_found_rows' => true,
					'post_type' => 'epi_embed',
					'update_post_term_cache' => false,
				], $args ) );
				
				if ( ! empty( $hash ) ) {
					$this->list[ $hash ] = self::get_by_posts( \array_merge( $custom_providers, $google_provider ) );
				}
				else {
					$this->list[ $type ] = self::get_by_posts( \array_merge( $custom_providers, $google_provider ) );
				}
				break;
			case 'oembed':
				$embed_providers = \get_posts( \array_merge( [
					'meta_key' => 'is_system',
					'meta_value' => 'yes',
					'no_found_rows' => true,
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
					'update_post_term_cache' => false,
				], $args ) );
				
				if ( ! empty( $hash ) ) {
					$this->list[ $hash ] = self::get_by_posts( $embed_providers );
				}
				else {
					$this->list[ $type ] = self::get_by_posts( $embed_providers );
				}
				break;
			case 'all':
			default:
				$embed_providers = \get_posts( \array_merge( [
					'no_found_rows' => true,
					'numberposts' => -1,
					'order' => 'ASC',
					'orderby' => 'post_title',
					'post_type' => 'epi_embed',
					'update_post_term_cache' => false,
				], $args ) );
				
				if ( ! empty( $hash ) ) {
					$this->list[ $hash ] = self::get_by_posts( $embed_providers );
				}
				else {
					$this->list['all'] = self::get_by_posts( $embed_providers );
				}
				break;
		}
		// phpcs:enable
		
		$identifier = ! empty( $hash ) && ! empty( $this->list[ $hash ] ) ? $hash : $type;
		
		/**
		 * Filter the list of providers of a specific identifier.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	array	$provider_list Current provider list
		 * @param	string	$identifier Current identifier
		 * @param	array	$global_list List with all providers of all identifiers
		 */
		$this->list[ $identifier ] = (array) \apply_filters( 'embed_privacy_provider_list', $this->list[ $identifier ], $identifier, $this->list );
		
		return $this->list[ $identifier ];
	}
	
	/**
	 * Check if a provider is always active.
	 * 
	 * @param	string	$provider The embed provider in lowercase
	 * @return	bool True if provider is always active, false otherwise
	 */
	public static function is_always_active( $provider ) {
		$cookie = Embed_Privacy::get_instance()->get_cookie();
		$provider = self::sanitize_name( $provider );
		
		return isset( $cookie->{$provider} ) && $cookie->{$provider} === true;
	}
	
	/**
	 * Whether the current provider is disabled.
	 * 
	 * @param	\WP_Post|null	$post Optional post object
	 * @return	bool Whether the current provider is disabled
	 */
	public static function is_disabled( $post = null ) {
		$post_id = null;
		
		if ( ! $post instanceof WP_Post ) {
			$post_id = \get_the_ID();
		}
		else {
			$post_id = $post->ID;
		}
		
		return \get_post_meta( $post_id, 'is_disabled', true ) === 'yes';
	}
	
	/**
	 * Sanitize the embed provider name.
	 * 
	 * @param	string	$name Current provider name
	 * @return	string Sanitized provider name
	 */
	public static function sanitize_name( $name ) {
		return \preg_replace( '/-\d+$/', '', \sanitize_title( \strtolower( $name ) ) );
	}
}
