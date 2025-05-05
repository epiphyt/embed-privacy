<?php
namespace epiphyt\Embed_Privacy\embed;

use epiphyt\Embed_Privacy\data\Providers;
use WP_Post;

/**
 * Embed provider representation.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Provider {
	/**
	 * @var	int|null Background image ID
	 */
	private $background_image_id = null;
	
	/**
	 * @var		string Name of a content item
	 */
	private $content_name = '';
	
	/**
	 * @var	string The description
	 */
	private $description = '';
	
	/**
	 * @var	bool Whether the provider is disabled
	 */
	private $disabled = false;
	
	/**
	 * @var	string Provider name
	 */
	private $name = '';
	
	/**
	 * @var		\WP_Post|null Provider post object
	 */
	private $post_object = null;
	
	/**
	 * @var	string Regular expression pattern
	 */
	private $pattern = '';
	
	/**
	 * @var	string Privacy policy URL
	 */
	private $privacy_policy_url = '';
	
	/**
	 * @var		bool Whether the provider is a system provider
	 */
	private $system = false;
	
	/**
	 * @var	int|null Thumbnail ID
	 */
	private $thumbnail_id = null;
	
	/**
	 * @var	string Title
	 */
	private $title = '';
	
	/**
	 * @var		bool Whether the current provider is unknown
	 */
	private $unknown = false;
	
	/**
	 * Provider constructor
	 * 
	 * @param	\WP_Post	$provider_object Provider post object
	 */
	public function __construct( $provider_object = null ) {
		$this->set_post_object( $provider_object );
		
		if ( $provider_object instanceof WP_Post ) {
			$this->set_name( Providers::sanitize_name( $provider_object->post_name ) );
			$this->set_title( $provider_object->post_title );
			$this->set_is_system( \get_post_meta( $provider_object->ID, 'is_system', true ) );
			$this->set_is_disabled( Providers::is_disabled( $provider_object ) );
			$this->set_pattern( \get_post_meta( $provider_object->ID, 'regex_default', true ) );
			$this->set_description( $provider_object->post_content );
			$this->set_privacy_policy_url( \get_post_meta( $provider_object->ID, 'privacy_policy_url', true ) );
			$this->set_background_image_id( \get_post_meta( $provider_object->ID, 'background_image', true ) );
			$this->set_thumbnail_id( \get_post_thumbnail_id( $provider_object ) );
			$this->set_content_name( \get_post_meta( $provider_object->ID, 'content_item_name', true ) );
		}
		else {
			$this->set_is_unknown( true );
		}
	}
	
	/**
	 * String representation of the provider.
	 * 
	 * @since	1.11.0
	 * 
	 * @return	string Provider name
	 */
	public function __toString() {
		return $this->get_name();
	}
	
	/**
	 * Get the background image ID.
	 * 
	 * @return	int|null Background image ID or null
	 */
	public function get_background_image_id() {
		return $this->background_image_id;
	}
	
	/**
	 * Get the name of a content item.
	 * 
	 * @return	string The content name
	 */
	public function get_content_name() {
		return $this->content_name;
	}
	
	/**
	 * Get the description.
	 * 
	 * @return	string The description
	 */
	public function get_description() {
		return $this->description;
	}
	
	/**
	 * Get the name.
	 * 
	 * @return	string Provider name
	 */
	public function get_name() {
		return $this->name;
	}
	
	/**
	 * Get the pattern.
	 * 
	 * @return	string Regular expression pattern
	 */
	public function get_pattern() {
		return $this->pattern;
	}
	
	/**
	 * Get the post object.
	 * 
	 * @return	\WP_Post|null Post object or null
	 */
	public function get_post_object() {
		return $this->post_object;
	}
	
	/**
	 * Get the privacy policy URL.
	 * 
	 * @return	string Privacy policy URL
	 */
	public function get_privacy_policy_url() {
		return $this->privacy_policy_url;
	}
	
	/**
	 * Get the thumbnail ID.
	 * 
	 * @return	int|null Thumbnail ID or null
	 */
	public function get_thumbnail_id() {
		return $this->thumbnail_id;
	}
	
	/**
	 * Get the title.
	 * 
	 * @return	string Title
	 */
	public function get_title() {
		return $this->title;
	}
	
	/**
	 * Set the background image ID.
	 * 
	 * @param	int|null	$background_image_id Background image ID or null
	 */
	public function set_background_image_id( $background_image_id ) {
		$this->background_image_id = $background_image_id;
	}
	
	/**
	 * Whether the provider has a certain name.
	 * 
	 * @param	string	$name Name to check
	 * @return	bool Whether the provider has the name to check
	 */
	public function is( $name ) {
		return $name === $this->name || $name === $this->title;
	}
	
	/**
	 * Whether the provider is disabled or not.
	 * 
	 * @return	bool Whether the provider is disabled
	 */
	public function is_disabled() {
		return $this->disabled;
	}
	
	/**
	 * Whether the provider is a system provider or not.
	 * 
	 * @return	bool Whether the provider is a system provider
	 */
	public function is_system() {
		return $this->system;
	}
	
	/**
	 * Whether the provider is unknown or not.
	 * 
	 * @return	bool Whether the provider is unknown
	 */
	public function is_unknown() {
		return $this->unknown;
	}
	
	/**
	 * Whether the provider is matching the current content.
	 * 
	 * @param	string	$content Content to check
	 * @param	string	$pattern Optional alternative pattern
	 * @return	bool Whether the provider is matching the current content
	 */
	public function is_matching( $content, $pattern = '' ) {
		$used_pattern = $pattern ?: $this->pattern;
		
		return (bool) ! empty( $used_pattern ) && \preg_match( $used_pattern, $content );
	}
	
	/**
	 * Set the content item name.
	 * 
	 * @param	string	$content_name Content name
	 */
	public function set_content_name( $content_name ) {
		$this->content_name = $content_name;
	}
	
	/**
	 * Set the description.
	 * 
	 * @param	string	$description Description
	 */
	public function set_description( $description ) {
		$this->description = $description;
	}
	
	/**
	 * Set the disabled state.
	 * 
	 * @param	bool	$disabled Whether this provider is disabled
	 */
	public function set_is_disabled( $disabled ) {
		$this->disabled = $disabled;
	}
	
	/**
	 * Set the system state.
	 * 
	 * @param	bool	$system Whether this provider is a system provider
	 */
	public function set_is_system( $system ) {
		$this->system = (bool) $system;
	}
	
	/**
	 * Set the unknown state.
	 * 
	 * @param	bool	$unknown Whether this provider is unknown
	 */
	public function set_is_unknown( $unknown ) {
		$this->unknown = (bool) $unknown;
	}
	
	/**
	 * Set the name.
	 * 
	 * @param	string	$name Name
	 */
	public function set_name( $name ) {
		/**
		 * Filter the embed provider name.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	string	$name Embed provider name
		 * @param	string	$provider Embed provider
		 */
		$name = \apply_filters( 'embed_privacy_provider_name', $name, $this );
		
		$this->name = $name;
	}
	
	/**
	 * Set the pattern.
	 * 
	 * @param	string	$pattern Regular expression pattern
	 */
	public function set_pattern( $pattern ) {
		$this->pattern = \trim( $pattern, '/' );
		
		if ( ! empty( $this->pattern ) ) {
			$this->pattern = '/' . $this->pattern . '/';
		}
	}
	
	/**
	 * Set the post object.
	 * 
	 * @param	string	$post_object Post object
	 */
	public function set_post_object( $post_object ) {
		$this->post_object = $post_object;
	}
	
	/**
	 * Set the privacy policy URL.
	 * 
	 * @param	string	$privacy_policy_url URL to the privacy policy
	 */
	public function set_privacy_policy_url( $privacy_policy_url ) {
		$this->privacy_policy_url = $privacy_policy_url;
	}
	
	/**
	 * Set the thumbnail_id.
	 * 
	 * @param	int|null	$thumbnail_id Thumbnail ID or null
	 */
	public function set_thumbnail_id( $thumbnail_id ) {
		$this->thumbnail_id = $thumbnail_id;
	}
	
	/**
	 * Set the title.
	 * 
	 * @param	string	$title Title
	 */
	public function set_title( $title ) {
		$this->title = $title;
	}
}
