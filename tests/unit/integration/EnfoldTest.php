<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\integration\Enfold;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Tests for the Enfold integration.
 *
 * The Enfold integration tracks the nesting depth of the [av_video] shortcode
 * and swaps embed content depending on that depth, plus builds an overlay for
 * matched video providers.
 */
#[CoversClass(Enfold::class)]
final class EnfoldTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        // reset the shared static depth and provider singleton between tests
        Enfold::$av_video_depth = 0;
        Providers::$instance = null;
    }

    /**
     * Build a real Provider with a controlled name and pattern via reflection.
     *
     * Provider is final and cannot be mocked, so private properties are set
     * directly.
     */
    private function providerWithPattern(string $name, string $pattern): Provider
    {
        $provider = new Provider();
        $nameProp = new \ReflectionProperty(Provider::class, 'name');
        $nameProp->setAccessible(true);
        $nameProp->setValue($provider, $name);
        $patternProp = new \ReflectionProperty(Provider::class, 'pattern');
        $patternProp->setAccessible(true);
        $patternProp->setValue($provider, $pattern);

        return $provider;
    }

    /**
     * Seed the Providers singleton's cached 'all' list to avoid DB access.
     *
     * @param Provider[] $providers
     */
    private function seedProviders(array $providers): void
    {
        $instance = Providers::get_instance();
        $listProp = new \ReflectionProperty(Providers::class, 'list');
        $listProp->setAccessible(true);
        $listProp->setValue($instance, ['all' => $providers]);
    }

    public function testInitRegistersHooks(): void
    {
        Enfold::init();

        $this->assertNotFalse(
            hasFilter('do_shortcode_tag', [Enfold::class, 'decrement_depth'])
        );
        $this->assertNotFalse(
            hasFilter('pre_do_shortcode_tag', [Enfold::class, 'increment_depth'])
        );
        $this->assertNotFalse(
            hasFilter('embed_privacy_ignored_shortcodes', [Enfold::class, 'set_ignored_shortcode'])
        );
        $this->assertNotFalse(
            hasFilter('avf_sc_video_output', [Enfold::class, 'set_video_output'])
        );
    }

    public function testIncrementDepthOnlyForAvVideo(): void
    {
        Enfold::increment_depth('out', 'other');
        $this->assertSame(0, Enfold::$av_video_depth);

        $output = Enfold::increment_depth('out', 'av_video');
        $this->assertSame(1, Enfold::$av_video_depth);
        // output passes through unchanged
        $this->assertSame('out', $output);
    }

    public function testDecrementDepthOnlyForAvVideoAboveZero(): void
    {
        // decrement is a no-op when depth is already zero
        Enfold::decrement_depth('out', 'av_video');
        $this->assertSame(0, Enfold::$av_video_depth);

        Enfold::$av_video_depth = 2;

        // wrong tag does not decrement
        Enfold::decrement_depth('out', 'other');
        $this->assertSame(2, Enfold::$av_video_depth);

        $output = Enfold::decrement_depth('out', 'av_video');
        $this->assertSame(1, Enfold::$av_video_depth);
        $this->assertSame('out', $output);
    }

    public function testMaybeSetOriginalContentReturnsOriginalWhenInsideAvVideo(): void
    {
        Enfold::$av_video_depth = 1;

        $this->assertSame(
            'original',
            Enfold::maybe_set_original_content('new', 'original')
        );
    }

    public function testMaybeSetOriginalContentReturnsNewOutsideAvVideo(): void
    {
        Enfold::$av_video_depth = 0;

        $this->assertSame(
            'new',
            Enfold::maybe_set_original_content('new', 'original')
        );
    }

    public function testSetIgnoredShortcodeAppendsAvVideo(): void
    {
        $result = Enfold::set_ignored_shortcode(['existing']);

        $this->assertSame(['existing', 'av_video'], $result);
    }

    public function testSetVideoOutputReturnsOutputWhenSrcEmpty(): void
    {
        // no src attribute: providers are never consulted
        $this->assertSame('out', Enfold::set_video_output('out', []));
        $this->assertSame('out', Enfold::set_video_output('out', ['src' => '']));
    }

    public function testSetVideoOutputReturnsOutputWhenNoProviderMatches(): void
    {
        // one provider with an empty pattern (skipped) and one that does not match
        $this->seedProviders([
            $this->providerWithPattern('empty', ''),
            $this->providerWithPattern('vimeo', '/vimeo\.com/'),
        ]);

        $this->assertSame(
            'out',
            Enfold::set_video_output('out', ['src' => 'https://www.youtube.com/watch?v=abc'])
        );
    }

    protected function tearDown(): void
    {
        tearDown();
        Enfold::$av_video_depth = 0;
        Providers::$instance = null;
        parent::tearDown();
    }
}
