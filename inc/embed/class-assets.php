<?php
namespace epiphyt\Embed_Privacy\embed;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\Embed_Privacy;
use WP_Post;

/**
 * Assets of an embed.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Assets {
	/**
	 * @var		string[] Background image asset data
	 */
	private $background = [
		'path' => null,
		'url' => null,
		'version' => null,
	];
	
	/**
	 * @var		bool Whether debug mode is enabled
	 */
	private $is_debug_mode = false;
	
	/**
	 * @var		string[] Logo asset data
	 */
	private $logo = [
		'path' => null,
		'url' => null,
		'version' => null,
	];
	
	/**
	 * @var		\epiphyt\Embed_Privacy\embed\Provider Provider object
	 */
	private $provider;
	
	/**
	 * @var		string[] Thumbnail image asset data
	 */
	private $thumbnail = [
		'path' => null,
		'url' => null,
		'version' => null,
	];
	
	/**
	 * Construct the object.
	 * 
	 * @since	1.11.0 Deprecated second parameter
	 * @since	1.11.0 First parameter must be a provider object
	 * 
	 * @param	string|\epiphyt\Embed_Privacy\embed\Provider	$provider Provider object
	 * @param	null											$deprecated Deprecated parameter
	 * @param	array											$attributes Additional embed attributes
	 */
	public function __construct( $provider, $deprecated = null, $attributes = [] ) {
		if ( \is_string( $provider ) ) {
			\_doing_it_wrong(
				__METHOD__,
				\sprintf(
					/* translators: parameter name */
					\esc_html__( 'Passing a string as parameter %s is deprecated.', 'embed-privacy' ),
					'$provider'
				),
				'1.11.0'
			);
			
			$provider = Providers::get_instance()->get_by_name( $provider );
		}
		
		if ( $deprecated !== null ) {
			\_deprecated_argument( __METHOD__, '1.11.0' );
		}
		
		$this->is_debug_mode = \defined( 'WP_DEBUG' ) && \WP_DEBUG || \defined( 'SCRIPT_DEBUG' ) && \SCRIPT_DEBUG;
		$this->provider = $provider;
		
		if ( $this->provider->get_post_object() instanceof WP_Post ) {
			$this->set_background();
		}
		
		$this->set_logo();
		$this->set_thumbnail( $attributes );
	}
	
	/**
	 * Get the background image asset data.
	 * 
	 * @return	string[] Background image asset data
	 */
	public function get_background() {
		return $this->background;
	}
	
	/**
	 * Get the logo asset data.
	 * 
	 * @return	string[] Logo asset data
	 */
	public function get_logo() {
		return $this->logo;
	}
	
	/**
	 * Get static assets.
	 * 
	 * @param	array	$assets List of assets
	 * @param	string	$provider Provider name
	 * @return	string Static assets as HTML
	 */
	public static function get_static( $assets, $provider = '' ) {
		$output = '';
		
		if ( ! empty( $provider ) ) {
			/**
			 * Filter the additional assets of an embed provider.
			 * 
			 * @since	1.4.5
			 * 
			 * @param	array	$assets List of embed assets
			 * @param	string	$provider The current embed provider in lowercase
			 */
			$args['assets'] = \apply_filters( "embed_privacy_assets_{$provider}", $assets, $provider );
		}
		
		if ( empty( $assets ) ) {
			return $output;
		}
		
		foreach ( \array_reverse( $assets ) as $asset ) {
			if ( empty( $asset['type'] ) ) {
				continue;
			}
			
			if ( $asset['type'] === 'script' ) {
				if ( empty( $asset['handle'] ) || empty( $asset['src'] ) ) {
					continue;
				}
				
				$output = '<script src="' . \esc_url( $asset['src'] ) . ( ! empty( $asset['version'] ) ? '?ver=' . \esc_attr( \rawurlencode( $asset['version'] ) ) : '' ) . '" id="' . \esc_attr( $asset['handle'] ) . '"></script>' . \PHP_EOL . $output; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			}
			else if ( $asset['type'] === 'inline' ) {
				if ( empty( $asset['data'] ) || empty( $asset['object_name'] ) ) {
					continue;
				}
				
				if ( \is_string( $asset['data'] ) ) {
					$data = \html_entity_decode( $asset['data'], \ENT_QUOTES, 'UTF-8' );
				}
				else {
					foreach ( (array) $asset['data'] as $key => $value ) {
						if ( ! \is_scalar( $value ) ) {
							continue;
						}
						
						$data[ $key ] = \html_entity_decode( (string) $value, \ENT_QUOTES, 'UTF-8' );
					}
				}
				$output = '<script>var ' . \esc_js( $asset['object_name'] ) . ' = ' . \wp_json_encode( $data ) . ';</script>' . \PHP_EOL . $output;
			}
		}
		
		return $output;
	}
	
	/**
	 * Get the thumbnail asset data.
	 * 
	 * @return	string[] Thumbnail asset data
	 */
	public function get_thumbnail() {
		return $this->thumbnail;
	}
	
	/**
	 * Set the background image asset data.
	 */
	private function set_background() {
		$background_id = \get_post_meta( $this->provider->get_post_object()->ID, 'background_image', true );
		
		if ( $background_id ) {
			$this->background['path'] = \get_attached_file( $background_id );
			$this->background['url'] = \wp_get_attachment_url( $background_id );
			$this->background['version'] = '';
		}
		
		/**
		 * Filter the path to the background image.
		 * 
		 * @param	string	$background_path The current background path
		 * @param	string	$provider The current embed provider in lowercase
		 */
		$this->background['path'] = \apply_filters( "embed_privacy_background_path_{$this->provider}", $this->background['path'], $this->provider );
		
		/**
		 * Filter the URL to the background image.
		 * 
		 * @param	string	$background_url The current background URL
		 * @param	string	$provider The current embed provider in lowercase
		 */
		$this->background['url'] = \apply_filters( "embed_privacy_background_url_{$this->provider}", $this->background['url'], $this->provider );
		
		if ( ! empty( $this->background['path'] ) && \file_exists( $this->background['path'] ) ) {
			$this->background['version'] = $this->is_debug_mode ? \filemtime( $this->background['path'] ) : \EMBED_PRIVACY_VERSION;
		}
	}
	
	/**
	 * Set the logo asset data.
	 */
	private function set_logo() {
		if ( \file_exists( \EPI_EMBED_PRIVACY_BASE . 'assets/images/embed-' . $this->provider . '.png' ) ) {
			$this->logo['path'] = \EPI_EMBED_PRIVACY_BASE . 'assets/images/embed-' . $this->provider . '.png';
			$this->logo['url'] = \EPI_EMBED_PRIVACY_URL . 'assets/images/embed-' . $this->provider . '.png';
			$this->logo['version'] = '';
		}
		
		if ( $this->provider->get_post_object() instanceof WP_Post ) {
			$thumbnail_id = \get_post_thumbnail_id( $this->provider->get_post_object() );
			
			if ( $thumbnail_id ) {
				$this->logo['path'] = \get_attached_file( $thumbnail_id );
				$this->logo['url'] = \get_the_post_thumbnail_url( $this->provider->get_post_object()->ID );
			}
		}
		
		/**
		 * Filter the path to the logo.
		 * 
		 * @param	string	$logo_path The current logo path
		 * @param	string	$provider The current embed provider in lowercase
		 */
		$this->logo['path'] = \apply_filters( "embed_privacy_logo_path_{$this->provider}", $this->logo['path'], $this->provider );
		
		/**
		 * Filter the URL to the logo.
		 * 
		 * @param	string	$logo_url The current logo URL
		 * @param	string	$provider The current embed provider in lowercase
		 */
		$this->logo['url'] = \apply_filters( "embed_privacy_logo_url_{$this->provider}", $this->logo['url'], $this->provider );
		
		if ( ! empty( $this->logo['path'] ) && \file_exists( $this->logo['path'] ) ) {
			$this->logo['version'] = $this->is_debug_mode ? \filemtime( $this->logo['path'] ) : \EMBED_PRIVACY_VERSION;
		}
	}
	
	/**
	 * Set the thumbnail image asset data.
	 * 
	 * @param	array	$attributes Embed attributes
	 */
	private function set_thumbnail( $attributes ) {
		if ( empty( $attributes['embed_url'] ) || ! \get_option( 'embed_privacy_download_thumbnails' ) ) {
			return;
		}
		
		$thumbnail = Embed_Privacy::get_instance()->thumbnail->get_data( \get_post(), $attributes['embed_url'] );
		$this->thumbnail = [
			'path' => $thumbnail['thumbnail_path'],
			'url' => $thumbnail['thumbnail_url'],
			'version' => '',
		];
		
		/**
		 * Filter the path to the thumbnail.
		 * 
		 * @param	string	$thumbnail_path The current thumbnail path
		 * @param	string	$provider The current embed provider in lowercase
		 */
		$this->thumbnail['path'] = \apply_filters( "embed_privacy_thumbnail_path_{$this->provider}", $this->thumbnail['path'], $this->provider );
		
		/**
		 * Filter the URL to the thumbnail.
		 * 
		 * @param	string	$thumbnail_url The current thumbnail URL
		 * @param	string	$provider The current embed provider in lowercase
		 */
		$this->thumbnail['url'] = \apply_filters( "embed_privacy_thumbnail_url_{$this->provider}", $this->thumbnail['url'], $this->provider );
		
		if ( ! empty( $this->thumbnail['path'] ) && \file_exists( $this->thumbnail['path'] ) ) {
			$this->thumbnail['version'] = $this->is_debug_mode ? \filemtime( $this->thumbnail['path'] ) : \EMBED_PRIVACY_VERSION;
		}
	}
}
