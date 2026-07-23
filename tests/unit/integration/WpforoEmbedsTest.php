<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\integration\Wpforo_Embeds;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Wpforo_Embeds::class)]
final class WpforoEmbedsTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        Embed_Privacy::$instance = null;
    }

    public function testInitRegistersHooks(): void
    {
        Wpforo_Embeds::init();

        $this->assertNotFalse(
            hasAction('embed_privacy_print_assets', [Wpforo_Embeds::class, 'enqueue_assets'])
        );
        $this->assertNotFalse(
            hasAction('embed_privacy_register_assets', [Wpforo_Embeds::class, 'register_assets'])
        );
        $this->assertNotFalse(
            hasFilter('wpforo_content_after', [Wpforo_Embeds::class, 'replace'])
        );
    }

    public function testEnqueueAssetsEnqueuesStyleWhenPluginActive(): void
    {
        stubs(['is_plugin_active' => true]);
        expect('wp_enqueue_style')->once()->with('embed-privacy-wpforo-embeds');

        Wpforo_Embeds::enqueue_assets();
    }

    public function testEnqueueAssetsDoesNothingWhenPluginInactive(): void
    {
        stubs(['is_plugin_active' => false]);
        expect('wp_enqueue_style')->never();

        Wpforo_Embeds::enqueue_assets();
    }

    public function testRegisterAssetsUsesVersionConstantWhenNotDebug(): void
    {
        expect('wp_register_style')->once()->with(
            'embed-privacy-wpforo-embeds',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/wpforo-embeds.min.css',
            [],
            \EMBED_PRIVACY_VERSION
        );

        Wpforo_Embeds::register_assets(false, '.min');
    }

    public function testRegisterAssetsUsesFilemtimeWhenDebug(): void
    {
        // an empty suffix maps to an existing CSS file, so filemtime() does not warn;
        // the source casts the result to string
        $expected = (string) \filemtime(\EPI_EMBED_PRIVACY_BASE . 'assets/style/wpforo-embeds.css');
        expect('wp_register_style')->once()->with(
            'embed-privacy-wpforo-embeds',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/wpforo-embeds.css',
            [],
            $expected
        );

        Wpforo_Embeds::register_assets(true, '');
    }

    public function testReplaceReturnsContentUnchangedWhenCacheDisabled(): void
    {
        // Replacer::replace_embeds() short-circuits when use_cache is false (admin context)
        $instance = Mockery::mock(Embed_Privacy::class);
        $instance->use_cache = false;
        Embed_Privacy::$instance = $instance;

        $content = '<p>Some forum content with an embed.</p>';

        $this->assertSame($content, Wpforo_Embeds::replace($content));
    }

    protected function tearDown(): void
    {
        tearDown();
        Embed_Privacy::$instance = null;
        parent::tearDown();
    }
}
