<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Polylang;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Polylang::class)]
final class PolylangTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testInitRegistersHooks(): void
    {
        Polylang::init();

        $this->assertNotFalse(
            hasFilter('embed_privacy_provider_name', [Polylang::class, 'sanitize_name'])
        );
        $this->assertNotFalse(
            hasFilter('pll_get_post_types', [Polylang::class, 'register_post_type'])
        );
    }

    public function testRegisterPostTypeAddsEmbedTypeOnFrontend(): void
    {
        $result = Polylang::register_post_type([], false);

        $this->assertArrayHasKey('epi_embed', $result);
        $this->assertSame('epi_embed', $result['epi_embed']);
    }

    public function testRegisterPostTypeRemovesEmbedTypeOnSettings(): void
    {
        $result = Polylang::register_post_type(['epi_embed' => 'epi_embed', 'post' => 'post'], true);

        $this->assertArrayNotHasKey('epi_embed', $result);
        $this->assertArrayHasKey('post', $result);
    }

    public function testSanitizeNameReturnsUnchangedWhenPluginInactive(): void
    {
        stubs(['is_plugin_active' => false]);

        $this->assertSame('youtube-en', Polylang::sanitize_name('youtube-en'));
    }

    // separate process so pll_current_language() is genuinely undefined regardless of
    // other tests that define it via Brain Monkey (function definitions leak per-process)
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSanitizeNameReturnsUnchangedWithoutPolylangFunction(): void
    {
        // plugin reports active, but pll_current_language() is undefined -> guard fails
        stubs(['is_plugin_active' => true]);

        $this->assertSame('youtube-en', Polylang::sanitize_name('youtube-en'));
    }

    public function testSanitizeNameStripsLanguageSuffix(): void
    {
        stubs(['is_plugin_active' => true]);
        when('pll_current_language')->justReturn('en');

        $this->assertSame('youtube', Polylang::sanitize_name('youtube-en'));
    }

    public function testSanitizeNameKeepsNameWithoutMatchingSuffix(): void
    {
        stubs(['is_plugin_active' => true]);
        when('pll_current_language')->justReturn('de');

        // name does not end with the current language suffix, so it is returned unchanged
        $this->assertSame('youtube-en', Polylang::sanitize_name('youtube-en'));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
