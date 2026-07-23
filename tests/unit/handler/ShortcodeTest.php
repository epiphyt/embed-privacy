<?php

declare(strict_types=1);

namespace Tests\Unit\handler;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\handler\Shortcode;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Filters\has;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Shortcode::class)]
final class ShortcodeTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        // reset singletons so state does not leak between tests
        Embed_Privacy::$instance = null;
        Providers::$instance = null;

        stubEscapeFunctions();
        stubTranslationFunctions();

        // clean cookie super global between tests
        unset($_COOKIE['embed-privacy']);
    }

    /**
     * Build a Provider with the given name and title, bypassing the
     * WordPress-heavy constructor.
     */
    private function provider(string $name, string $title): Provider
    {
        $reflection = new \ReflectionClass(Provider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        foreach (['name' => $name, 'title' => $title] as $key => $value) {
            $property = $reflection->getProperty($key);
            $property->setAccessible(true);
            $property->setValue($provider, $value);
        }

        return $provider;
    }

    /**
     * Install an Embed_Privacy singleton (built without its constructor),
     * optionally with the given frontend object.
     */
    private function installEmbedPrivacy(?object $frontend = null): void
    {
        $reflection = new \ReflectionClass(Embed_Privacy::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        if ($frontend !== null) {
            $instance->frontend = $frontend;
        }

        Embed_Privacy::$instance = $instance;
    }

    /**
     * Install a Providers singleton (built without its constructor) whose
     * 'all' list is the given provider list.
     *
     * @param \epiphyt\Embed_Privacy\embed\Provider[] $providers
     */
    private function installProviders(array $providers): void
    {
        $reflection = new \ReflectionClass(Providers::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('list');
        $property->setAccessible(true);
        $property->setValue($instance, ['all' => $providers]);

        Providers::$instance = $instance;
    }

    public function testInitRegistersShortcodeAndFilter(): void
    {
        expect('add_shortcode')->once()->with('embed_privacy_opt_out', Mockery::type('array'));

        $shortcode = new Shortcode();
        $shortcode->init();

        $this->assertNotFalse(has('the_content'));
    }

    public function testGetIgnoredReturnsDefaults(): void
    {
        $shortcode = new Shortcode();

        $this->assertSame(
            ['embed_privacy_opt_out', 'grw'],
            $shortcode->get_ignored()
        );
    }

    public function testGetIgnoredIsFilterable(): void
    {
        expectApplied('embed_privacy_ignored_shortcodes')->andReturn(['custom_shortcode']);

        $shortcode = new Shortcode();

        $this->assertSame(['custom_shortcode'], $shortcode->get_ignored());
    }

    public function testOptOutReturnsEmptyStringWithoutProviders(): void
    {
        stubs([
            'shortcode_atts' => static function ($defaults, $atts) {
                return \array_merge($defaults, (array) $atts);
            },
        ]);
        $this->installEmbedPrivacy();
        $this->installProviders([]);

        $this->assertSame('', Shortcode::opt_out([]));
    }

    public function testOptOutRendersProviderMarkupHidden(): void
    {
        stubs([
            'shortcode_atts' => static function ($defaults, $atts) {
                return \array_merge($defaults, (array) $atts);
            },
            'checked' => '',
        ]);
        $this->installEmbedPrivacy();
        $this->installProviders([$this->provider('youtube', 'YouTube')]);

        $output = Shortcode::opt_out([]);

        $this->assertStringContainsString('embed-privacy-opt-out', $output);
        $this->assertStringContainsString('data-show-all="0"', $output);
        // headline and subline are rendered
        $this->assertStringContainsString('<h3>Embed providers</h3>', $output);
        // with show_all off and no enabled providers, the entry is hidden
        $this->assertStringContainsString('is-hidden', $output);
        $this->assertStringContainsString('data-embed-provider="youtube"', $output);
        $this->assertStringContainsString('Load all embeds from YouTube', $output);
    }

    public function testOptOutShowAllRendersVisibleProvider(): void
    {
        stubs([
            'shortcode_atts' => static function ($defaults, $atts) {
                return \array_merge($defaults, (array) $atts);
            },
            'checked' => '',
        ]);
        $this->installEmbedPrivacy();
        $this->installProviders([$this->provider('vimeo', 'Vimeo')]);

        $output = Shortcode::opt_out(['show_all' => 1]);

        $this->assertStringContainsString('data-show-all="1"', $output);
        // with show_all on, providers are not hidden
        $this->assertStringNotContainsString('is-hidden', $output);
        $this->assertStringContainsString('data-embed-provider="vimeo"', $output);
    }

    public function testPrintAssetsForShortcodePrintsWhenShortcodePresent(): void
    {
        stubs(['has_shortcode' => true]);

        $frontend = Mockery::mock();
        $frontend->shouldReceive('print_assets')->once();
        $this->installEmbedPrivacy($frontend);

        $content = 'content with [embed_privacy_opt_out]';

        $this->assertSame($content, (new Shortcode())->print_assets_for_shortcode($content));
    }

    public function testPrintAssetsForShortcodeSkipsWhenAbsent(): void
    {
        stubs(['has_shortcode' => false]);

        $frontend = Mockery::mock();
        $frontend->shouldReceive('print_assets')->never();
        $this->installEmbedPrivacy($frontend);

        $content = 'plain content';

        $this->assertSame($content, (new Shortcode())->print_assets_for_shortcode($content));
    }

    protected function tearDown(): void
    {
        Embed_Privacy::$instance = null;
        Providers::$instance = null;

        tearDown();
        parent::tearDown();
    }
}
