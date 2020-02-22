=== Embed Privacy ===
Contributors: epiphyt, kittmedia, krafit
Tags: oembed, privacy, gutenberg
Requires at least: 4.7
Tested up to: 5.4
Requires PHP: 5.6
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed Privacy prevents loading of embedded external content and allows your site visitors to opt-in.

== Description ==

Content embedded from external sites such as YouTube or Twitter is loaded immediately when visitors access your site. Embed Privacy addresses this issue and prevents loading of these contents until the visitor decides to allow loading of external content.
But Embed Privacy not only protects your visitors privacy, but also makes your site load faster.

All embeds will be replaced by placeholders, ready for you to apply style as you wish. With only a couple of lines of CSS. 

By clicking on the placeholder the respective content will be reloaded.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/embed-privacy` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Embedded content will automatically be replaced by a placeholder and can be loaded on demand by your visitors. There are no additional settings.


== Frequently Asked Questions ==

= Can Embed Privacy keep external services from tracking me/my visitors? =

Yes. As long as you don't opt-in to load external content, you/your visitors can't be tracked by these services.

= Does Embed Privacy make embedding content privacy-friendly? =

The embedding process itself will be privacy-friendly with Embed Privacy. That means, that no third-party embed provider is able to track users without their explicit consent by clicking on the overlay to allow the embed to be loaded. However, to make sure everything is fine you need to expand your privacy policy for each embed provider you’re using or you want to use, because you need to specify, where data will be sent to and what happens to them.

= Does Embed Privacy support the Gutenberg editor? =

Sure thing! We enjoy playing with the new WordPress editor and developed Embed Privacy with Gutenberg in mind, the plugin will work no matter the editor you use.

= Which embeds are currently supported? =

We currently support all oEmbed providers known to WordPress core. Want to know about them? Here you go: Amazon Kindle (since WordPress 5.2), Animoto, Cloudup, CollegeHumor, DailyMotion, Facebook, Flickr, Funny Or Die, Hulu, Imgur, Instagram, Issuu, Kickstarter, Meetup, Mixcloud, Photobucket, Photobucket, Polldaddy.com, Reddit, ReverbNation, Scribd, Sketchfab, SlideShare, SmugMug, SoundCloud, Speaker Deck, Spotify, TikTok, TED, Tumblr, Twitter, VideoPres, Vimeo, WordPress.org, WordPress.tv, YouTube.

= Developers: How to use Embed Privacy’s methods for custom content? =

Since version 1.1.0 you can now use our mechanism for content we don’t support in our plugin. You can do it the following way:

```php
/**
 * Replace specific content with the Embed Privacy overlay of type 'google-maps'.
 * 
 * @param	string		$content The content to replace
 * @return	string The updated content
 */
function prefix_replace_content_with_overlay( $content ) {
	// check for Embed Privacy
	if ( ! class_exists( 'epiphyt\Embed_Privacy\Embed_Privacy' ) ) {
		return $content;
	}
	
	// get Embed Privacy instance
	$embed_privacy = epiphyt\Embed_Privacy\Embed_Privacy::get_instance();
	
	// check if provider is always active; if so, just return the content
	if ( ! $embed_privacy->is_always_active_provider( 'google-maps' ) ) {
		// replace the content with the overlay
		$content = $embed_privacy->get_output_template( 'Google Maps', 'google-maps', $content );
	}
	
	return $content;
}
```

= Who are you folks? =

We are [Epiphyt](https://epiph.yt/), your friendly neighborhood WordPress plugin shop from southern Germany.


== Changelog ==

= 1.1.0 =
* Added option to allow all embeds by one provider
* Added provider TikTok, introduced in WordPress 5.4
* Added support for Google Maps iframes
* Added URL rewrite to youtube-nocookie.com
* Added option to save user selection per embed provider
* Added provider logo to our placeholder
* Added option to filter our placeholders markup
* Added support for 'alignwide' and 'alignfull' Gutenberg classes
* Added support for using our embedding overlay mechanism for external developers
* Improved our placeholder markup to be actually semantic
* Changed .embed- classes to .embed-privacy-
* Fixed some embed providers that use custom z-index, which results in the embedded content being above the overlay
* Fixed typos

= 1.0.2 =
* Improved compatiblity with [Autoptimize](https://wordpress.org/plugins/autoptimize/)
* Improved compatiblity with [AMP](https://wordpress.org/plugins/amp/)
* Fix issue with Slideshare causing wrong (generic) placeholders

= 1.0.1 =
* Fixed support for PHP 5.6

= 1.0.0 =
* Initial release

== Upgrade Notice ==
