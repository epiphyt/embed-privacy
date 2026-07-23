<?php

declare(strict_types=1);

namespace Tests\Unit\embed;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\embed\Template;
use epiphyt\Embed_Privacy\Embed_Privacy;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\faker;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Characterization tests for the overlay template renderer.
 *
 * These lock down the observable markup produced by Template::get() so the
 * overlay structure and its guard branches cannot change silently.
 */
#[CoversClass(Template::class)]
final class TemplateTest extends MockeryTestCase
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

        // reset singletons so cached state doesn't leak between tests
        Embed_Privacy::$instance = null;
        Providers::$instance = null;

        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            '_doing_it_wrong' => null,
            'get_current_blog_id' => 1,
            'get_locale' => 'en_US',
            'get_option' => false,
            'get_post' => static function () {
                return null;
            },
            'get_post_thumbnail_id' => 0,
            'get_transient' => false,
            'has_blocks' => false,
            'home_url' => 'https://www.example.com',
            'is_admin' => false,
            'set_transient' => true,
            'update_meta_cache' => null,
            'sanitize_title' => static function ($title) {
                return \strtolower(\trim(\preg_replace('/[^a-z0-9]+/i', '-', (string) $title), '-'));
            },
            'wp_generate_uuid4' => '00000000-0000-0000-0000-000000000000',
            'wp_json_encode' => static function ($data) {
                return \json_encode($data);
            },
            'wp_kses' => static function ($string) {
                return $string;
            },
            'wp_kses_post' => static function ($string) {
                return $string;
            },
            'wp_parse_args' => static function ($value, $default) {
                return \array_merge($default, (array) $value);
            },
            'get_post_meta' => static function () {
                return '';
            },
        ]);

        $this->faker = faker();
        $this->wpFaker = $this->faker->wp();
    }

    /**
     * Build a plain provider with a name/title (unknown, no post object).
     */
    private function makeProvider(string $name = 'youtube', string $title = 'YouTube'): Provider
    {
        $provider = new Provider();
        $provider->set_name($name);
        $provider->set_title($title);

        return $provider;
    }

    public function testGeneratesOverlayForUnknownProvider(): void
    {
        $output = Template::get(new Provider(), '<iframe src="https://example.org/x"></iframe>');

        // the container is created with the default embed class
        $this->assertStringContainsString('embed-privacy-container', $output);
        $this->assertStringContainsString('embed-default', $output);
        // an empty provider name is used everywhere
        $this->assertStringContainsString('data-embed-provider=""', $output);
        // the fallback description is used when the provider has no name
        $this->assertStringContainsString('external service', $output);
        // the original markup is stored inside the _oembed script
        $this->assertStringContainsString('_oembed_', $output);
    }

    public function testUsesProviderNameForClassAndData(): void
    {
        $output = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>');

        $this->assertStringContainsString('embed-youtube', $output);
        $this->assertStringContainsString('data-embed-provider="youtube"', $output);
    }

    public function testRendersProviderDescription(): void
    {
        $provider = $this->makeProvider();
        $provider->set_description('Watch our great video.');

        $output = Template::get($provider, '<iframe src="https://example.org/x"></iframe>');

        $this->assertStringContainsString('Watch our great video.', $output);
    }

    public function testRendersPrivacyPolicyLink(): void
    {
        $provider = $this->makeProvider();
        $provider->set_description('Watch our great video.');
        $provider->set_privacy_policy_url('https://youtube.example/privacy');

        $output = Template::get($provider, '<iframe src="https://example.org/x"></iframe>');

        $this->assertStringContainsString('https://youtube.example/privacy', $output);
        $this->assertStringContainsString('privacy policy', $output);
    }

    public function testReplacesYoutubeDomainWithNocookie(): void
    {
        $output = Template::get($this->makeProvider(), '<iframe src="https://youtube.com/embed/x"></iframe>');

        // the youtube domain in the stored embed is replaced with the nocookie variant
        $this->assertStringContainsString('youtube-nocookie.com', $output);
    }

    public function testFallbackClickHereForNamedProviderWithoutDescription(): void
    {
        // named provider, but no description and no post object => "Click here" branch
        $output = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>');

        $this->assertStringContainsString('Click here to display content from', $output);
    }

    public function testLocalizedContentBranchForNonEnglishLocale(): void
    {
        stubs([
            'get_locale' => 'de_DE',
        ]);
        $provider = $this->makeProvider();
        $provider->set_description('Click here to display content from YouTube.');

        $output = Template::get($provider, '<iframe src="https://example.org/x"></iframe>');

        $this->assertStringContainsString('Click here to display content from YouTube.', $output);
    }

    public function testAddsAlignClass(): void
    {
        $output = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>', [
            'align' => 'center',
        ]);

        $this->assertStringContainsString('aligncenter', $output);
    }

    public function testRendersFooterLinkWhenEmbedUrlGiven(): void
    {
        $output = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>', [
            'embed_url' => 'https://youtube.example/watch?v=1',
        ]);

        $this->assertStringContainsString('embed-privacy-footer', $output);
        $this->assertStringContainsString('embed-privacy-url', $output);
        $this->assertStringContainsString('https://youtube.example/watch?v=1', $output);
    }

    public function testFooterLinkOmittedWhenDisableLinkOptionIsSet(): void
    {
        stubs([
            'get_option' => true,
        ]);

        $output = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>', [
            'embed_url' => 'https://youtube.example/watch?v=1',
        ]);

        // the footer wrapper is still rendered, but without the direct link
        $this->assertStringContainsString('embed-privacy-footer', $output);
        $this->assertStringNotContainsString('embed-privacy-url', $output);
    }

    public function testFooterLinkUsesContentName(): void
    {
        $provider = $this->makeProvider();
        $provider->set_content_name('video');

        $output = Template::get($provider, '<iframe src="https://example.org/x"></iframe>', [
            'embed_url' => 'https://youtube.example/watch?v=1',
        ]);

        // "Open %s directly" with the content name
        $this->assertStringContainsString('Open video directly', $output);
    }

    public function testFooterLinkUsesEmbedTitle(): void
    {
        $output = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>', [
            'embed_title' => 'My Movie',
            'embed_url' => 'https://youtube.example/watch?v=1',
        ]);

        // the button uses the embed title and the footer link references it
        $this->assertStringContainsString('My Movie', $output);
        $this->assertStringContainsString('Open &quot;My Movie&quot; directly', $output);
    }

    public function testStripNewlinesRemovesLineBreaks(): void
    {
        $withNewlines = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>');
        $stripped = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>', [
            'strip_newlines' => true,
        ]);

        $this->assertStringContainsString(\PHP_EOL, $withNewlines);
        $this->assertStringNotContainsString(\PHP_EOL, $stripped);
    }

    public function testPrependsStaticAssets(): void
    {
        $output = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>', [
            'assets' => [
                [
                    'type' => 'script',
                    'handle' => 'my-embed',
                    'src' => 'https://example.com/embed.js',
                ],
            ],
        ]);

        // the asset script becomes part of the stored (html-encoded, slash-escaped) embed markup
        $this->assertStringContainsString('example.com', $output);
        $this->assertStringContainsString('embed.js', $output);
        $this->assertStringContainsString('my-embed', $output);
    }

    public function testReturnsOutputUnchangedWhenPostIsDisabled(): void
    {
        $post = $this->wpFaker->post([
            'ID' => 5,
            'post_content' => 'Disabled provider.',
            'post_type' => 'epi_embed',
        ]);
        stubs([
            'get_post' => static function () use ($post) {
                return $post;
            },
            'get_post_meta' => static function ($id, $key) {
                return $key === 'is_disabled' ? 'yes' : '';
            },
        ]);
        $output = '<iframe src="https://example.org/x"></iframe>';

        $result = Template::get($this->makeProvider(), $output, ['post_id' => 5]);

        $this->assertSame($output, $result);
    }

    public function testUsesPostContentAsDescriptionWhenPostGiven(): void
    {
        $post = $this->wpFaker->post([
            'ID' => 6,
            'post_content' => 'Description straight from the post.',
            'post_type' => 'epi_embed',
        ]);
        stubs([
            'get_post' => static function () use ($post) {
                return $post;
            },
            'get_post_meta' => static function ($id, $key) {
                return $key === 'privacy_policy_url' ? 'https://policy.example' : '';
            },
        ]);

        $result = Template::get(
            $this->makeProvider(),
            '<iframe src="https://example.org/x"></iframe>',
            ['post_id' => 6]
        );

        $this->assertStringContainsString('Description straight from the post.', $result);
        $this->assertStringContainsString('https://policy.example', $result);
    }

    public function testReturnsOutputUnchangedWhenProviderAttributeIsDisabled(): void
    {
        $disabled = new Provider();
        $disabled->set_name('vimeo');
        $disabled->set_is_disabled(true);
        $output = '<iframe src="https://example.org/x"></iframe>';

        $result = Template::get($this->makeProvider(), $output, ['provider' => $disabled]);

        $this->assertSame($output, $result);
    }

    public function testStringProviderIsDeprecatedButStillRenders(): void
    {
        // passing a string provider is deprecated but must still render an overlay
        expect('get_posts')->andReturn([]);

        $output = Template::get('YouTube', '<iframe src="https://example.org/x"></iframe>');

        $this->assertStringContainsString('embed-privacy-container', $output);
    }

    public function testTemplateMarkupFilterCanOverrideOutput(): void
    {
        expectApplied('embed_privacy_template_markup')->once()->andReturn('CUSTOM_MARKUP');

        $result = Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>');

        $this->assertSame('CUSTOM_MARKUP', $result);
    }

    public function testSetsHasEmbedFlag(): void
    {
        Template::get($this->makeProvider(), '<iframe src="https://example.org/x"></iframe>');

        $this->assertTrue(Embed_Privacy::get_instance()->has_embed);
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
