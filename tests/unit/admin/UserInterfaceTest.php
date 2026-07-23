<?php

declare(strict_types=1);

namespace Tests\Unit\admin;

use epiphyt\Embed_Privacy\admin\User_Interface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(User_Interface::class)]
final class UserInterfaceTest extends MockeryTestCase
{
    /**
     * @var string[] Asset files created by a test to satisfy the file_exists() guard
     */
    private $createdAssets = [];

    /**
     * @var string[] Asset files moved aside by a test to force their absence
     */
    private $movedAssets = [];

    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    /**
     * The admin asset files the source guards with file_exists() (minified variant).
     *
     * @return string[] Absolute asset paths
     */
    private function assetTargets(): array
    {
        return [
            \EPI_EMBED_PRIVACY_BASE . 'assets/js/admin/image-upload.min.js',
            \EPI_EMBED_PRIVACY_BASE . 'assets/style/embed-privacy-admin.min.css',
            \EPI_EMBED_PRIVACY_BASE . 'assets/js/admin/clipboard.min.js',
            \EPI_EMBED_PRIVACY_BASE . 'assets/style/settings.min.css',
        ];
    }

    /**
     * Ensure the guarded asset files exist on disk (creating only missing ones).
     */
    private function makeAssetsAvailable(): void
    {
        foreach ($this->assetTargets() as $path) {
            if (\file_exists($path)) {
                continue;
            }

            if (!\is_dir(\dirname($path))) {
                \mkdir(\dirname($path), 0755, true);
            }

            \file_put_contents($path, '');
            $this->createdAssets[] = $path;
        }
    }

    /**
     * Ensure the guarded asset files are absent (moving any existing ones aside).
     */
    private function makeAssetsUnavailable(): void
    {
        foreach ($this->assetTargets() as $path) {
            if (!\file_exists($path)) {
                continue;
            }

            \rename($path, $path . '.epbak');
            $this->movedAssets[] = $path;
        }
    }

    public function testInitRegistersHooks(): void
    {
        User_Interface::init();

        $this->assertNotFalse(hasAction('admin_enqueue_scripts', [User_Interface::class, 'enqueue_assets']));
        $this->assertNotFalse(hasFilter('plugin_row_meta', [User_Interface::class, 'add_meta_link']));
    }

    public function testAddMetaLinkBailsForOtherPlugins(): void
    {
        stubs([
            'plugin_basename' => 'embed-privacy/embed-privacy.php',
        ]);

        $input = ['deactivate' => '<a href="#">Deactivate</a>'];

        // a foreign plugin file must be returned untouched
        $this->assertSame($input, User_Interface::add_meta_link($input, 'other-plugin/other-plugin.php'));
    }

    public function testAddMetaLinkAppendsDocumentationForOwnPlugin(): void
    {
        stubTranslationFunctions();
        stubs([
            'plugin_basename' => 'embed-privacy/embed-privacy.php',
        ]);

        $input = ['deactivate' => '<a href="#">Deactivate</a>'];
        $result = User_Interface::add_meta_link($input, 'embed-privacy/embed-privacy.php');

        $this->assertCount(2, $result);
        $this->assertContains($input['deactivate'], $result);
        $documentation = \end($result);
        $this->assertStringContainsString('https://docs.epiph.yt/embed-privacy/', $documentation);
        $this->assertStringContainsString(\rawurlencode(\EMBED_PRIVACY_VERSION), $documentation);
        $this->assertStringContainsString('Documentation', $documentation);
    }

    public function testEnqueueAssetsReturnsEarlyWithoutScreen(): void
    {
        // a closure is required: stubs() treats a literal null as "return first arg"
        stubs([
            'get_current_screen' => static function () {
                return null;
            },
        ]);

        // no screen means no assets are enqueued
        expect('wp_enqueue_script')->never();
        expect('wp_enqueue_style')->never();

        User_Interface::enqueue_assets('post.php');
    }

    public function testEnqueueAssetsForPostEditScreen(): void
    {
        $screen = Mockery::mock('WP_Screen');
        $screen->id = 'post';

        stubs([
            'get_current_screen' => $screen,
        ]);
        // assets exist on disk, so they are enqueued (version is their filemtime)
        $this->makeAssetsAvailable();

        expect('wp_enqueue_script')
            ->once()
            ->with(
                'embed-privacy-admin-image-upload',
                Mockery::any(),
                ['jquery'],
                Mockery::any(),
                true
            );
        expect('wp_enqueue_style')
            ->once()
            ->with('embed-privacy-admin-style', Mockery::any(), [], Mockery::any());

        User_Interface::enqueue_assets('post.php');
    }

    public function testEnqueueAssetsSkipsMissingAssetsOnPostEditScreen(): void
    {
        $screen = Mockery::mock('WP_Screen');
        $screen->id = 'post';

        stubs([
            'get_current_screen' => $screen,
        ]);
        // assets are not available on disk, so the guard skips enqueue (and filemtime)
        $this->makeAssetsUnavailable();

        expect('wp_enqueue_script')->never();
        expect('wp_enqueue_style')->never();

        User_Interface::enqueue_assets('post.php');
    }

    public function testEnqueueAssetsForEmbedScreen(): void
    {
        $screen = Mockery::mock('WP_Screen');
        $screen->id = 'epi_embed';

        stubs([
            'get_current_screen' => $screen,
        ]);
        $this->makeAssetsAvailable();

        // the embed post type screen also triggers the image upload assets
        expect('wp_enqueue_script')->once()->with(
            'embed-privacy-admin-image-upload',
            Mockery::any(),
            ['jquery'],
            Mockery::any(),
            true
        );
        expect('wp_enqueue_style')->once();

        User_Interface::enqueue_assets('index.php');
    }

    public function testEnqueueAssetsForSettingsScreen(): void
    {
        $screen = Mockery::mock('WP_Screen');
        $screen->id = 'settings_page_embed_privacy';

        stubTranslationFunctions();
        stubs([
            'get_current_screen' => $screen,
        ]);
        $this->makeAssetsAvailable();

        expect('wp_enqueue_script')->once()->with(
            'embed-privacy-admin-clipboard',
            Mockery::any(),
            [],
            Mockery::any(),
            true
        );
        expect('wp_localize_script')->once();
        expect('wp_enqueue_style')->once()->with(
            'embed-privacy-admin-settings',
            Mockery::any(),
            [],
            Mockery::any()
        );

        User_Interface::enqueue_assets('settings_page_embed_privacy');
    }

    public function testEnqueueAssetsSkipsMissingAssetsOnSettingsScreen(): void
    {
        $screen = Mockery::mock('WP_Screen');
        $screen->id = 'settings_page_embed_privacy';

        stubs([
            'get_current_screen' => $screen,
        ]);
        // no built assets => no script/style/localize and no filemtime warning
        $this->makeAssetsUnavailable();

        expect('wp_enqueue_script')->never();
        expect('wp_localize_script')->never();
        expect('wp_enqueue_style')->never();

        User_Interface::enqueue_assets('settings_page_embed_privacy');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdAssets as $path) {
            if (\file_exists($path)) {
                \unlink($path);
            }
        }

        foreach ($this->movedAssets as $path) {
            if (\file_exists($path . '.epbak')) {
                \rename($path . '.epbak', $path);
            }
        }

        $this->createdAssets = [];
        $this->movedAssets = [];

        tearDown();
        parent::tearDown();
    }
}
