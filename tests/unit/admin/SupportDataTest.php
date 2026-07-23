<?php

declare(strict_types=1);

namespace Tests\Unit\admin;

use epiphyt\Embed_Privacy\admin\Support_Data;
use epiphyt\Embed_Privacy\data\Providers;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use ReflectionProperty;

use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Support_Data::class)]
final class SupportDataTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        Providers::$instance = null;
    }

    /**
     * Invoke a private static method of Support_Data.
     *
     * @param   string  $method Method name
     * @param   array   $args Arguments
     * @return  mixed Method return value
     */
    private function invokePrivate(string $method, array $args = [])
    {
        $reflection = new ReflectionMethod(Support_Data::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, ...$args);
    }

    public function testGetHeadingUnderlinesTitle(): void
    {
        $this->assertSame(
            'Title' . \PHP_EOL . '-----' . \PHP_EOL,
            $this->invokePrivate('get_heading', ['Title'])
        );
    }

    public function testGetHeadingUsesMultibyteLength(): void
    {
        // five multibyte characters must produce exactly five dashes
        $this->assertSame(
            'Übärö' . \PHP_EOL . '-----' . \PHP_EOL,
            $this->invokePrivate('get_heading', ['Übärö'])
        );
    }

    public function testGetPluginDataListsActivePlugins(): void
    {
        stubTranslationFunctions();
        stubs([
            'wp_get_active_and_valid_plugins' => ['/plugins/my-plugin/my-plugin.php'],
            'get_plugin_data' => [
                'Name' => 'My Plugin',
                'PluginURI' => 'https://example.com/plugin',
                'Version' => '1.2.3',
            ],
        ]);

        $output = $this->invokePrivate('get_plugin_data');

        $this->assertStringContainsString('Active plugins', $output);
        $this->assertStringContainsString('My Plugin', $output);
        $this->assertStringContainsString('https://example.com/plugin', $output);
        $this->assertStringContainsString('1.2.3', $output);
    }

    public function testGetPluginDataWithoutPluginsRendersHeadingOnly(): void
    {
        stubTranslationFunctions();
        stubs([
            'wp_get_active_and_valid_plugins' => [],
        ]);

        $output = $this->invokePrivate('get_plugin_data');

        $this->assertStringContainsString('Active plugins', $output);
        $this->assertStringContainsString('array (', $output);
    }

    public function testGetThemeDataListsActiveThemes(): void
    {
        $theme = Mockery::mock();
        $theme->shouldReceive('get')->with('Name')->andReturn('My Theme');
        $theme->shouldReceive('get')->with('ThemeURI')->andReturn('https://example.com/theme');
        $theme->shouldReceive('get')->with('Version')->andReturn('2.0.0');

        stubTranslationFunctions();
        stubs([
            'wp_get_active_and_valid_themes' => ['/themes/my-theme'],
            'wp_get_theme' => $theme,
        ]);

        $output = $this->invokePrivate('get_theme_data');

        $this->assertStringContainsString('Active themes', $output);
        $this->assertStringContainsString('My Theme', $output);
        $this->assertStringContainsString('https://example.com/theme', $output);
        $this->assertStringContainsString('2.0.0', $output);
    }

    public function testGetProviderDataListsProviders(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('is_disabled')->andReturn(false);
        $provider->shouldReceive('get_name')->andReturn('youtube');
        $provider->shouldReceive('get_pattern')->andReturn('/youtube\.com/');
        $provider->shouldReceive('get_title')->andReturn('YouTube');

        // seed the Providers singleton with a controlled list so no query runs
        $providers = Providers::get_instance();
        $listProperty = new ReflectionProperty(Providers::class, 'list');
        $listProperty->setAccessible(true);
        $listProperty->setValue($providers, ['all' => [$provider]]);

        stubTranslationFunctions();

        $output = $this->invokePrivate('get_provider_data');

        $this->assertStringContainsString('Active providers', $output);
        $this->assertStringContainsString('youtube', $output);
        $this->assertStringContainsString('YouTube', $output);
        // the pattern is var_export()ed, so the single backslash is doubled in the output
        $this->assertStringContainsString('/youtube\\\\.com/', $output);
    }

    public function testGetVersionUsesWpGetWpVersionWhenAvailable(): void
    {
        stubs([
            'wp_get_wp_version' => '6.7.1',
        ]);

        $this->assertSame('6.7.1', $this->invokePrivate('get_version'));
    }

    protected function tearDown(): void
    {
        Providers::$instance = null;

        tearDown();
        parent::tearDown();
    }
}
