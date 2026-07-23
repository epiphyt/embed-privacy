<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Kadence_Blocks;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Unit\helper\ManagesAssetFiles;

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
    use ManagesAssetFiles;

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
        // asset exists on disk, so it is registered with the version constant
        $this->makeAssetsAvailable([\EPI_EMBED_PRIVACY_BASE . 'assets/style/kadence-blocks.min.css']);

        expect('wp_register_style')->once()->with(
            'embed-privacy-kadence-blocks',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/kadence-blocks.min.css',
            [],
            \EMBED_PRIVACY_VERSION
        );

        Kadence_Blocks::register_assets(false, '.min');
    }

    public function testRegisterAssetsSkipsMissingAsset(): void
    {
        // asset is not available on disk, so nothing is registered (and no filemtime warning)
        $this->makeAssetsUnavailable([\EPI_EMBED_PRIVACY_BASE . 'assets/style/kadence-blocks.min.css']);

        expect('wp_register_style')->never();

        Kadence_Blocks::register_assets(false, '.min');
    }

    public function testRegisterAssetsUsesFilemtimeWhenDebug(): void
    {
        // build the non-minified file so file_exists() passes and filemtime() does not warn
        $this->makeAssetsAvailable([\EPI_EMBED_PRIVACY_BASE . 'assets/style/kadence-blocks.css']);
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
        $this->restoreAssetFiles();

        tearDown();
        parent::tearDown();
    }
}
