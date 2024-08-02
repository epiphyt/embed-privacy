<?php
namespace epiphyt\Embed_Privacy\embed;

use DOMDocument;
use DOMElement;
use DOMNode;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\Provider as Provider_Functionality;
use WP_Post;

/**
 * Embed overlay related functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Overlay {
	/**
	 * @var		string Original content
	 */
	private $content = '';
	
	/**
	 * @var		\epiphyt\Embed_privacy\embed\Provider Provider of this overlay
	 */
	private $provider;
	
	/**
	 * @var		array List of replacements
	 */
	private $replacements = [];
	
	/**
	 * Overlay constructor
	 * 
	 * @param	string	$content Original embedded content
	 * @param	string	$url Embedded content URL
	 */
	public function __construct( $content, $url = '' ) {
		$this->content = $content;
		$this->set_provider( $content, $url );
	}
	
	/**
	 * Get the content with an overlay.
	 * 
	 * @param	array	$attributes Embed attributes
	 * @return	string Content with embeds replaced by an overlay
	 */
	public function get( array $attributes = [] ) {
		/**
		 * Filter the content after it has been replaced with an overlay.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	string	$content Replaced content
		 */
		$content = (string) \apply_filters( 'embed_privacy_overlay_replaced_content', $this->content );
		
		/**
		 * If set to true, unknown providers are not handled via Embed Privacy.
		 * 
		 * @since	1.5.0
		 * 
		 * @param	bool	$ignore_unknown Whether unknown providers should be ignored
		 * @param	string	$content The original content
		 */
		$ignore_unknown_providers = \apply_filters( 'embed_privacy_ignore_unknown_providers', false, $content );
		
		// get default external content
		// special case for youtube-nocookie.com as it is part of YouTube provider
		// and gets rewritten in Divi
		// see: https://github.com/epiphyt/embed-privacy/issues/69
		if (
			! $ignore_unknown_providers
			&& (
				! \str_contains( $content, 'youtube-nocookie.com' )
				|| ! Provider_Functionality::is_always_active( 'youtube' )
			)
		) {
			$attributes['check_always_active'] = true;
			$new_content = $this->replace_content( $content, $attributes );
			
			if ( $new_content !== $content ) {
				Embed_Privacy::get_instance()->has_embed = true;
				Embed_Privacy::get_instance()->frontend->print_assets();
				$content = $new_content;
			}
		}
		
		return $content;
	}
	
	/**
	 * Get the overlay provider.
	 * 
	 * @return	\epiphyt\Embed_privacy\embed\Provider Provider object
	 */
	public function get_provider() {
		return $this->provider;
	}
	
	/**
	 * Replace embedded content with an overlay.
	 * 
	 * @param	string	$content Content to replace embeds in
	 * @param	array	$attributes Additional attributes
	 * @return	string Updated content
	 */
	private function replace_content( $content, $attributes ) {
		if ( empty( $content ) ) {
			return $content;
		}
		
		if ( $this->provider === null ) {
			return $content;
		} 
		
		/**
		 * Filter whether to ignore this embed.
		 * 
		 * @since	1.9.0
		 * 
		 * @param	bool	$ignore_embed Whether to ignore this embed
		 * @param	string	$content The original content
		 * @param	string	$provider_title Embed provider title
		 * @param	string	$provider_name Embed provider name
		 * @param	array	$attributes Additional attributes
		 */
		$ignore_embed = (bool) \apply_filters( 'embed_privacy_ignore_embed', false, $content, $this->provider->get_title(), $this->provider->get_name(), $attributes );
		
		if ( $ignore_embed ) {
			return $content;
		}
		
		$attributes = \wp_parse_args( $attributes, [
			'additional_checks' => [],
			'check_always_active' => false,
			'element_attribute' => 'src',
			'elements' => [ 'embed', 'iframe', 'object' ],
			'height' => 0,
			'ignore_aspect_ratio' => false,
			'is_oembed' => false,
			'regex' => '',
			'strip_newlines' => ! \has_blocks( $content ),
			'width' => 0,
		] );
		
		if ( $attributes['is_oembed'] ) {
			return Template::get( $this->provider, $content, $attributes );
		}
		
		\libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . \str_replace( '%', '%_epi_', $content ) . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		$template_dom = new DOMDocument();
		// detect domain if WordPress is installed on a sub domain
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		
		if ( ! \filter_var( $host, \FILTER_VALIDATE_IP ) ) {
			$host_array = \explode( '.', \str_replace( 'www.', '', $host ) );
			$tld_count = \count( $host_array );
			
			if ( $tld_count >= 3 && strlen( $host_array[ $tld_count - 2 ] ) === 2 ) {
				$host = \implode( '.', \array_splice( $host_array, $tld_count - 3, 3 ) );
			}
			else if ( $tld_count >= 2 ) {
				$host = \implode( '.', \array_splice( $host_array, $tld_count - 2, $tld_count ) );
			}
		}
		
		foreach ( $attributes['elements'] as $tag ) {
			$replacements = [];
			
			if ( $tag === 'object' ) {
				$attributes['element_attribute'] = 'data';
			}
			
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				if ( ! Embed_Privacy::get_instance()->run_checks( $attributes['additional_checks'], $element ) ) {
					continue;
				}
				
				// ignore embeds from the same (sub-)domain
				if ( \preg_match( '/https?:\/\/(.*\.)?' . \preg_quote( $host, '/' ) . '/', $element->getAttribute( $attributes['element_attribute'] ) ) ) {
					continue;
				}
				
				if ( ! empty( $attributes['regex'] ) && ! \preg_match( $attributes['regex'], $element->getAttribute( $attributes['element_attribute'] ) ) ) {
					continue;
				}
				
				// providers need to be explicitly checked if they're always active
				// see https://github.com/epiphyt/embed-privacy/issues/115
				if ( $attributes['check_always_active'] && Provider_Functionality::is_always_active( $this->provider->get_name() ) ) {
					if ( ! empty( $attributes['assets'] ) ) {
						$content = Assets::get_static( $attributes['assets'], $content );
					}
					
					return $content;
				}
				
				if ( $this->provider->is_unknown() ) {
					$embedded_host = \wp_parse_url( $element->getAttribute( $attributes['element_attribute'] ), \PHP_URL_HOST );
					
					// embeds with relative paths have no host
					// and they are local by definition, so do nothing
					// see https://github.com/epiphyt/embed-privacy/issues/27
					if ( empty( $embedded_host ) ) {
						return $content;
					}
					
					$this->provider->set_title( $embedded_host );
					$this->provider->set_name( \sanitize_title( $embedded_host ) );
					
					// unknown providers need to be explicitly checked if they're always active
					// see https://github.com/epiphyt/embed-privacy/issues/115
					if ( $attributes['check_always_active'] && Provider_Functionality::is_always_active( $this->provider->get_name() ) ) {
						if ( ! empty( $attributes['assets'] ) ) {
							$content = Assets::get_static( $attributes['assets'], $content );
						}
						
						return $content;
					}
					
					// check URL for available provider
					foreach ( Provider_Functionality::get_instance()->get_list() as $provider ) {
						if ( $provider->is_matching( $element->getAttribute( $attributes['element_attribute'] ) ) && empty( $replacements ) ) {
							continue 2;
						}
					}
				}
				
				/* translators: embed title */
				$attributes['embed_title'] = $element->hasAttribute( 'title' ) ? $element->getAttribute( 'title' ) : '';
				$attributes['embed_url'] = $element->getAttribute( $attributes['element_attribute'] );
				$attributes['height'] = $element->hasAttribute( 'height' ) ? $element->getAttribute( 'height' ) : 0;
				$attributes['width'] = $element->hasAttribute( 'width' ) ? $element->getAttribute( 'width' ) : 0;
				
				// get overlay template as DOM element
				$template_dom->loadHTML(
					'<html><meta charset="utf-8">' . str_replace( '%', '%_epi_', Template::get( $this->provider, $dom->saveHTML( $element ), $attributes ) ) . '</html>',
					\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
				);
				$overlay = null;
				
				foreach ( $template_dom->getElementsByTagName( 'div' ) as $div ) {
					if ( stripos( $div->getAttribute( 'class' ), 'embed-privacy-container' ) !== false ) {
						$overlay = $div;
						break;
					}
				}
				
				// store the elements to replace (see regressive loop down below)
				if ( $overlay instanceof DOMNode || $overlay instanceof DOMElement ) {
					$replacements[] = [
						'element' => $element,
						'replace' => $dom->importNode( $overlay, true ),
					];
				}
				
				// reset embed provider name
				if ( $this->provider->is_unknown() ) {
					$embed_provider = '';
					$embed_provider_lowercase = '';
				}
			}
			
			if ( ! empty( $replacements ) ) {
				$this->replacements = \array_merge( $this->replacements, $replacements );
				Embed_Privacy::get_instance()->has_embed = true;
				$elements = $dom->getElementsByTagName( $tag );
				$i = $elements->length - 1;
				
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				// use regressive loop for replaceChild()
				// see: https://www.php.net/manual/en/domnode.replacechild.php#50500
				while ( $i > -1 ) {
					$element = $elements->item( $i );
					
					foreach ( $replacements as $replacement ) {
						if ( $replacement['element'] === $element ) {
							$element->parentNode->replaceChild( $replacement['replace'], $replacement['element'] );
						}
					}
					
					$i--;
				}
				
				$content = $dom->saveHTML( $dom->documentElement );
				// phpcs:enable
			}
		}
		
		\libxml_use_internal_errors( false );
		
		// embeds for other elements need to be handled manually
		// make sure to test before if the regex matches
		// see: https://github.com/epiphyt/embed-privacy/issues/26
		if (
			empty( $this->replacements )
			&& ! empty( $attributes['regex'] )
			&& ! $this->provider->is_unknown()
		) {
			$provider = Provider_Functionality::get_instance()->get_by_name( $embed_provider_lowercase );
			
			if (
				$provider instanceof WP_Post
				&& ! \get_post_meta( $provider->ID, 'is_system', true )
				&& \get_post_meta( $provider->ID, 'is_disabled', true ) !== 'yes'
			) {
				// extend regular expression to match the full element
				if ( \strpos( $attributes['regex'], '<' ) === false || \strpos( $attributes['regex'], '>' ) === false ) {
					$allowed_tags = [
						'blockquote',
						'div',
						'embed',
						'iframe',
						'object',
					];
					
					/**
					 * Filter allowed HTML tags in regular expressions.
					 * Only elements matching these tags get processed.
					 * 
					 * @since	1.6.0
					 * 
					 * @param	string[]	$allowed_tags The allowed tags
					 * @param	string		$embed_provider_lowercase The embed provider without spaces and in lowercase
					 * @return	array A list of allowed tags
					 */
					$allowed_tags = \apply_filters( 'embed_privacy_matcher_elements', $allowed_tags, $embed_provider_lowercase );
					
					$tags_regex = '(' . \implode( '|', \array_filter( $allowed_tags, function( $tag ) {
						return \preg_quote( $tag, '/' );
					} ) ) . ')';
					$attributes['regex'] = '/<' . $tags_regex . '([^"]*)"([^<]*)' . \trim( $attributes['regex'], '/' ) . '([^"]*)"([^>]*)(>(.*)<\/' . $tags_regex . ')?>/';
				}
				
				while ( \preg_match( $attributes['regex'], $content, $matches ) ) {
					$content = \preg_replace( $attributes['regex'], Template::get( $embed_provider, $embed_provider_lowercase, $matches[0], $attributes ), $content, 1 );
				}
			}
		}
		
		// decode to make sure there is nothing left encoded if replacements have been made
		// otherwise, content is untouched by DOMDocument, and we don't need a decoding
		// only required for WPBakery Page Builder
		if ( ! empty( $this->replacements ) && \str_contains( 'vc_row', $content ) ) {
			$content = \rawurldecode( $content );
		}
		
		// remove root element, see https://github.com/epiphyt/embed-privacy/issues/22
		return \str_replace(
			[
				'<html><meta charset="utf-8">',
				'</html>',
				'%_epi_',
			],
			[
				'',
				'',
				'%',
			],
			$content
		);
	}
	
	/**
	 * Set the provider for this overlay.
	 * 
	 * @param	string	$content Content to get the provider from
	 * @param	string	$url URL to the embedded content
	 */
	private function set_provider( $content, $url = '' ) {
		$providers = Provider_Functionality::get_instance()->get_list();
		
		foreach ( $providers as $provider ) {
			if ( $provider->is_matching( $content ) || $provider->is_matching( $url ) ) {
				$this->provider = $provider;
				break;
			}
		}
		
		// support unknown oEmbed provider
		// see https://github.com/epiphyt/embed-privacy/issues/89
		if ( $this->provider === null && ! empty( $url ) ) {
			$parsed_url = \wp_parse_url( $url );
			$provider = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
			$this->provider = new Provider();
			$this->provider->set_name( $provider );
			$this->provider->set_title( $provider );
		}
		
		/**
		 * Filter the overlay provider.
		 * 
		 * @param	epiphyt\Embed_Privacy\embed\Provider	$provider Current provider
		 * @param	string									$content Content to get the provider from
		 * @param	string									$url URL to the embedded content
		 */
		$this->provider = \apply_filters( 'embed_privacy_overlay_provider', $this->provider, $content, $url );
	}
}