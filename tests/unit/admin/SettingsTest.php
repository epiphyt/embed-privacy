<?php

declare(strict_types=1);

namespace Tests\Unit\admin;

use epiphyt\Embed_Privacy\admin\Settings;
use epiphyt\Embed_Privacy\Embed_Privacy;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Settings::class)]
final class SettingsTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        Embed_Privacy::$instance = null;
    }

    public function testInitRegistersHooks(): void
    {
        Settings::init();

        $this->assertNotFalse(hasAction('admin_init', [Settings::class, 'register']));
        $this->assertNotFalse(hasAction('admin_menu', [Settings::class, 'register_menu']));
    }

    public function testGetPageReturnsEarlyWithoutCapability(): void
    {
        stubs([
            'current_user_can' => false,
        ]);

        // without the capability nothing is rendered
        expect('settings_errors')->never();
        expect('settings_fields')->never();

        Settings::get_page();
    }

    public function testRegisterAddsSectionFieldsAndSettings(): void
    {
        // Settings::register() asks the Embed_Privacy singleton for provider titles
        Embed_Privacy::$instance = (object) [
            'thumbnail' => new class {
                public function get_provider_titles(): string
                {
                    return 'YouTube, Vimeo';
                }
            },
        ];

        stubTranslationFunctions();
        stubs([
            '__return_empty_string' => '',
            'wp_sprintf' => '',
        ]);

        expect('add_settings_section')->once();
        expect('add_settings_field')->times(6);
        expect('register_setting')->times(6);

        Settings::register();
    }

    public function testRegisterMenuAddsSubmenuPage(): void
    {
        stubTranslationFunctions();

        expect('add_submenu_page')
            ->once()
            ->with(
                'options-general.php',
                \Mockery::any(),
                \Mockery::any(),
                Settings::CAPABILITY,
                'embed_privacy',
                [Settings::class, 'get_page']
            );

        Settings::register_menu();
    }

    protected function tearDown(): void
    {
        Embed_Privacy::$instance = null;

        tearDown();
        parent::tearDown();
    }
}
