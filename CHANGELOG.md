# Changelog

## 1.2.0
* Added support for managing embeds (add/remove/edit/disable)
* Added support for caching plugins by adding a JavaScript detection for always active embed providers
* Added CSS classes that indicate the current state of the embed (`is-disabled`/`is-enabled`)
* Added shortcode `[embed_privacy_opt_out]` to allow users to opt-out/in
* Fixed responsive design if the embed added an own width

## 1.1.3
* Changed provider name from Polldaddy to Crowdsignal
* Removed provider Hulu

## 1.1.2
* Fixed a possible difference in the used class name of the embed provider in HTML and CSS

## 1.1.1
* Removed provider CollegeHumor
* Fixed a bug with the automatic addition of paragraphs

## 1.1.0
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

## 1.0.2
* Improved compatibility with [Autoptimize](https://wordpress.org/plugins/autoptimize/)
* Improved compatibility with [AMP](https://wordpress.org/plugins/amp/)
* Fix issue with Slideshare causing wrong (generic) placeholders

## 1.0.1
* Fixed support for PHP 5.6

## 1.0.0
* Initial release
