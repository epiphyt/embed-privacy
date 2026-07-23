<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\integration\Instagram;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\faker;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Characterization tests for the Instagram integration.
 *
 * These lock down the observable behaviour of Instagram::replace_posts(): live
 * Instagram blockquote embeds (plus their embed.js script) are converted into
 * Embed Privacy overlays attributed to the "instagram" provider.
 */
#[CoversClass(Instagram::class)]
final class InstagramTest extends MockeryTestCase
{
    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * @var \Brain\Faker\Providers
     */
    protected $wpFaker;

    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        // reset singletons so cached provider lists don't leak between tests
        Embed_Privacy::$instance = null;
        Providers::$instance = null;

        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'get_current_blog_id' => 1,
            'get_locale' => 'en_US',
            'get_option' => false,
            'get_post_thumbnail_id' => 0,
            'get_transient' => false,
            'has_blocks' => false,
            'home_url' => 'https://www.example.com',
            'is_admin' => false,
            'sanitize_title' => function ($title) {
                return \strtolower(\trim(\preg_replace('/[^a-z0-9]+/i', '-', (string) $title), '-'));
            },
            'set_transient' => true,
            'update_meta_cache' => null,
            'wp_add_inline_script' => true,
            'wp_enqueue_script' => null,
            'wp_enqueue_style' => null,
            'wp_localize_script' => null,
            'wp_register_script' => true,
            'wp_register_style' => true,
            'wp_script_is' => false,
            'wp_style_is' => false,
            'wp_generate_uuid4' => '00000000-0000-0000-0000-000000000000',
            'wp_json_encode' => 'json_encode',
            'wp_kses' => function ($string) {
                return $string;
            },
            'wp_kses_post' => function ($string) {
                return $string;
            },
            'wp_parse_args' => function ($value, $default) {
                return \array_merge($default, $value);
            },
            'wp_parse_url' => 'parse_url',
            'get_post_meta' => function ($id, $key, $single = false) {
                switch ($key) {
                    case 'is_system':
                        return 'yes';
                    case 'regex_default':
                        return '/(https?:)?\/\/(www\.)?instagram\.com/';
                    case 'content_item_name':
                        return 'post';
                }

                return '';
            },
        ]);

        $this->faker = faker();
        $this->wpFaker = $this->faker->wp();
    }

    /**
     * Build the single controlled Instagram provider used by the replacer.
     */
    private function stubInstagramProvider(): void
    {
        $provider = $this->wpFaker->post([
            'ID' => 1,
            'post_content' => 'Look at this post.',
            'post_name' => 'instagram',
            'post_status' => 'publish',
            'post_title' => 'Instagram',
            'post_type' => 'epi_embed',
        ]);
        expect('get_posts')->andReturn([$provider]);
    }

    /**
     * A single Instagram blockquote embed followed by its embed.js script.
     */
    private function instagramEmbed(string $permalink): string
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return '<blockquote class="instagram-media" data-instgrm-captioned data-instgrm-permalink="' . $permalink . '?utm_source=ig_embed&amp;utm_campaign=loading" data-instgrm-version="14" style=" background:#FFF; border:0; border-radius:3px; margin: 1px; max-width:540px; min-width:326px; padding:0; width:99.375%;">'
            . '<div style="padding:16px;"><a href="' . $permalink . '?utm_source=ig_embed&amp;utm_campaign=loading" target="_blank">'
            . '<div style="display: flex; flex-direction: row; align-items: center;">Sieh dir diesen Beitrag auf Instagram an</div></a>'
            . '<p style="color:#c9c8cd;"><a href="' . $permalink . '?utm_source=ig_embed&amp;utm_campaign=loading" target="_blank">A post shared by Instagram (@instagram)</a></p></div>'
            . '</blockquote>' . "\n"
            . '        <script async src="//www.instagram.com/embed.js"></script>';
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    public function testInitRegistersReplacePostsFilter(): void
    {
        Instagram::init();

        // has() returns the registered priority (an int) for a matching callback, false otherwise
        $this->assertNotFalse(
            hasFilter('embed_privacy_overlay_replaced_content', [Instagram::class, 'replace_posts'])
        );
    }

    public function testReplacePostsReturnsContentUnchangedWithoutInstagramScript(): void
    {
        // no provider should be queried when the guard short-circuits
        expect('get_posts')->never();

        $content = '<p>Just a paragraph with a <a href="https://example.com">link</a>.</p>';

        $this->assertSame($content, Instagram::replace_posts($content));
    }

    public function testReplacePostsConvertsSingleBlockquoteToOverlay(): void
    {
        $this->stubInstagramProvider();

        $content = 'Code #1' . "\n\n" . $this->instagramEmbed('https://www.instagram.com/p/Cw7y8YIID-C/');

        $output = Instagram::replace_posts($content);

        // exactly one overlay is produced for the single embed
        $this->assertSame(1, \substr_count($output, 'embed-privacy-container'));
        // the overlay is attributed to the Instagram provider
        $this->assertStringContainsString('data-embed-provider="instagram"', $output);
        // the live blockquote and embed script are no longer present as live markup
        $this->assertStringNotContainsString('<blockquote class="instagram-media', $output);
        $this->assertStringNotContainsString('<script async src="//www.instagram.com/embed.js">', $output);
    }

    public function testReplacePostsConvertsMultipleBlockquotesToOverlays(): void
    {
        $this->stubInstagramProvider();

        $content = 'Code #1' . "\n\n" . $this->instagramEmbed('https://www.instagram.com/p/Cw7y8YIID-C/')
            . "\n\n" . 'Code #2' . "\n\n" . $this->instagramEmbed('https://www.instagram.com/p/DLXW1DCMMBo/');

        $output = Instagram::replace_posts($content);

        // both embeds are wrapped in overlays, none left live
        $this->assertSame(2, \substr_count($output, 'embed-privacy-container'));
        $this->assertStringNotContainsString('<blockquote class="instagram-media', $output);
        $this->assertStringNotContainsString('<script async src="//www.instagram.com/embed.js">', $output);
        // both overlays belong to the Instagram provider (and no other provider is introduced)
        $this->assertStringContainsString('data-embed-provider="instagram"', $output);
        $this->assertSame(
            \substr_count($output, 'data-embed-provider="instagram"'),
            \substr_count($output, 'data-embed-provider=')
        );
    }

    public function testReplacePostsMarksEmbedPrivacyHasEmbed(): void
    {
        $this->stubInstagramProvider();

        $content = 'Code #1' . "\n\n" . $this->instagramEmbed('https://www.instagram.com/p/Cw7y8YIID-C/');

        Instagram::replace_posts($content);

        // the replacement records that the page contains an embed
        $this->assertTrue(Embed_Privacy::get_instance()->has_embed);
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
