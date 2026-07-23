<?php

declare(strict_types=1);

namespace Tests\Unit\handler;

use epiphyt\Embed_Privacy\handler\Theme;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Theme::class)]
final class ThemeTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    /**
     * Stub wp_get_theme() to return a theme with the given name and template.
     */
    private function stubTheme(string $name, string $template): void
    {
        $theme = Mockery::mock();
        $theme->shouldReceive('get')->with('Name')->andReturn($name);
        $theme->shouldReceive('get')->with('Template')->andReturn($template);

        expect('wp_get_theme')->andReturn($theme);
    }

    public function testIsMatchesThemeName(): void
    {
        $this->stubTheme('Twenty Twenty-Four', 'twentytwentyfour');

        $this->assertTrue(Theme::is('Twenty Twenty-Four'));
    }

    public function testIsMatchesTemplate(): void
    {
        // a child theme has a different name but the parent template matches
        $this->stubTheme('My Child Theme', 'astra');

        $this->assertTrue(Theme::is('astra'));
    }

    public function testIsIsCaseInsensitive(): void
    {
        $this->stubTheme('Astra', 'astra');

        $this->assertTrue(Theme::is('ASTRA'));
    }

    public function testIsReturnsFalseWithoutMatch(): void
    {
        $this->stubTheme('Twenty Twenty-Four', 'twentytwentyfour');

        $this->assertFalse(Theme::is('generatepress'));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
