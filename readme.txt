=== Embed Privacy ===
Contributors: epiphyt, kittmedia, krafit
Tags: oembed, privacy, gutenberg, iframes, performance
Requires at least: 5.9
Stable tag: 1.12.3
Tested up to: 6.9
Requires PHP: 5.6
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed Privacy prevents the loading of embedded external content and allows your site visitors to opt-in.

== Description ==

Content embedded from external sites such as YouTube or Twitter is loaded immediately when visitors access your site. Embed Privacy addresses this issue and prevents the loading of these contents until the visitor decides to allow loading of external content.
But Embed Privacy not only protects your visitor's privacy but also makes your site load faster.

All embeds will be replaced by placeholders, ready for you to apply style as you wish. With only a couple of lines of CSS. 

By clicking on the placeholder the respective content will then be loaded.

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

= Does Embed Privacy use cookies? =

If you use the opt-out functionality with the shortcode or the functionality to allow the user to always display content of certain embed providers, Embed Privacy creates a single cookie called `embed-privacy` with an expiration of 1 year to store the user’s choice.

= Does Embed Privacy support the block editor? =

Sure thing! We enjoy playing with the block editor and developed Embed Privacy with Gutenberg in mind, the plugin will work no matter the editor you use.

= Which embeds are currently supported? =

We currently support all oEmbed providers known to WordPress core by default. Want to know about them? Here you go:

* Amazon Kindle
* Anghami
* Animoto
* Bluesky
* Canva
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

We also support Google Maps via iframe and Divi and the following plugins:

* BuddyPress activity stream
* Jetpack (Facebook posts)
* Maps Marker (Pro)
* Shortcodes Ultimate
* wpForo (with the plugin wpForo Embeds)

Additionally, we support the following custom content:

* Local Fediverse content
* Local X posts
* Facebook embed code (HTML)
* Instagram embed code (HTML)

Since version 1.2.0, you can also add custom embed providers by going to **Settings > Embed Privacy > Manage embeds**. Here you can also modify any existing embed provider, change its logo, add a background image, change the text displaying on the embed or disable the embed provider entirely.

= Can Embed Privacy automatically download thumbnails of the embedded content? =

Yes! Since version 1.5.0, Embed Privacy supports downloading and displaying thumbnails in posts for SlideShare, Vimeo and YouTube as background of Embed Privacy’s overlay.

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

We are [Epiphyt](https://epiph.yt/en/), your friendly neighborhood WordPress plugin shop from southern Germany.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/embed-privacy)

== Changelog ==

= 1.12.3 =
* Fixed: Saving embed fields for embed providers was not successful

= 1.12.2 =
* Improved: Dynamic content is now handled more performant
* Fixed: The current focussed element is no more changed to the first active embed after page load

= 1.12.1 =
* Fixed: Fatal error during activation and saving embed providers

= 1.12.0 =
* Added: Allow handling dynamic content (in combination with the setting "Force script loading")
* Improved: Performance for content with many blocks
* Improved: Overall performance through various caching mechanisms
* Changed: Renamed tweets to X posts
* Fixed: Default English descriptions are now automatically translated as soon as a translation is available, if it's missing during installation
* Fixed: Handling multiple Instagram/Facebook posts within the same content
* Fixed: Fatal error in combination with Sugar Calendar Lite and Elementor

For the full changelog, please visit [https://docs.epiph.yt/embed-privacy/changelog.html](https://docs.epiph.yt/embed-privacy/changelog.html).

== Upgrade Notice ==

== Screenshots ==
1. Add embeds using the classic editor or the block editor's embed blocks.
2. Embed Privacy will add an overlay to supported embeds automatically.
3. You can customize the overlays for each service individually.
4. Different settings allow you to adjust the functionality of Embed Privacy according to your needs.
