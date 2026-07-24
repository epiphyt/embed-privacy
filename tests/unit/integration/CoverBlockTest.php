<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Cover_Block;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Tests for the Cover Block integration.
 *
 * Cover_Block::replace_background_dim() rearranges the DOM of Gutenberg cover
 * blocks that contain an Embed Privacy overlay so the dimming element is painted
 * behind (or appended into) the overlay container. The logic is pure DOM
 * manipulation and needs no WordPress functions.
 */
#[CoversClass(Cover_Block::class)]
final class CoverBlockTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testInitRegistersContentFilter(): void
    {
        Cover_Block::init();

        // has() returns the registered priority (an int) for a matching callback, false otherwise
        $this->assertNotFalse(
            hasFilter('the_content', [Cover_Block::class, 'replace_background_dim'])
        );
    }

    public function testReturnsContentUnchangedWithoutEmbedPrivacyContainer(): void
    {
        $content = '<div class="wp-block-cover"><div class="wp-block-cover__background"></div></div>';

        $this->assertSame($content, Cover_Block::replace_background_dim($content));
    }

    public function testReturnsContentUnchangedWithoutCoverBlock(): void
    {
        $content = '<div class="embed-privacy-container"><div class="embed-privacy-overlay"></div></div>';

        $this->assertSame($content, Cover_Block::replace_background_dim($content));
    }

    public function testReturnsContentUnchangedWithoutEmbedBackground(): void
    {
        // cover with an overlay container but no wp-block-cover__embed-background,
        // so the guard bails out and the content is returned untouched
        $content = '<div class="wp-block-cover">'
            . '<span class="wp-block-cover__background"></span>'
            . '<div class="embed-privacy-container">'
            . '<div class="embed-privacy-overlay"></div>'
            . '</div>'
            . '</div>';

        $this->assertSame($content, Cover_Block::replace_background_dim($content));
    }

    public function testMovesBackgroundDimBeforeOverlay(): void
    {
        // both markers present so the guard passes and the DOM is processed
        $content = '<div class="wp-block-cover">'
            . '<span class="wp-block-cover__background" id="dim"></span>'
            . '<div class="wp-block-cover__embed-background">'
            . '<div class="embed-privacy-container">'
            . '<div class="embed-privacy-overlay" id="overlay"></div>'
            . '</div>'
            . '</div>'
            . '</div>';

        $output = Cover_Block::replace_background_dim($content);

        // the dim element has been relocated inside the container, right before the overlay
        $dimPos = \strpos($output, 'id="dim"');
        $overlayPos = \strpos($output, 'id="overlay"');
        $containerPos = \strpos($output, 'embed-privacy-container');

        $this->assertNotFalse($dimPos);
        $this->assertNotFalse($overlayPos);
        // the dim now sits after the container start and before the overlay
        $this->assertGreaterThan($containerPos, $dimPos);
        $this->assertLessThan($overlayPos, $dimPos);
        // the <html> wrapper and charset meta are stripped from the serialized output
        $this->assertStringNotContainsString('<html>', $output);
        $this->assertStringNotContainsString('charset', $output);
    }

    public function testAppendsBackgroundDimToContainerWhenNoOverlay(): void
    {
        // container present (guard passes) but without an embed-privacy-overlay child
        $content = '<div class="wp-block-cover">'
            . '<span class="wp-block-cover__background" id="dim"></span>'
            . '<div class="wp-block-cover__embed-background">'
            . '<div class="embed-privacy-container" id="container">'
            . '<p>no overlay here</p>'
            . '</div>'
            . '</div>'
            . '</div>';

        $output = Cover_Block::replace_background_dim($content);

        // the dim element is appended as the last child of the container
        $dimPos = \strpos($output, 'id="dim"');
        $containerPos = \strpos($output, 'id="container"');
        $noOverlayPos = \strpos($output, 'no overlay here');

        $this->assertNotFalse($dimPos);
        // dim moved after the container opening and after the existing paragraph (appended last)
        $this->assertGreaterThan($containerPos, $dimPos);
        $this->assertGreaterThan($noOverlayPos, $dimPos);
    }

    public function testSkipsCoverWithoutBackgroundOrContainer(): void
    {
        // markers are present in the string (guard passes) but the matched cover
        // lacks a wp-block-cover__background element, so it is left untouched
        $content = '<div class="wp-block-cover">'
            . '<div class="embed-privacy-container"><div class="embed-privacy-overlay"></div></div>'
            . '</div>';

        $output = Cover_Block::replace_background_dim($content);

        // structure preserved; container and overlay still present
        $this->assertStringContainsString('embed-privacy-container', $output);
        $this->assertStringContainsString('embed-privacy-overlay', $output);
        $this->assertStringNotContainsString('wp-block-cover__background', $output);
    }

    public function testSkipsCoverWhenEmbedBackgroundIsNotDirectChild(): void
    {
        // all markers present (guard passes) but the embed background is nested
        // inside another wrapper rather than being a direct child of the cover
        $content = '<div class="wp-block-cover">'
            . '<span class="wp-block-cover__background" id="dim"></span>'
            . '<div class="inner-wrapper">'
            . '<div class="wp-block-cover__embed-background">'
            . '<div class="embed-privacy-container">'
            . '<div class="embed-privacy-overlay" id="overlay"></div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        $output = Cover_Block::replace_background_dim($content);

        // the cover was not processed, so the dim stays put as the cover's first
        // child, still before the embed background wrapper (it was not moved into
        // the container). Had it been processed, the dim would sit after it.
        $dimPos = \strpos($output, 'id="dim"');
        $embedBackgroundPos = \strpos($output, 'wp-block-cover__embed-background');

        $this->assertNotFalse($dimPos);
        $this->assertNotFalse($embedBackgroundPos);
        $this->assertLessThan($embedBackgroundPos, $dimPos);
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
