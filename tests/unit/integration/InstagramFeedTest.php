<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\integration\Instagram_Feed;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Tests for the Instagram Feed integration.
 *
 * Instagram_Feed::should_replace_match() prevents Embed Privacy from replacing
 * Instagram markup that was produced by the "Smash Balloon Instagram Feed"
 * plugin (identified by the "sbi_" class prefix).
 */
#[CoversClass(Instagram_Feed::class)]
final class InstagramFeedTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    /**
     * Build a real Provider with a controlled name via reflection.
     *
     * Provider is final, so it cannot be mocked; setting the private name
     * property directly is the cleanest way to control get_name().
     */
    private function providerWithName(string $name): Provider
    {
        $provider = new Provider();
        $reflection = new \ReflectionProperty(Provider::class, 'name');
        $reflection->setAccessible(true);
        $reflection->setValue($provider, $name);

        return $provider;
    }

    public function testInitRegistersShouldReplaceMatchFilter(): void
    {
        Instagram_Feed::init();

        $this->assertNotFalse(
            hasFilter('embed_privacy_should_replace_match', [Instagram_Feed::class, 'should_replace_match'])
        );
    }

    public function testReturnsEarlyWhenShouldReplaceIsFalse(): void
    {
        $provider = $this->providerWithName('instagram');

        // when replacement is already disabled, the value passes through unchanged
        $this->assertFalse(
            Instagram_Feed::should_replace_match(false, 'class="sbi_photo"', $provider)
        );
    }

    public function testReturnsUnchangedForNonInstagramProvider(): void
    {
        $provider = $this->providerWithName('youtube');

        // a non-Instagram provider is never affected by this integration
        $this->assertTrue(
            Instagram_Feed::should_replace_match(true, 'class="sbi_photo"', $provider)
        );
    }

    public function testPreventsReplacementForInstagramFeedMarkup(): void
    {
        $provider = $this->providerWithName('instagram');

        // markup produced by the Instagram Feed plugin (sbi_ prefix) is skipped
        $this->assertFalse(
            Instagram_Feed::should_replace_match(true, '<div class="sbi_photo">…</div>', $provider)
        );
    }

    public function testAllowsReplacementForRegularInstagramMarkup(): void
    {
        $provider = $this->providerWithName('instagram');

        // regular Instagram embeds (no sbi_ marker) are still replaced
        $this->assertTrue(
            Instagram_Feed::should_replace_match(true, '<blockquote class="instagram-media">…</blockquote>', $provider)
        );
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
