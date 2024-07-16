<?php
namespace epiphyt\Embed_Privacy;

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
final class Provider {
	/**
	 * @var		\epiphyt\Embed_Privacy\Provider
	 */
	public static $instance;
	
	/**
	 * @var		\WP_Post[] List of embed providers
	 */
	private $list = [];
	
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'embed_privacy_provider_name', [ self::class, 'sanitize_title' ] );
	}
	
	/**
	 * Get an embed provider by its name.
	 * 
	 * @param	string	$name The name to search for
	 * @return	\WP_Post|null The embed or null
	 */
	public function get_by_name( $name ) {
		if ( empty( $name ) ) {
			return null;
		}
		
		$embed_providers = $this->get_list();
		$provider = null;
		$name = self::sanitize_title( $name );
		
		foreach ( $embed_providers as $embed_provider ) {
			if ( $embed_provider->post_name !== $name ) {
				continue;
			}
			
			$provider = $embed_provider;
			break;
		}
		
		return $provider;
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Embed_Privacy\Provider The single instance of this class
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
	 * @return	array A list of embeds
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
					'meta_query' => [
						'relation' => 'OR',
						[
							'compare' => 'NOT EXISTS',
							'key' => 'is_system',
							'value' => 'yes',
						],
						[
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
					$this->list[ $hash ] = \array_merge( $custom_providers, $google_provider );
				}
				else {
					$this->list[ $type ] = \array_merge( $custom_providers, $google_provider );
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
					$this->list[ $hash ] = $embed_providers;
				}
				else {
					$this->list[ $type ] = $embed_providers;
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
					$this->list[ $hash ] = $embed_providers;
				}
				else {
					$this->list['all'] = $embed_providers;
				}
				break;
		}
		// phpcs:enable
		
		if ( ! empty( $hash ) ) {
			return $this->list[ $hash ];
		}
		
		return $this->list[ $type ];
	}
	
	/**
	 * Check if a provider is always active.
	 * 
	 * @param	string		$provider The embed provider in lowercase
	 * @return	bool True if provider is always active, false otherwise
	 */
	public static function is_always_active( $provider ) {
		$javascript_detection = \get_option( 'embed_privacy_javascript_detection' );
		$provider = self::sanitize_title( $provider );
		
		if ( $javascript_detection ) {
			return false;
		}
		
		$cookie = Embed_Privacy::get_instance()->get_cookie();
		
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
	 * Sanitize the embed provider title.
	 * 
	 * @param	string	$title Current provider title
	 * @return	string Sanitized provider title
	 */
	public static function sanitize_title( $title ) {
		return \preg_replace( '/-\d+$/', '', \sanitize_title( \strtolower( $title ) ) );
	}
}
