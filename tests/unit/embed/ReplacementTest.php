<?php

declare(strict_types=1);

namespace Tests\Unit\embed;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\embed\Replacement;
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
 * Characterization tests for the embed replacement engine.
 *
 * These lock down provider resolution in the constructor and the observable
 * output/guard branches of Replacement::get().
 */
#[CoversClass(Replacement::class)]
final class ReplacementTest extends MockeryTestCase
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
            '_doing_it_wrong' => null,
            'get_current_blog_id' => 1,
            'get_locale' => 'en_US',
            'get_option' => false,
            'get_post' => static function () {
                return null;
            },
            'get_post_thumbnail_id' => 0,
            'get_the_ID' => 0,
            'get_transient' => false,
            'has_blocks' => false,
            'home_url' => 'https://www.example.com',
            'is_admin' => false,
            'sanitize_title' => static function ($title) {
                return \strtolower(\trim(\preg_replace('/[^a-z0-9]+/i', '-', (string) $title), '-'));
            },
            'set_transient' => true,
            'update_meta_cache' => null,
            'wp_add_inline_script' => true,
            'wp_enqueue_script' => null,
            'wp_enqueue_style' => null,
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
            'wp_localize_script' => null,
            'wp_parse_args' => static function ($value, $default) {
                return \array_merge($default, (array) $value);
            },
            'wp_parse_url' => static function ($url, $component = -1) {
                return \parse_url((string) $url, $component);
            },
            'wp_register_script' => true,
            'wp_register_style' => true,
            'wp_script_is' => false,
            'wp_style_is' => false,
            'get_post_meta' => static function ($id, $key) {
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
     * Register a single controlled YouTube provider for the providers list.
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

    /**
     * Build a fully-formed (non-unknown) YouTube provider from a post object.
     */
    private function makeYouTubeProvider(): Provider
    {
        $post = $this->wpFaker->post([
            'ID' => 1,
            'post_content' => 'Watch our video.',
            'post_name' => 'youtube',
            'post_status' => 'publish',
            'post_title' => 'YouTube',
            'post_type' => 'epi_embed',
        ]);

        return new Provider($post);
    }

    public function testConstructorCreatesUnknownProviderForPlainContent(): void
    {
        expect('get_posts')->andReturn([]);

        $replacement = new Replacement('just some plain text');
        $providers = $replacement->get_providers();

        $this->assertCount(1, $providers);
        $this->assertTrue($providers[0]->is_unknown());
    }

    public function testConstructorCreatesHostProviderFromUrl(): void
    {
        expect('get_posts')->andReturn([]);

        $replacement = new Replacement('some content', 'https://vimeo.com/12345');
        $providers = $replacement->get_providers();

        $this->assertCount(1, $providers);
        $this->assertSame('vimeo.com', $providers[0]->get_name());
        $this->assertSame('vimeo.com', $providers[0]->get_title());
    }

    public function testConstructorResolvesMatchingProviderFromList(): void
    {
        $this->stubYouTubeProvider();
        $content = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';

        $replacement = new Replacement($content);
        $providers = $replacement->get_providers();

        $this->assertNotEmpty($providers);
        $this->assertSame('youtube', $providers[0]->get_name());
    }

    public function testGetReturnsEmptyStringForEmptyContent(): void
    {
        expect('get_posts')->andReturn([]);

        $replacement = new Replacement('');

        $this->assertSame('', $replacement->get());
    }

    public function testGetReturnsContentUnchangedWhenUnknownProvidersIgnored(): void
    {
        expect('get_posts')->andReturn([]);
        // the filter short-circuits processing for unknown providers
        expectApplied('embed_privacy_ignore_unknown_providers')->andReturn(true);
        $content = '<iframe src="https://example.org/x"></iframe>';

        $replacement = new Replacement($content);

        $this->assertSame($content, $replacement->get());
    }

    public function testGetReplacesYoutubeIframeWithOverlay(): void
    {
        $this->stubYouTubeProvider();
        $content = '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">'
            . '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'
            . '</div></figure>';

        $output = (new Replacement($content))->get();

        $this->assertSame(1, \substr_count($output, 'embed-privacy-container'));
        $this->assertStringContainsString('data-embed-provider="youtube"', $output);
        $this->assertStringNotContainsString('<iframe', $output);
    }

    public function testGetUsesExplicitlyPassedProvider(): void
    {
        expect('get_posts')->andReturn([]);
        $content = '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';

        $output = (new Replacement($content))->get([], $this->makeYouTubeProvider());

        $this->assertStringContainsString('embed-privacy-container', $output);
        $this->assertStringContainsString('data-embed-provider="youtube"', $output);
    }

    public function testGetWithOembedAttributeWrapsContentDirectly(): void
    {
        expect('get_posts')->andReturn([]);
        $content = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        $output = (new Replacement($content))->get(['is_oembed' => true], $this->makeYouTubeProvider());

        // the oembed branch skips DOM parsing and wraps the content directly
        $this->assertStringContainsString('embed-privacy-container', $output);
    }

    public function testGetReturnsContentWhenProviderIsDisabled(): void
    {
        expect('get_posts')->andReturn([]);
        $content = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
        $provider = $this->makeYouTubeProvider();
        $provider->set_is_disabled(true);

        $output = (new Replacement($content))->get([], $provider);

        // a disabled provider leaves the embed untouched
        $this->assertStringNotContainsString('embed-privacy-container', $output);
        $this->assertStringContainsString('<iframe', $output);
    }

    public function testGetSkipsSameDomainEmbeds(): void
    {
        expect('get_posts')->andReturn([]);
        // the iframe points at the site's own host and must not be replaced
        $content = '<iframe src="https://www.example.com/embed/local"></iframe>';

        $output = (new Replacement($content))->get([], $this->makeYouTubeProvider());

        $this->assertStringNotContainsString('embed-privacy-container', $output);
        $this->assertStringContainsString('<iframe', $output);
    }

    public function testGetLeavesRelativeUnknownEmbedsUntouched(): void
    {
        expect('get_posts')->andReturn([]);
        // an unknown provider with a hostless (relative) embed URL is left alone
        $content = '<iframe src="/local/video"></iframe>';

        $replacement = new Replacement($content);
        $output = $replacement->get();

        $this->assertStringNotContainsString('embed-privacy-container', $output);
        $this->assertStringContainsString('<iframe', $output);
    }

    public function testGetAppliesReplacedContentFilter(): void
    {
        expect('get_posts')->andReturn([]);
        expectApplied('embed_privacy_overlay_replaced_content')->andReturn('   ');

        $replacement = new Replacement('original');

        // filtered content that is only whitespace short-circuits and is returned as-is
        $this->assertSame('   ', $replacement->get());
    }

    public function testGetProvidersReturnsResolvedProviders(): void
    {
        expect('get_posts')->andReturn([]);

        $replacement = new Replacement('nothing to see');

        $this->assertIsArray($replacement->get_providers());
        $this->assertNotEmpty($replacement->get_providers());
    }

    public function testGetProviderIsDeprecatedAndReturnsNull(): void
    {
        expect('get_posts')->andReturn([]);

        $replacement = new Replacement('content');

        // deprecated accessor: emits a notice (stubbed) and returns the unset provider
        $this->assertNull($replacement->get_provider());
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
