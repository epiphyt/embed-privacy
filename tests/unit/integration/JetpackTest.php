<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\integration\Jetpack;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Jetpack::class)]
final class JetpackTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        Embed_Privacy::$instance = null;
        Providers::$instance = null;
    }

    /**
     * Full WordPress stub set required by the Replacement stack.
     */
    private function stubReplacementEnvironment(): void
    {
        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'get_current_blog_id' => 1,
            'get_locale' => 'en_US',
            'get_option' => false,
            'get_post_thumbnail_id' => 0,
            'get_posts' => [],
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
            'wp_json_encode' => 'json',
            'wp_localize_script' => null,
            'wp_register_script' => true,
            'wp_register_style' => true,
            'wp_script_is' => false,
            'wp_style_is' => false,
            'wp_kses' => static function ($string) {
                return $string;
            },
            'wp_kses_post' => static function ($string) {
                return $string;
            },
            'wp_parse_args' => static function ($value, $default) {
                return \array_merge($default, (array) $value);
            },
            'wp_parse_url' => 'parse_url',
        ]);
    }

    /**
     * init() returns early when Jetpack is not installed (JETPACK__VERSION undefined).
     * This test must run before any test that defines the constant.
     */
    // separate process so JETPACK__VERSION is genuinely undefined regardless of
    // other tests that define it (constants leak per-process and cannot be unset)
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInitReturnsEarlyWithoutJetpack(): void
    {
        if (\defined('JETPACK__VERSION')) {
            $this->markTestSkipped('JETPACK__VERSION is already defined.');
        }

        Jetpack::init();

        $this->assertFalse(
            hasFilter('embed_privacy_overlay_replaced_content', [Jetpack::class, 'replace_facebook_posts'])
        );
        $this->assertFalse(
            hasAction('wp_enqueue_scripts', [Jetpack::class, 'deregister_assets'])
        );
    }

    public function testInitRegistersHooksWhenJetpackAvailable(): void
    {
        if (! \defined('JETPACK__VERSION')) {
            \define('JETPACK__VERSION', '13.0');
        }

        Jetpack::init();

        $this->assertNotFalse(
            hasAction('wp_enqueue_scripts', [Jetpack::class, 'deregister_assets'])
        );
        $this->assertNotFalse(
            hasFilter('embed_privacy_overlay_replaced_content', [Jetpack::class, 'replace_facebook_posts'])
        );
    }

    public function testDeregisterAssetsDeregistersFacebookEmbedScript(): void
    {
        expect('wp_deregister_script')->once()->with('jetpack-facebook-embed');

        Jetpack::deregister_assets();
    }

    public function testReplaceFacebookPostsReturnsContentWithoutFacebookPost(): void
    {
        // the guard short-circuits, so no provider lookup happens
        expect('get_posts')->never();

        $content = '<p>Just a paragraph without any Facebook embed.</p>';

        $this->assertSame($content, Jetpack::replace_facebook_posts($content));
    }

    public function testReplaceFacebookPostsTogglesFilterWhileProcessing(): void
    {
        $this->stubReplacementEnvironment();

        // the method removes its own filter before processing and re-adds it afterwards
        expect('remove_filter')
            ->once()
            ->with('embed_privacy_overlay_replaced_content', [Jetpack::class, 'replace_facebook_posts']);
        expect('add_filter')
            ->once()
            ->with('embed_privacy_overlay_replaced_content', [Jetpack::class, 'replace_facebook_posts']);

        $content = '<div class="fb-post" data-href="https://www.facebook.com/foo/posts/123"></div>';

        $output = Jetpack::replace_facebook_posts($content);

        // the Facebook post is wrapped in an Embed Privacy overlay and flagged as an embed
        $this->assertNotSame($content, $output);
        $this->assertStringContainsString('embed-privacy-container', $output);
        $this->assertTrue(Embed_Privacy::get_instance()->has_embed);
    }

    protected function tearDown(): void
    {
        tearDown();
        Embed_Privacy::$instance = null;
        Providers::$instance = null;
        parent::tearDown();
    }
}
