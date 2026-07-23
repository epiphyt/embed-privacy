<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Elementor;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Tests for the Elementor integration.
 *
 * Only the branches reachable without the Elementor plugin classes
 * (Elementor\Plugin) are covered here: hook registration, asset
 * registration, and the "not built with Elementor" guard paths.
 */
#[CoversClass(Elementor::class)]
final class ElementorTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testInitRegistersHooks(): void
    {
        Elementor::init();

        $this->assertNotFalse(
            hasAction('embed_privacy_print_assets', [Elementor::class, 'enqueue_assets'])
        );
        $this->assertNotFalse(
            hasAction('embed_privacy_register_assets', [Elementor::class, 'register_assets'])
        );
        $this->assertNotFalse(
            hasFilter('embed_privacy_overlay_replaced_content', [Elementor::class, 'replace_youtube'])
        );
    }

    public function testIsUsedReturnsFalseWhenPluginInactive(): void
    {
        // System::is_plugin_active() short-circuits the check
        stubs(['is_plugin_active' => false, 'get_queried_object_id' => 0]);

        $this->assertFalse(Elementor::is_used());
    }

    public function testEnqueueAssetsDoesNothingWhenNotUsed(): void
    {
        stubs(['is_plugin_active' => false, 'get_queried_object_id' => 0]);
        expect('wp_enqueue_script')->never();
        expect('wp_enqueue_style')->never();

        Elementor::enqueue_assets();
    }

    public function testRegisterAssetsUsesVersionConstantWhenNotDebug(): void
    {
        expect('wp_register_script')->once()->with(
            'embed-privacy-elementor-video',
            \EPI_EMBED_PRIVACY_URL . 'assets/js/elementor-video.min.js',
            [],
            \EMBED_PRIVACY_VERSION,
            ['strategy' => 'defer']
        );
        expect('wp_register_style')->once()->with(
            'embed-privacy-elementor',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/elementor.min.css',
            [],
            \EMBED_PRIVACY_VERSION
        );

        Elementor::register_assets(false, '.min');
    }

    public function testRegisterAssetsUsesFilemtimeWhenDebug(): void
    {
        // an empty suffix maps to existing asset files, so filemtime() does not warn
        $expectedJs = \filemtime(\EPI_EMBED_PRIVACY_BASE . 'assets/js/elementor-video.js');
        $expectedCss = \filemtime(\EPI_EMBED_PRIVACY_BASE . 'assets/style/elementor.css');
        expect('wp_register_script')->once()->with(
            'embed-privacy-elementor-video',
            \EPI_EMBED_PRIVACY_URL . 'assets/js/elementor-video.js',
            [],
            $expectedJs,
            ['strategy' => 'defer']
        );
        expect('wp_register_style')->once()->with(
            'embed-privacy-elementor',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/elementor.css',
            [],
            $expectedCss
        );

        Elementor::register_assets(true, '');
    }

    public function testReplaceYoutubeReturnsContentUnchangedWhenNotUsed(): void
    {
        // is_used() is false, so the content passes straight through
        stubs(['is_plugin_active' => false, 'get_queried_object_id' => 0]);

        $content = '<div>https://www.youtube.com/watch?v=abc</div>';

        $this->assertSame($content, Elementor::replace_youtube($content));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
