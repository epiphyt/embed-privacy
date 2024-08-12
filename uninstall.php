<?php
namespace epiphyt\Embed_Privacy;

use epiphyt\Embed_Privacy\thumbnail\Thumbnail;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

// if uninstall.php is not called by WordPress, die
if ( ! \defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$GLOBALS['options'] = [
	'embed_privacy_disable_link',
	'embed_privacy_download_thumbnails',
	'embed_privacy_is_migrating',
	'embed_privacy_javascript_detection',
	'embed_privacy_local_tweets',
	'embed_privacy_migrate_version',
	'embed_privacy_migration_count',
	'embed_privacy_preserve_data_on_uninstall',
];

if ( \is_multisite() ) {
	$sites = \get_sites( [
		'fields' => 'ids',
		'number' => 99999,
	] );
	
	foreach ( $sites as $site_blog_id ) {
		\switch_to_blog( $site_blog_id );
		
		// do nothing if option says so
		if ( \get_option( 'embed_privacy_preserve_data_on_uninstall' ) ) {
			continue;
		}
		
		delete_data();
		\restore_current_blog();
	}
	
	// delete site options
	foreach ( $GLOBALS['options'] as $option ) {
		\delete_site_option( $option );
	}
}
else if ( ! \get_option( 'embed_privacy_preserve_data_on_uninstall' ) ) {
	delete_data();
}

/**
 * Delete all data
 * 
 * @since	1.5.0
 */
function delete_data() {
	global $options;
	
	// delete options
	foreach ( $options as $option ) {
		\delete_option( $option );
	}
	
	// delete posts of custom post type
	$post_args = [
		'fields' => 'ids',
		'no_found_rows' => true,
		'post_status' => 'any',
		'post_type' => 'epi_embed',
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];
	$embeds = \get_posts( $post_args );
	
	while ( \count( $embeds ) ) {
		foreach ( $embeds as $embed_id ) {
			\wp_delete_post( $embed_id, true );
		}
		
		$embeds = \get_posts( $post_args );
	}
	
	$post_args = [
		'fields' => 'ids',
		'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			[
				'compare' => '!=',
				'compare_key' => 'LIKE',
				'key' => 'embed_privacy_thumbnail_',
				'value' => '',
			],
		],
		'no_found_rows' => true,
		'offset' => 0,
		'post_type' => 'any',
		'update_post_term_cache' => false,
	];
	$posts = \get_posts( $post_args );
	
	while ( \count( $posts ) ) {
		foreach ( $posts as $post_id ) {
			$metadata = \get_post_meta( $post_id );
			
			foreach ( $metadata as $meta_key => $meta_value ) {
				if ( ! \str_contains( $meta_key, 'embed_privacy_thumbnail_' ) ) {
					continue;
				}
				
				\delete_post_meta( $post_id, $meta_key );
			}
		}
		
		$post_args['offset'] += 5;
		$posts = \get_posts( $post_args );
	}
	
	require_once __DIR__ . '/inc/thumbnail/class-thumbnail.php';
	
	// delete thumbnail directory
	delete_directory( Thumbnail::get_directory()['base_dir'] );
	// delete old thumbnail directory
	delete_directory( WP_CONTENT_DIR . '/uploads/embed-privacy' );
}

/**
 * Delete a directory recursively.
 * 
 * @since	1.7.3
 * 
 * @param	string	$directory The directory to delete
 */
function delete_directory( $directory ) {
	if ( ! \file_exists( $directory ) ) {
		return;
	}
	
	$iterator = new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS );
	$files = new RecursiveIteratorIterator( $iterator, RecursiveIteratorIterator::CHILD_FIRST );
	
	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			\rmdir( $file->getRealPath() );
		}
		else {
			\unlink( $file->getRealPath() );
		}
	}
	
	\rmdir( $directory );
}
