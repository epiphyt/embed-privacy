<?php
namespace epiphyt\Embed_Privacy\handler;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\data\Replacer;
use epiphyt\Embed_Privacy\Embed_Privacy;
use WP_Post;

/**
 * Post handler.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Post {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_filter( 'acf_the_content', [ Replacer::class, 'replace_embeds' ] );
		\add_filter( 'do_shortcode_tag', [ Replacer::class, 'replace_embeds' ], 10, 2 );
		\add_filter( 'embed_oembed_html', [ Replacer::class, 'replace_oembed' ], 10, 3 );
		\add_filter( 'render_block', [ Replacer::class, 'replace_embeds' ], 10, 2 );
		\add_filter( 'the_content', [ Replacer::class, 'replace_embeds' ] );
		\add_filter( 'wp_video_shortcode', [ Replacer::class, 'replace_video_shortcode' ], 10, 2 );
		\register_activation_hook( \EPI_EMBED_PRIVACY_FILE, [ self::class, 'clear_embed_cache' ] );
		\register_deactivation_hook( \EPI_EMBED_PRIVACY_FILE, [ self::class, 'clear_embed_cache' ] );
	}
	
	/**
	 * Embeds are cached in the postmeta database table and need to be removed
	 * whenever the plugin will be enabled or disabled.
	 */
	public static function clear_embed_cache() {
		global $wpdb;
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( \is_plugin_active_for_network( 'embed-privacy/embed-privacy.php' ) ) {
			// on networks we need to iterate through every site
			$sites = \get_sites( [
				'fields' => 'ids',
				'number' => 99999,
			] );
			
			foreach ( $sites as $blog_id ) {
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						"DELETE FROM	$wpdb->get_blog_prefix( $blog_id )postmeta
						WHERE			meta_key LIKE %s",
						// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						[ '%_oembed_%' ]
					)
				);
			}
		}
		else {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM	$wpdb->postmeta
					WHERE			meta_key LIKE %s",
					[ '%_oembed_%' ]
				)
			);
		}
		//phpcs:enable
	}
	
	/**
	 * List of ignored blocks that won't be replaced at all.
	 * 
	 * @since	1.12.0
	 * 
	 * @return	string[] List of ignored blocks
	 */
	public static function get_ignored_blocks() {
		$blocks = [];
		
		foreach ( \array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $block_name ) {
			if ( \strpos( $block_name, 'core/' ) === 0 && $block_name !== 'core/html' ) {
				$blocks[] = $block_name;
			}
		}
		
		/**
		 * List of ignored blocks, where no embed is possible.
		 * 
		 * @since	1.12.0
		 * 
		 * @param	string[]	$blocks List of ignored blocks
		 */
		$blocks = (array) \apply_filters( 'embed_privacy_ignored_blocks', $blocks );
		
		return $blocks;
	}
	
	/**
	 * Check if a post contains an embed.
	 * 
	 * @param	\WP_Post|int|null	$post A post object, post ID or null
	 * @return	bool True if a post contains an embed, false otherwise
	 */
	public static function has_embed( $post = null ) {
		if ( $post === null && \get_queried_object_id() ) {
			$post = \get_post( \get_queried_object_id() );
		}
		
		/**
		 * Allow overwriting the return value of has_embed().
		 * If set to anything other than null, this value will be returned.
		 * 
		 * @since	1.3.0
		 * 
		 * @param	null	$has_embed The default value
		 */
		$has_embed = \apply_filters( 'embed_privacy_has_embed', null );
		
		if ( $has_embed !== null ) {
			return $has_embed;
		}
		
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		
		if ( Embed_Privacy::get_instance()->has_embed ) {
			return true;
		}
		
		$embed_providers = Providers::get_instance()->get_list();
		
		// check post content
		foreach ( $embed_providers as $provider ) {
			if ( $provider->is_matching( $post->post_content ) ) {
				return true;
			}
		}
		
		return false;
	}
}
