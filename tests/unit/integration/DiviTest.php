<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Divi;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Tests for the Divi integration.
 *
 * Covers hook registration, the wp_kses allow-list transform, asset
 * (de-)registration around dynamic content, and the guard branches of
 * replace_google_maps(). The full Google Maps replacement path depends on
 * Divi's ET_Builder_Module_Map class and is not exercised.
 */
#[CoversClass(Divi::class)]
final class DiviTest extends MockeryTestCase
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
        (new Divi())->init();

        $this->assertNotFalse(
            hasAction('embed_privacy_register_assets', [Divi::class, 'register_assets'])
        );
        $this->assertNotFalse(
            hasFilter('embed_privacy_print_assets', [Divi::class, 'enqueue_assets'])
        );
        $this->assertNotFalse(
            hasFilter('et_builder_resolve_dynamic_content', [Divi::class, 'add_dynamic_content_filter'])
        );
        $this->assertNotFalse(
            hasFilter('et_builder_resolve_dynamic_content', [Divi::class, 'remove_dynamic_content_filter'])
        );
        $this->assertNotFalse(
            hasFilter('et_module_process_display_conditions', [Divi::class, 'replace_google_maps'])
        );
    }

    public function testAddDynamicContentFilterRegistersKsesFilterAndReturnsContent(): void
    {
        $result = Divi::add_dynamic_content_filter('the content');

        $this->assertSame('the content', $result);
        $this->assertNotFalse(
            hasFilter('wp_kses_allowed_html', [Divi::class, 'allow_script_in_post'])
        );
    }

    public function testRemoveDynamicContentFilterReturnsContent(): void
    {
        // remove_filter is intercepted by Brain Monkey; only the return matters here
        $this->assertSame('the content', Divi::remove_dynamic_content_filter('the content'));
    }

    public function testAllowScriptInPostAddsInputAndScriptForPostContext(): void
    {
        $result = Divi::allow_script_in_post([], 'post');

        $this->assertArrayHasKey('input', $result);
        $this->assertArrayHasKey('script', $result);
        $this->assertSame(['type' => true], $result['script']);
        $this->assertTrue($result['input']['class']);
    }

    public function testAllowScriptInPostKeepsExistingInputDefinition(): void
    {
        $existing = ['input' => ['id' => true]];

        $result = Divi::allow_script_in_post($existing, 'post');

        // existing input definition is preserved (not overwritten)
        $this->assertSame(['id' => true], $result['input']);
        $this->assertSame(['type' => true], $result['script']);
    }

    public function testAllowScriptInPostLeavesNonPostContextUnchanged(): void
    {
        $html = ['div' => true];

        $result = Divi::allow_script_in_post($html, 'user_description');

        $this->assertSame($html, $result);
        $this->assertArrayNotHasKey('script', $result);
    }

    public function testEnqueueAssetsWhenDiviActive(): void
    {
        $this->stubTheme('Divi');
        expect('wp_enqueue_script')->once()->with('embed-privacy-divi');
        expect('wp_enqueue_style')->once()->with('embed-privacy-divi');

        Divi::enqueue_assets();
    }

    public function testEnqueueAssetsSkippedForOtherTheme(): void
    {
        $this->stubTheme('Twenty Twenty-Four');
        expect('wp_enqueue_script')->never();
        expect('wp_enqueue_style')->never();

        Divi::enqueue_assets();
    }

    public function testRegisterAssetsUsesVersionConstantWhenNotDebug(): void
    {
        expect('wp_register_script')->once()->with(
            'embed-privacy-divi',
            \EPI_EMBED_PRIVACY_URL . 'assets/js/divi.min.js',
            ['jquery'],
            \EMBED_PRIVACY_VERSION,
            ['strategy' => 'defer']
        );
        expect('wp_register_style')->once()->with(
            'embed-privacy-divi',
            \EPI_EMBED_PRIVACY_URL . 'assets/style/divi.min.css',
            [],
            \EMBED_PRIVACY_VERSION
        );

        Divi::register_assets(false, '.min');
    }

    public function testReplaceGoogleMapsReturnsOutputForBuilderData(): void
    {
        // the render_as_builder_data render method returns early, before any module check
        $this->assertSame(
            'output',
            Divi::replace_google_maps('output', 'render_as_builder_data', null)
        );
    }

    public function testReplaceGoogleMapsReturnsOutputForNonMapModule(): void
    {
        // a module that is not an ET_Builder_Module_Map is ignored
        $module = new \stdClass();

        $this->assertSame(
            'output',
            Divi::replace_google_maps('output', 'render', $module)
        );
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
