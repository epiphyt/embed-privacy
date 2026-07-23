<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Shortcodes_Ultimate;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Unit\helper\ManagesAssetFiles;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Shortcodes_Ultimate::class)]
final class ShortcodesUltimateTest extends MockeryTestCase
{
    use ManagesAssetFiles;

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
        // asset exists on disk, so it is registered with the version constant
        $this->makeAssetsAvailable([\EPI_EMBED_PRIVACY_BASE . 'assets/style/shortcodes-ultimate.min.css']);

        expect('wp_register_style')->once()->with(
            'embed-privacy-shortcodes-ultimate',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/shortcodes-ultimate.min.css',
            [],
            \EMBED_PRIVACY_VERSION
        );

        Shortcodes_Ultimate::register_assets(false, '.min');
    }

    public function testRegisterAssetsSkipsMissingAsset(): void
    {
        // asset is not available on disk, so nothing is registered (and no filemtime warning)
        $this->makeAssetsUnavailable([\EPI_EMBED_PRIVACY_BASE . 'assets/style/shortcodes-ultimate.min.css']);

        expect('wp_register_style')->never();

        Shortcodes_Ultimate::register_assets(false, '.min');
    }

    public function testRegisterAssetsUsesFilemtimeWhenDebug(): void
    {
        // build the non-minified file so file_exists() passes and filemtime() does not warn
        $this->makeAssetsAvailable([\EPI_EMBED_PRIVACY_BASE . 'assets/style/shortcodes-ultimate.css']);
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
        $this->restoreAssetFiles();

        tearDown();
        parent::tearDown();
    }
}
