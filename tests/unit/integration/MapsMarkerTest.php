<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\integration\Maps_Marker;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Maps_Marker::class)]
final class MapsMarkerTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        Embed_Privacy::$instance = null;
        Providers::$instance = null;
    }

    public function testInitRegistersHooks(): void
    {
        Maps_Marker::init();

        $this->assertNotFalse(
            hasFilter('do_shortcode_tag', [Maps_Marker::class, 'replace'])
        );
        $this->assertNotFalse(
            hasFilter('embed_privacy_overlay_provider', [Maps_Marker::class, 'set_provider'])
        );
    }

    public function testReplaceReturnsOutputForOtherShortcode(): void
    {
        $output = '<p>some other shortcode output</p>';

        // a non-mapsmarker tag must not trigger the Embed_Privacy singleton
        $this->assertSame($output, Maps_Marker::replace($output, 'gallery'));
    }

    public function testReplaceReturnsOutputForIgnoredRequest(): void
    {
        $instance = Mockery::mock(Embed_Privacy::class);
        $instance->is_ignored_request = true;
        Embed_Privacy::$instance = $instance;

        $output = '<div class="mmp-map" style="height: 300px; width: 100%;"></div>';

        $this->assertSame($output, Maps_Marker::replace($output, 'mapsmarker'));
    }

    public function testReplaceReturnsOutputWhenCacheDisabled(): void
    {
        // is_ignored_request is false, so get_dimensions() runs and Replacer::replace_oembed()
        // is reached; with use_cache disabled the replacer returns the output unchanged
        $instance = Mockery::mock(Embed_Privacy::class);
        $instance->is_ignored_request = false;
        $instance->use_cache = false;
        Embed_Privacy::$instance = $instance;

        $output = '<div class="mmp-map" style="height: 300px; width: 400px;"></div>';

        $this->assertSame($output, Maps_Marker::replace($output, 'mapsmarker'));
    }

    public function testSetProviderReturnsProviderUnchangedForUnrelatedContent(): void
    {
        $provider = Mockery::mock();

        // no Maps Marker markers -> Providers singleton is not touched
        $this->assertSame(
            $provider,
            Maps_Marker::set_provider($provider, '<p>Just some unrelated content.</p>')
        );
    }

    public function testSetProviderResolvesProviderForMapsMarkerClass(): void
    {
        $resolved = Mockery::mock();
        // Providers is final, so use a partial mock over a real instance
        $providers = Mockery::mock(new Providers());
        $providers->shouldReceive('get_by_name')->once()->with('maps-marker-pro')->andReturn($resolved);
        Providers::$instance = $providers;

        $this->assertSame(
            $resolved,
            Maps_Marker::set_provider(null, '<div class="maps-marker-pro"></div>')
        );
    }

    public function testSetProviderResolvesProviderForMapsMarkerShortcode(): void
    {
        $resolved = Mockery::mock();
        $providers = Mockery::mock(new Providers());
        $providers->shouldReceive('get_by_name')->once()->with('maps-marker-pro')->andReturn($resolved);
        Providers::$instance = $providers;

        $this->assertSame(
            $resolved,
            Maps_Marker::set_provider(null, 'text [mapsmarker marker="1"] text')
        );
    }

    protected function tearDown(): void
    {
        tearDown();
        Embed_Privacy::$instance = null;
        Providers::$instance = null;
        parent::tearDown();
    }
}
