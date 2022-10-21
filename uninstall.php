<?php
namespace epiphyt\Embed_Privacy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function defined;
use function delete_option;
use function delete_site_option;
use function get_option;
use function get_posts;
use function get_sites;
use function is_multisite;
use function restore_current_blog;
use function rmdir;
use function switch_to_blog;
use function unlink;
use function wp_delete_post;
use const WP_CONTENT_DIR;

// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = [
	'embed_privacy_is_migrating',
	'embed_privacy_javascript_detection',
	'embed_privacy_local_tweets',
	'embed_privacy_migrate_version',
	'embed_privacy_migration_count',
	'embed_privacy_preserve_data_on_uninstall',
];

if ( is_multisite() ) {
	$sites = get_sites( [ 'number' => 99999 ] );
	
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		
		// do nothing if option says so
		if ( get_option( 'embed_privacy_preserve_data_on_uninstall' ) ) {
			continue;
		}
		
		delete_data();
		restore_current_blog();
	}
	
	// delete site options
	foreach ( $options as $option ) {
		delete_site_option( $option );
	}
}
else if ( ! get_option( 'embed_privacy_preserve_data_on_uninstall' ) ) {
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
		delete_option( $option );
	}
	
	// delete posts of custom post type
	$embeds = get_posts( [
		'numberposts' => -1,
		'post_status' => 'any',
		'post_type' => 'epi_embed',
	] );
	
	foreach ( $embeds as $embed ) {
		wp_delete_post( $embed->ID, true );
	}
	
	// delete thumbnail directory
	$directory = WP_CONTENT_DIR . '/uploads/embed-privacy/thumbnails';
	$iterator = new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS );
	$files = new RecursiveIteratorIterator( $iterator, RecursiveIteratorIterator::CHILD_FIRST );
	
	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getRealPath() );
		}
		else {
			unlink( $file->getRealPath() );
		}
	}
	
	rmdir( $directory );
}
