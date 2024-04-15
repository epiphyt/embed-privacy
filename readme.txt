=== Embed Privacy ===
Contributors: epiphyt, kittmedia, krafit
Tags: oembed, privacy, gutenberg
Requires at least: 5.9
Stable tag: 1.8.1
Tested up to: 6.5
Requires PHP: 5.6
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed Privacy prevents the loading of embedded external content and allows your site visitors to opt-in.

== Description ==

Content embedded from external sites such as YouTube or Twitter is loaded immediately when visitors access your site. Embed Privacy addresses this issue and prevents the loading of these contents until the visitor decides to allow loading of external content.
But Embed Privacy not only protects your visitor's privacy but also makes your site load faster.

All embeds will be replaced by placeholders, ready for you to apply style as you wish. With only a couple of lines of CSS. 

By clicking on the placeholder the respective content will be reloaded.

**Note: This plugins requires the PHP extension ["Document Object Model" (php-dom)](https://www.php.net/manual/en/book.dom.php).**

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/embed-privacy` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Embedded content will automatically be replaced by a placeholder and can be loaded on demand by your visitors. There are no additional settings.
1. To allow users to opt-out of embed providers that they set to always active, place the shortcode `[embed_privacy_opt_out]` into your privacy policy.


== Frequently Asked Questions ==

= Can Embed Privacy keep external services from tracking me/my visitors? =

Yes. As long as you don't opt in to load external content, you/your visitors can't be tracked by these services.

= Does Embed Privacy make embedding content privacy-friendly? =

The embedding process itself will be privacy-friendly with Embed Privacy. That means, that no third-party embed provider can track users without their explicit consent by clicking on the overlay to allow the embed to be loaded. However, to make sure everything is fine you need to expand your privacy policy for each embed provider you’re using or you want to use because you need to specify, where data will be sent to and what happens to them.

= Does Embed Privacy support the Gutenberg editor? =

Sure thing! We enjoy playing with the new WordPress editor and developed Embed Privacy with Gutenberg in mind, the plugin will work no matter the editor you use.

= Which embeds are currently supported? =

We currently support all oEmbed providers known to WordPress core by default. Want to know about them? Here you go:

* Amazon Kindle
* Anghami
* Animoto
* Cloudup
* DailyMotion
* Facebook
* Flickr
* Funny Or Die
* Imgur
* Instagram
* Issuu
* Kickstarter
* Meetup
* Mixcloud
* Photobucket
* Pocket Casts
* Polldaddy.com
* Reddit
* ReverbNation
* Scribd
* Sketchfab
* SlideShare
* SmugMug
* SoundCloud
* Speaker Deck
* Spotify
* TikTok
* TED
* Tumblr
* Twitter
* VideoPress
* Vimeo
* WordPress.org
* WordPress.tv
* YouTube

We also support Google Maps via iframe and the plugins Maps Marker, Maps Marker Pro and Shortcodes Ultimate.

Since version 1.2.0, you can also add custom embed providers by going to **Settings > Embed Privacy > Manage embeds**. Here you can also modify any existing embed provider, change its logo, add a background image, change the text displaying on the embed or disable the embed provider entirely.

= Can Embed Privacy automatically download thumbnails of the embedded content? =

Yes! Since version 1.5.0, Embed Privacy supports downloading and displaying thumbnails in posts for SlideShare, Vimeo and YouTube as background of Embed Privacy’s overlay.

= Developers: How to use Embed Privacy’s methods for custom content? =

Since version 1.1.0 you can now use our mechanism for content we don’t support in our plugin. You can do it the following way:

`
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
		// enqueue assets
		$embed_privacy->print_assets();
	}
	
	return $content;
}
`

= Can users opt-out of already opted in embed providers? =

Yes! You can use the shortcode `[embed_privacy_opt_out]` to add a list of embed providers anywhere you want (recommendation: add it to your privacy policy) to allow your users to opt-out.

= What parameters can be used in the shortcode? =

The shortcode `[embed_privacy_opt_out]` can be used to let users opt-out of embed providers that have been set to be always active by the user. It can have the following attributes:

<code>headline</code> – Add a custom headline (default: Embed providers)

`
[embed_privacy_opt_out headline="My custom headline"]
`

<code>subline</code> – Add a custom subline (default: Enable or disable embed providers globally. By enabling a provider, its embedded content will be displayed directly on every page without asking you anymore.)

`
[embed_privacy_opt_out subline="My custom subline"]
`

<code>show_all</code> – Whether to show all available embed providers or just the ones the user opted in (default: false)

`
[embed_privacy_opt_out show_all="1"]
`

You can also combine all of these attributes:

`
[embed_privacy_opt_out headline="My custom headline" subline="My custom subline" show_all="1"]
`

= Is this plugin compatible with my caching plugin? =

If you’re using a caching plugin, make sure you enable the "JavaScript detection for active providers" in **Settings > Embed Privacy > JavaScript detection**. Then, the plugin is fully compatible with your caching plugin.

= How can Embed Privacy be extended? =

Check out our documentation: [https://epiph.yt/en/embed-privacy/documentation/](https://epiph.yt/en/embed-privacy/documentation/)

= Who are you, folks? =

We are [Epiphyt](https://epiph.yt/), your friendly neighborhood WordPress plugin shop from southern Germany.


== Changelog ==

= 1.8.1 =
* Fixed the output of the opt-out shortcode (thanks wpdabh for reporting)

= 1.8.0 =
* Added support for Anghami
* Added filter to prevent thumbnail orphan deletion
* Added check for PHP DOM extension
* Improved loading embed providers

= 1.7.3 =
* Improved compatibility with Advanced Custom Fields
* Fixed compatibility with PHP 8.2
* Fixed a potential PHP warning if a post does not exist while checking permissions
* Fixed functionality of the opt-out shortcode if the current website uses a non-standard port
* Fixed replacing only the necessary part of embedded contents for custom embeds
* Fixed replacing all occurrences of a custom embed in the current content
* Fixed multiple unnecessary database queries in migration before the actual check whether a migration is necessary
* Fixed downloading the thumbnail multiple times when the same embed is embedded multiple times
* Fixed deleted thumbnails if they are only in content of Advanced Custom Fields
* Fixed a potential security issue regarding disclosing absolute paths in thumbnail URLs (special thanks to [@kraftner](https://profiles.wordpress.org/kraftner/))
* Fixed aspect ratio generation if width or height contains a percentage sign
* Fixed thumbnail positioning (it's now horizontally and vertically centered)
* Fixed HTML output of the opt-out shortcode

= 1.7.2 =
* Fixed getting the URL of video shortcodes properly to not block content from the same (sub)domain

= 1.7.1 =
* Improved Google Maps height in Kadence Blocks
* Fixed a JavaScript error if jQuery is not initialized
* Fixed potential PHP warning regarding an undefined variable
* Fixed potential PHP warning regarding an undefined hostname while retrieving the embed URL

= 1.7.0 =
* Added support for crowdsignal.net
* Added support for classic video shortcode/widget
* Added support for Slideshare thumbnails (thanks to [@Lazza](https://github.com/Lazza))
* Added support for custom thumbnail generation via filter
* Fixed hiding thumbnails after embedded content has been enabled
* Fixed aspect ratio for embeds without proper dimension information

= 1.6.5 =
* Fixed aspect ratio for non-default content width themes
* Fixed handling of the % character

= 1.6.4 =
* Fixed aspect ratio for many oEmbeds

= 1.6.3 =
* Fixed replacing some oEmbeds
* Fixed potential problems while retrieving a single embed provider
* Fixed deleting post metadata of embed providers while editing them via WP-CLI

= 1.6.2 =
* Restore displaying YouTube thumbnails

= 1.6.1 =
* Updated required WordPress version to 5.0
* Fixed text links to youtube.com
* Fixed Twitter embeds

= 1.6.0 =
* Added automatic detection of aspect ratio if given by the embed
* Added ability to work with HTML of regular oEmbed providers
* Added filter to ignore shortcodes (see [documentation](https://epiph.yt/en/embed-privacy/documentation/#embed_privacy_ignored_shortcodes))
* Added matching links to the new documentation at [https://epiph.yt/en/embed-privacy/documentation/](https://epiph.yt/en/embed-privacy/documentation/)
* Improved handling of matching non-standard elements (embed, iframe, object, see [documentation](https://epiph.yt/en/embed-privacy/documentation/#regex-pattern))
* Fixed Google Maps regex pattern after installation
* Fixed behavior of the opt-out shortcode with enabled page caching
* Fixed line-height of custom embeds in Elementor
* General code improvements

= 1.5.1 =
* Fixed storing and displaying video thumbnails from Vimeo that are embedded using the domain player.vimeo.com
* Fixed unnecessary line breaks within the classic editor
* Fixed displaying the overlay if an embed of a known embed provider followed an embed of an unknown embed provider
* Fixed uninstallation issues

= 1.5.0 =
* Added support for embed provider Pocket Casts
* Added support for Maps Marker and Maps Marker Pro
* Added support for Google Maps in Shortcodes Ultimate
* Added support to automatically download and display a thumbnail of the embed for Vimeo and YouTube (only in posts)
* Added support to re-initiate the database migration and display an error message if it has been failed at least three times
* Added support for Polylang and Polylang Pro
* Added a new filter to allow stopping Embed Privacy from handling unknown embeds
* Added option to disable the direct link in the overlay
* Updated logos for Reddit, SmugMug and SoundCloud for better readability
* Use the plugin version as parameter for assets to allow better caching and prevent problems with some setups
* General code improvements
* Fixed a JavaScript error

= 1.4.8 =
* Fixed an issue with always active providers being cached by page caching plugins
* Fixed printing inline JavaScript only once

= 1.4.7 =
* Fixed displaying embeds on mobile devices while using Divi
* Fixed the output of assets
* Improved regular expression for Google Maps

= 1.4.6 =
* Fixed an issue where JavaScript assets may be missing for the opt-out shortcode

= 1.4.5 =
* Fixed an issue with always enable an unknown embed provider
* Fixed issues with missing JavaScript for Facebook embed of Jetpack

= 1.4.4 =
* Fixed a potential encoding issue
* Fixed issues with Facebook embed of Jetpack
* Fixed issues with registering assets

= 1.4.3 =
* Fixed a problem that prevents embed fields from being stored

= 1.4.2 =
* Fixed an expired link during plugin activation and creating a new embed provider

= 1.4.1 =
* Fixed invalid HTML by changing the accessibility behavior (it's now a separate button)
* Fixed potential empty link titles in other locales then English
* Fixed potential unwanted URL encoding in the content after Embed Privacy replaced an embed

= 1.4.0 =
* Added support for Pinterest and Wolfram Cloud
* Improved migrations to fix duplicate providers or performance problems in multisite installations
* Embeds can now be enabled via keyboard
* Added a link to the original content on the bottom right overlay corner
* Added support for caching the opt-out shortcode
* Clicking on the "always enable content of X" enables all embeds of this provider on the current page immediately
* Improved markup of local tweets
* Added additional class to checkbox paragraph, thanks to @florianbrinkmann
* System providers cannot be deleted anymore
* Fixed oEmbed provider name if the provider is unknown
* Fix enqueuing assets for unknown embed providers

= 1.3.6 =
* Fixed enabling multiple YouTube videos in Elementor
* Fixed displaying content if the page contains an always active provider

= 1.3.5 =
* Fixed functionality in Elementor
* Fixed custom embed provider output
* Fixed replacing content in shortcodes multiple times
* Fixed checking for theme/template

= 1.3.4 =
* Fixed deleted meta fields if you put an embed provider in trash and restore it afterwards
* Fixed Embed Privacy sometimes trying to replace an embed twice
* Fixed an error that may occur if Embed Privacy tries to replace an embed that doesn't need to be replaced
* Fixed checking for local domain if WordPress itself is located in a sub-domain
* Fixed always enable YouTube within Divi
* Fixed overlay line height in Divi

= 1.3.3 =
* Improved mobile design for responsive embeds
* Fixed centering for non-responsive embeds
* Fixed disabled embed content from preventing interactions with the text below the overlay

= 1.3.2 =
* Fixed general activation error

= 1.3.1 =
* Fixed a fatal error on uninstallation on non-multisite
* Fixed activating via WP-CLI
* Fixed deleting an option on uninstallation

= 1.3.0 =
* Added local tweets without overlay
* Added option to preserve data on uninstall
* Added compatibility with theme Astra
* Added filter `embed_privacy_markup` for filtering the whole markup of an embed overlay
* Added proper support for embeds on the current domain
* Added support for embeds on other elements than `embed`, `iframe` and `object`
* Enqueue assets only if needed
* Removed images from media (which had been added in version 1.2.0) and use fallback images for default embed providers
* Improved regular expression for Google Maps
* Improved texts for clarity
* Fixed visibility of custom post type
* Fixed network-wide activation
* Fixed clearing oEmbed cache

= 1.2.2 =
* Added a check if a migration is already running
* Fixed a bug where the page markup could be changed unexpectedly
* `<object>` elements are now replaced correctly
* Added a missing textdomain to a string
* Excluded local embeds (with the same domain)
* Fixed Amazon Kindle regex being too greedy

= 1.2.1 =
* Fixed a bug where the page markup could be changed unexpectedly
* Fixed a warning if an embed provider has no regular expressions
* Improved migrations of embed provider metadata to make sure they have been added to the database

= 1.2.0 =
* Added support for managing embeds (add/remove/edit/disable)
* Added support for caching plugins by adding a JavaScript detection for always active embed providers
* Added CSS classes that indicate the current state of the embed (`is-disabled`/`is-enabled`)
* Added shortcode `[embed_privacy_opt_out]` to allow users to opt-out/in
* Fixed responsive design if the embed added an own width

= 1.1.3 =
* Changed provider name from Polldaddy to Crowdsignal
* Removed provider Hulu

= 1.1.2 =
* Fixed a possible difference in the used class name of the embed provider in HTML and CSS

= 1.1.1 =
* Removed provider CollegeHumor
* Fixed a bug with the automatic addition of paragraphs

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
* Improved compatibility with [Autoptimize](https://wordpress.org/plugins/autoptimize/)
* Improved compatibility with [AMP](https://wordpress.org/plugins/amp/)
* Fix issue with Slideshare causing wrong (generic) placeholders

= 1.0.1 =
* Fixed support for PHP 5.6

= 1.0.0 =
* Initial release

== Upgrade Notice ==

== Screenshots ==
1. Add embeds using the classic editor or the block editor's embed blocks.
2. Embed Privacy will add an overlay to supported embeds automatically.
3. You can customize the overlays for each service individually.
4. Embed Privacy can deliver embedded Tweets locally without 3rd party scripts or tracking.
