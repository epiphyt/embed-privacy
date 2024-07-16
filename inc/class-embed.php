<?php
namespace epiphyt\Embed_Privacy;

use epiphyt\Embed_Privacy\embed\Provider;

/**
 * Embed related functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Embed {
	/**
	 * @var		\epiphyt\Embed_Privacy\embed\Provider
	 */
	public $provider;
	
	/**
	 * Embed constructor.
	 */
	public function __construct() {
		$this->provider = new Provider();
	}
	
	/**
	 * Initialize functionality.
	 */
	public function init() {
		$this->provider->init();
	}
}
