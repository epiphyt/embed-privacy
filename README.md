# Embed Privacy

Embed Privacy prevents the loading of embedded external content and allows your site visitors to opt-in.

Content embedded from external sites such as YouTube or Twitter is loaded immediately when visitors access your site. Embed Privacy addresses this issue and prevents the loading of these contents until the visitor decides to allow loading of external content.
But Embed Privacy not only protects your visitor's privacy but also makes your site load faster.

All embeds will be replaced by placeholders, ready for you to apply style as you wish. With only a couple of lines of CSS. 

By clicking on the placeholder the respective content will be reloaded.


## Requirements

PHP: 5.6<br>
WordPress: 4.7


## Installation

1. Upload the plugin files to the `/wp-content/plugins/embed-privacy` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Embedded content will automatically be replaced by a placeholder and can be loaded on demand by your visitors. There are no additional settings.
1. To allow users to opt-out of embed providers that they set to always active, place the shortcode `[embed_privacy_opt_out]` into your privacy policy.


## Frequently Asked Questions

### Can Embed Privacy keep external services from tracking me/my visitors?

Yes. As long as you don't opt in to load external content, you/your visitors can't be tracked by these services.

### Does Embed Privacy make embedding content privacy-friendly?

The embedding process itself will be privacy-friendly with Embed Privacy. That means, that no third-party embed provider can track users without their explicit consent by clicking on the overlay to allow the embed to be loaded. However, to make sure everything is fine you need to expand your privacy policy for each embed provider you’re using or you want to use because you need to specify, where data will be sent to and what happens to them.

### Does Embed Privacy support the Gutenberg editor?

Sure thing! We enjoy playing with the new WordPress editor and developed Embed Privacy with Gutenberg in mind, the plugin will work no matter the editor you use.

### Which embeds are currently supported?

We currently support all oEmbed providers known to WordPress core by default. Want to know about them? Here you go: Amazon Kindle, Animoto, Cloudup, DailyMotion, Facebook, Flickr, Funny Or Die, Imgur, Instagram, Issuu, Kickstarter, Meetup, Mixcloud, Photobucket, Pocket Casts, Polldaddy.com, Reddit, ReverbNation, Scribd, Sketchfab, SlideShare, SmugMug, SoundCloud, Speaker Deck, Spotify, TikTok, TED, Tumblr, Twitter, VideoPress, Vimeo, WordPress.org, WordPress.tv, YouTube.

We also support Google Maps via iframe and the plugins Maps Marker, Maps Marker Pro and Shortcodes Ultimate.

Since version 1.2.0, you can also add custom embed providers by going to **Settings > Embed Privacy > Manage embeds**. Here you can also modify any existing embed provider, change its logo, add a background image, change the text displaying on the embed or disable the embed provider entirely.

### Can Embed Privacy automatically download thumbnails of the embedded content?

Yes! Since version 1.5.0, Embed Privacy supports downloading and displaying thumbnails in posts for Vimeo and YouTube as background of Embed Privacy’s overlay.

### Developers: How to use Embed Privacy’s methods for custom content?

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
		// enqueue assets
		$embed_privacy->print_assets();
	}
	
	return $content;
}
```

### Can users opt-out of already opted in embed providers?

Yes! You can use the shortcode `[embed_privacy_opt_out]` to add a list of embed providers anywhere you want (recommendation: add it to your privacy policy) to allow your users to opt-out.

### What parameters can be used in the shortcode?

The shortcode `[embed_privacy_opt_out]` can be used to let users opt-out of embed providers that have been set to be always active by the user. It can have the following attributes:

`headline` – Add a custom headline (default: Embed providers)

`
[embed_privacy_opt_out headline="My custom headline"]
`

`subline` – Add a custom subline (default: Enable or disable embed providers globally. While an embed provider is disabled, its embedded content will be displayed directly on every page without asking you anymore.)

`
[embed_privacy_opt_out subline="My custom subline"]
`

`show_all` – Whether to show all available embed providers or just the ones the user opted in (default: false)

`
[embed_privacy_opt_out show_all="1"]
`

You can also combine all of these attributes:

`
[embed_privacy_opt_out headline="My custom headline" subline="My custom subline" show_all="1"]
`

### Is this plugin compatible with my caching plugin?

If you’re using a caching plugin, make sure you enable the "JavaScript detection for active providers" in **Settings > Embed Privacy > JavaScript detection**. Then, the plugin is fully compatible with your caching plugin.

### Who are you, folks?

We are [Epiphyt](https://epiph.yt/), your friendly neighborhood WordPress plugin shop from southern Germany.


## License

Embed Privacy is free software, and is released under the terms of the GNU General Public License version 2 or (at your option) any later version. See [LICENSE.md](LICENSE.md) for complete license.
