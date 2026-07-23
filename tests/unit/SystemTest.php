<?php

declare(strict_types=1);

namespace Tests\Unit;

use epiphyt\Embed_Privacy\System;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(System::class)]
final class SystemTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testIsPluginActiveReturnsTrueWhenCoreReportsActive(): void
    {
        // defining the stub also makes function_exists('is_plugin_active') true,
        // so the wp-admin include branch is skipped
        expect('is_plugin_active')
            ->once()
            ->with('akismet/akismet.php')
            ->andReturn(true);

        $this->assertTrue(System::is_plugin_active('akismet/akismet.php'));
    }

    public function testIsPluginActiveReturnsFalseWhenCoreReportsInactive(): void
    {
        expect('is_plugin_active')
            ->once()
            ->with('some-plugin/some-plugin.php')
            ->andReturn(false);

        $this->assertFalse(System::is_plugin_active('some-plugin/some-plugin.php'));
    }

    public function testIsPluginActivePassesPluginArgumentThrough(): void
    {
        expect('is_plugin_active')
            ->once()
            ->with('classic-editor/classic-editor.php')
            ->andReturn(true);

        $this->assertTrue(System::is_plugin_active('classic-editor/classic-editor.php'));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
