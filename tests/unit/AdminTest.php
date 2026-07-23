<?php

declare(strict_types=1);

namespace Tests\Unit;

use epiphyt\Embed_Privacy\Admin;
use epiphyt\Embed_Privacy\Embed_Privacy;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Admin::class)]
final class AdminTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        $this->resetInstance();
        stubTranslationFunctions();
    }

    /**
     * Reset the private Admin singleton between tests.
     */
    private function resetInstance(): void
    {
        $instance = new ReflectionProperty(Admin::class, 'instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $first = Admin::get_instance();

        $this->assertInstanceOf(Admin::class, $first);
        $this->assertSame($first, Admin::get_instance());
    }

    public function testConstructorRegistersInstance(): void
    {
        $admin = new Admin();

        $this->assertSame($admin, Admin::get_instance());
    }

    public function testInitIsMarkedAsDoingItWrong(): void
    {
        expect('_doing_it_wrong')->once();

        Admin::get_instance()->init();
    }

    public function testAddMetaLinkDelegatesToUserInterface(): void
    {
        stubs([
            'plugin_basename' => 'embed-privacy/embed-privacy.php',
        ]);

        expect('_doing_it_wrong')->once();

        $input = ['deactivate' => '<a href="#">Deactivate</a>'];

        // delegated call bails on a foreign plugin file and returns the input
        $this->assertSame($input, Admin::get_instance()->add_meta_link($input, 'foreign/foreign.php'));
    }

    public function testDisallowDeletingSystemEmbedsDelegatesToFields(): void
    {
        expect('_doing_it_wrong')->once();

        $caps = ['read'];

        // a non delete_post capability is returned unchanged by the delegate
        $this->assertSame(
            $caps,
            Admin::get_instance()->disallow_deleting_system_embeds($caps, 'edit_post', 1, [5])
        );
    }

    public function testGetFieldIsMarkedAsDoingItWrong(): void
    {
        stubs([
            'wp_parse_args' => static function ($value, $default) {
                return \array_merge($default, $value);
            },
        ]);

        expect('_doing_it_wrong')->once();

        // empty name/title makes the delegated Field::get() return without output
        Admin::get_instance()->get_field(['name' => '', 'title' => '']);
    }

    public function testInitSettingsIsMarkedAsDoingItWrong(): void
    {
        Embed_Privacy::$instance = (object) [
            'thumbnail' => new class {
                public function get_provider_titles(): string
                {
                    return 'YouTube';
                }
            },
        ];

        stubs([
            '__return_empty_string' => '',
            'wp_sprintf' => '',
            'add_settings_section' => null,
            'add_settings_field' => null,
            'register_setting' => null,
        ]);

        expect('_doing_it_wrong')->once();

        Admin::get_instance()->init_settings();

        Embed_Privacy::$instance = null;
    }

    public function testOptionsHtmlIsMarkedAsDoingItWrong(): void
    {
        stubs([
            'current_user_can' => false,
        ]);

        expect('_doing_it_wrong')->once();

        // the delegated Settings::get_page() returns early without the capability
        Admin::get_instance()->options_html();
    }

    public function testRegisterMenuIsMarkedAsDoingItWrong(): void
    {
        stubs([
            'add_submenu_page' => null,
        ]);

        expect('_doing_it_wrong')->once();

        Admin::get_instance()->register_menu();
    }

    protected function tearDown(): void
    {
        Embed_Privacy::$instance = null;

        $this->resetInstance();

        tearDown();
        parent::tearDown();
    }
}
