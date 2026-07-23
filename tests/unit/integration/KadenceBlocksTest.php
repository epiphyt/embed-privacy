<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Kadence_Blocks;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Tests for the Kadence Blocks integration.
 */
#[CoversClass(Kadence_Blocks::class)]
final class KadenceBlocksTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testInitRegistersAssetHooks(): void
    {
        Kadence_Blocks::init();

        $this->assertNotFalse(
            hasAction('embed_privacy_print_assets', [Kadence_Blocks::class, 'enqueue_assets'])
        );
        $this->assertNotFalse(
            hasAction('embed_privacy_register_assets', [Kadence_Blocks::class, 'register_assets'])
        );
    }

    public function testEnqueueAssetsEnqueuesStyleWhenPluginActive(): void
    {
        stubs(['is_plugin_active' => true]);
        expect('wp_enqueue_style')->once()->with('embed-privacy-kadence-blocks');

        Kadence_Blocks::enqueue_assets();
    }

    public function testEnqueueAssetsDoesNothingWhenPluginInactive(): void
    {
        stubs(['is_plugin_active' => false]);
        expect('wp_enqueue_style')->never();

        Kadence_Blocks::enqueue_assets();
    }

    public function testRegisterAssetsUsesVersionConstantWhenNotDebug(): void
    {
        expect('wp_register_style')->once()->with(
            'embed-privacy-kadence-blocks',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/kadence-blocks.min.css',
            [],
            \EMBED_PRIVACY_VERSION
        );

        Kadence_Blocks::register_assets(false, '.min');
    }

    public function testRegisterAssetsUsesFilemtimeWhenDebug(): void
    {
        // an empty suffix maps to an existing CSS file, so filemtime() does not warn
        $expected = \filemtime(\EPI_EMBED_PRIVACY_BASE . 'assets/style/kadence-blocks.css');
        expect('wp_register_style')->once()->with(
            'embed-privacy-kadence-blocks',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/kadence-blocks.css',
            [],
            $expected
        );

        Kadence_Blocks::register_assets(true, '');
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
