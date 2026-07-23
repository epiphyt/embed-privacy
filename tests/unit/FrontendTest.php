<?php

declare(strict_types=1);

namespace Tests\Unit;

use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\Frontend;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Frontend::class)]
final class FrontendTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        Embed_Privacy::$instance = null;
    }

    public function testInitRegistersInitAction(): void
    {
        $frontend = new Frontend();
        $frontend->init();

        $this->assertNotFalse(hasAction('init', [$frontend, 'register_assets']));
    }

    public function testRegisterAssetsReturnsEarlyInAdmin(): void
    {
        stubs([
            'is_admin' => true,
            'wp_doing_ajax' => false,
            'wp_doing_cron' => false,
        ]);

        // nothing should be registered when in admin context
        expect('wp_register_style')->never();
        expect('wp_register_script')->never();

        (new Frontend())->register_assets();
    }

    public function testRegisterAssetsReturnsEarlyDuringAjax(): void
    {
        stubs([
            'is_admin' => false,
            'wp_doing_ajax' => true,
            'wp_doing_cron' => false,
        ]);

        expect('wp_register_style')->never();

        (new Frontend())->register_assets();
    }

    public function testRegisterAssetsReturnsEarlyDuringCron(): void
    {
        stubs([
            'is_admin' => false,
            'wp_doing_ajax' => false,
            'wp_doing_cron' => true,
        ]);

        expect('wp_register_style')->never();

        (new Frontend())->register_assets();
    }

    public function testRegisterAssetsRegistersStyleAndScript(): void
    {
        stubs([
            'is_admin' => false,
            'wp_doing_ajax' => false,
            'wp_doing_cron' => false,
            'get_option' => false,
            // keep Amp::is_amp() deterministically false even if another test leaked the function
            'is_amp_endpoint' => false,
        ]);

        expect('wp_register_style')
            ->once()
            ->with(
                'embed-privacy',
                \EPI_EMBED_PRIVACY_URL . 'assets/style/embed-privacy.min.css',
                [],
                \EMBED_PRIVACY_VERSION
            );

        // Amp::is_amp() is false (is_amp_endpoint() undefined), so the script is registered
        expect('wp_register_script')
            ->once()
            ->with(
                'embed-privacy',
                \EPI_EMBED_PRIVACY_URL . 'assets/js/embed-privacy.min.js',
                [],
                \EMBED_PRIVACY_VERSION,
                ['strategy' => 'defer']
            );

        // force loading disabled => print_assets() is not triggered
        expect('wp_enqueue_script')->never();

        (new Frontend())->register_assets();

        $this->assertSame(1, \did_action('embed_privacy_register_assets'));
    }

    public function testRegisterAssetsForcesPrintingWhenOptionEnabled(): void
    {
        stubs([
            'is_admin' => false,
            'wp_doing_ajax' => false,
            'wp_doing_cron' => false,
            'wp_register_style' => true,
            'wp_register_script' => true,
            'get_option' => 'yes',
            'wp_localize_script' => null,
            // keep Amp::is_amp() deterministically false even if another test leaked the function
            'is_amp_endpoint' => false,
        ]);

        $embedPrivacy = Mockery::mock(Embed_Privacy::class);
        $embedPrivacy->shouldReceive('get_cookie')->andReturn([]);
        Embed_Privacy::$instance = $embedPrivacy;

        // print_assets() must run because the force option is set to "yes"
        expect('wp_enqueue_script')->once()->with('embed-privacy');
        expect('wp_enqueue_style')->once()->with('embed-privacy');

        (new Frontend())->register_assets();

        $this->assertSame(1, \did_action('embed_privacy_print_assets'));
    }

    public function testPrintAssetsEnqueuesAndLocalizes(): void
    {
        $embedPrivacy = Mockery::mock(Embed_Privacy::class);
        $embedPrivacy->shouldReceive('get_cookie')->andReturn([]);
        Embed_Privacy::$instance = $embedPrivacy;

        expect('wp_enqueue_script')->once()->with('embed-privacy');
        expect('wp_enqueue_style')->once()->with('embed-privacy');
        expect('wp_localize_script')
            ->once()
            ->with('embed-privacy', 'embedPrivacy', ['alwaysActiveProviders' => []]);

        (new Frontend())->print_assets();

        $this->assertSame(1, \did_action('embed_privacy_print_assets'));
    }

    public function testPrintAssetsIsIdempotent(): void
    {
        $embedPrivacy = Mockery::mock(Embed_Privacy::class);
        $embedPrivacy->shouldReceive('get_cookie')->andReturn([]);
        Embed_Privacy::$instance = $embedPrivacy;

        stubs([
            'wp_enqueue_script' => null,
            'wp_enqueue_style' => null,
            'wp_localize_script' => null,
        ]);

        $frontend = new Frontend();
        $frontend->print_assets();

        // the second call must return early before firing the action again
        $frontend->print_assets();

        $this->assertSame(1, \did_action('embed_privacy_print_assets'));
    }

    protected function tearDown(): void
    {
        Embed_Privacy::$instance = null;

        tearDown();
        parent::tearDown();
    }
}
