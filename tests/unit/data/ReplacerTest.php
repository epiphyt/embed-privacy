<?php

declare(strict_types=1);

namespace Tests\Unit\data;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\data\Replacer;
use epiphyt\Embed_Privacy\Embed_Privacy;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\faker;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Characterization tests for the embed replacement engine.
 *
 * These lock down the observable behaviour of Replacer::replace_embeds() so the
 * internal restructuring of the replacement loop / DOMDocument handling cannot
 * silently change the output.
 */
#[CoversClass(Replacer::class)]
final class ReplacerTest extends MockeryTestCase
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
                        return '/(https?:)?\/\/(www\.)?(youtube\.com|youtu\.be)/';
                    case 'content_item_name':
                        return 'video';
                }

                return '';
            },
        ]);

        $this->faker = faker();
        $this->wpFaker = $this->faker->wp();
    }

    /**
     * Build the single controlled YouTube provider used by the replacer.
     */
    private function stubYouTubeProvider(): void
    {
        $provider = $this->wpFaker->post([
            'ID' => 1,
            'post_content' => 'Watch our video.',
            'post_name' => 'youtube',
            'post_status' => 'publish',
            'post_title' => 'YouTube',
            'post_type' => 'epi_embed',
        ]);
        expect('get_posts')->andReturn([$provider]);
    }

    public function testReplacesYouTubeIframeWithSingleOverlay(): void
    {
        $this->stubYouTubeProvider();
        $content = '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">'
            . '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'
            . '</div></figure>';

        $output = Replacer::replace_embeds($content);

        // exactly one overlay is created for the single embed
        $this->assertSame(1, \substr_count($output, 'embed-privacy-container'));
        // the overlay is attributed to the YouTube provider
        $this->assertStringContainsString('data-embed-provider="youtube"', $output);
        // the original live iframe is gone (it is stored encoded inside the overlay)
        $this->assertStringNotContainsString('<iframe', $output);
    }

    public function testReplacesMultipleEmbedsInOnePass(): void
    {
        $this->stubYouTubeProvider();
        $content = '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">'
            . '<iframe width="560" height="315" src="https://www.youtube.com/embed/aaaaaaaaaaa"></iframe>'
            . '</div></figure>'
            . '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">'
            . '<iframe width="560" height="315" src="https://www.youtube.com/embed/bbbbbbbbbbb"></iframe>'
            . '</div></figure>';

        $output = Replacer::replace_embeds($content);

        // both embeds are wrapped, and none are left live
        $this->assertSame(2, \substr_count($output, 'embed-privacy-container'));
        $this->assertStringNotContainsString('<iframe', $output);
    }

    public function testReplacementIsIdempotent(): void
    {
        $this->stubYouTubeProvider();
        $content = '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">'
            . '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'
            . '</div></figure>';

        $once = Replacer::replace_embeds($content);
        $twice = Replacer::replace_embeds($once);

        // running the replacer over already-replaced content must not wrap it again
        $this->assertSame(
            \substr_count($once, 'embed-privacy-container'),
            \substr_count($twice, 'embed-privacy-container')
        );
        $this->assertSame(1, \substr_count($twice, 'embed-privacy-container'));
    }

    public function testContentWithoutEmbedIsUnchanged(): void
    {
        $this->stubYouTubeProvider();
        $content = '<p>Just a paragraph with a <a href="https://example.com">link</a>.</p>';

        $this->assertSame($content, Replacer::replace_embeds($content));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
