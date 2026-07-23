<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Astra;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Astra::class)]
final class AstraTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    /**
     * Stub wp_get_theme() so Theme::is() sees the given theme name.
     */
    private function stubTheme(string $name): void
    {
        $theme = Mockery::mock();
        $theme->shouldReceive('get')->with('Name')->andReturn($name);
        $theme->shouldReceive('get')->with('Template')->andReturn($name);
        when('wp_get_theme')->justReturn($theme);
    }

    public function testInitRegistersHooks(): void
    {
        Astra::init();

        $this->assertNotFalse(
            \Brain\Monkey\Actions\has('embed_privacy_print_assets', [Astra::class, 'enqueue_assets'])
        );
        $this->assertNotFalse(
            \Brain\Monkey\Actions\has('embed_privacy_register_assets', [Astra::class, 'register_assets'])
        );
    }

    public function testEnqueueAssetsWhenAstraActive(): void
    {
        $this->stubTheme('Astra');
        expect('wp_enqueue_style')->once()->with('embed-privacy-astra');

        Astra::enqueue_assets();
    }

    public function testEnqueueAssetsSkippedForOtherTheme(): void
    {
        $this->stubTheme('Twenty Twenty-Four');
        expect('wp_enqueue_style')->never();

        Astra::enqueue_assets();
    }

    public function testRegisterAssetsUsesVersionWhenNotDebug(): void
    {
        expect('wp_register_style')
            ->once()
            ->with(
                'embed-privacy-astra',
                \EPI_EMBED_PRIVACY_URL . 'assets/style/astra.min.css',
                [],
                \EMBED_PRIVACY_VERSION
            );

        Astra::register_assets(false, '.min');
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
