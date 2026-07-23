<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Shortcodes_Ultimate;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Shortcodes_Ultimate::class)]
final class ShortcodesUltimateTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testInitRegistersAssetHooks(): void
    {
        Shortcodes_Ultimate::init();

        $this->assertNotFalse(
            hasAction('embed_privacy_print_assets', [Shortcodes_Ultimate::class, 'enqueue_assets'])
        );
        $this->assertNotFalse(
            hasAction('embed_privacy_register_assets', [Shortcodes_Ultimate::class, 'register_assets'])
        );
    }

    public function testEnqueueAssetsEnqueuesStyleWhenPluginActive(): void
    {
        stubs(['is_plugin_active' => true]);
        expect('wp_enqueue_style')->once()->with('embed-privacy-shortcodes-ultimate');

        Shortcodes_Ultimate::enqueue_assets();
    }

    public function testEnqueueAssetsDoesNothingWhenPluginInactive(): void
    {
        stubs(['is_plugin_active' => false]);
        expect('wp_enqueue_style')->never();

        Shortcodes_Ultimate::enqueue_assets();
    }

    public function testRegisterAssetsUsesVersionConstantWhenNotDebug(): void
    {
        expect('wp_register_style')->once()->with(
            'embed-privacy-shortcodes-ultimate',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/shortcodes-ultimate.min.css',
            [],
            \EMBED_PRIVACY_VERSION
        );

        Shortcodes_Ultimate::register_assets(false, '.min');
    }

    public function testRegisterAssetsUsesFilemtimeWhenDebug(): void
    {
        // an empty suffix maps to an existing CSS file, so filemtime() does not warn
        $expected = \filemtime(\EPI_EMBED_PRIVACY_BASE . 'assets/style/shortcodes-ultimate.css');
        expect('wp_register_style')->once()->with(
            'embed-privacy-shortcodes-ultimate',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/shortcodes-ultimate.css',
            [],
            $expected
        );

        Shortcodes_Ultimate::register_assets(true, '');
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
