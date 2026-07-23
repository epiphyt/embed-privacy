<?php

declare(strict_types=1);

namespace Tests\Unit\handler;

use epiphyt\Embed_Privacy\handler\Oembed;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Oembed::class)]
final class OembedTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testGetDimensionsFromIframe(): void
    {
        $content = '<iframe src="https://www.youtube.com/embed/x" width="560" height="315"></iframe>';

        $this->assertSame(
            ['height' => '315', 'width' => '560'],
            Oembed::get_dimensions($content)
        );
    }

    public function testGetDimensionsFromImg(): void
    {
        $content = '<img src="https://example.com/x.jpg" width="800" height="600">';

        $this->assertSame(
            ['height' => '600', 'width' => '800'],
            Oembed::get_dimensions($content)
        );
    }

    public function testGetDimensionsFromObject(): void
    {
        $content = '<object width="320" height="240"></object>';

        $this->assertSame(
            ['height' => '240', 'width' => '320'],
            Oembed::get_dimensions($content)
        );
    }

    public function testGetDimensionsReturnsEmptyWhenOnlyOneDimensionPresent(): void
    {
        // only a width is present, so the height && width guard fails
        $content = '<iframe src="https://example.com" width="560"></iframe>';

        $this->assertSame([], Oembed::get_dimensions($content));
    }

    public function testGetDimensionsReturnsEmptyWithoutMatchingTag(): void
    {
        $content = '<div><p>No embeddable element here.</p></div>';

        $this->assertSame([], Oembed::get_dimensions($content));
    }

    public function testGetDimensionsReturnsFirstMatchingElement(): void
    {
        // the embed tag is checked before iframe, so its dimensions win
        $content = '<embed width="100" height="200">'
            . '<iframe src="https://example.com" width="560" height="315"></iframe>';

        $this->assertSame(
            ['height' => '200', 'width' => '100'],
            Oembed::get_dimensions($content)
        );
    }

    public function testGetTitleFromIframe(): void
    {
        $content = '<iframe title="My Video" src="https://www.youtube.com/embed/x"></iframe>';

        $this->assertSame('My Video', Oembed::get_title($content));
    }

    public function testGetTitleFromObject(): void
    {
        $content = '<object title="Flash Object"></object>';

        $this->assertSame('Flash Object', Oembed::get_title($content));
    }

    public function testGetTitleReturnsEmptyWithoutTitleAttribute(): void
    {
        $content = '<iframe src="https://www.youtube.com/embed/x"></iframe>';

        $this->assertSame('', Oembed::get_title($content));
    }

    public function testGetTitleIgnoresImgTag(): void
    {
        // img is not in the title tag list, so its title is not returned
        $content = '<img src="https://example.com/x.jpg" title="Image title">';

        $this->assertSame('', Oembed::get_title($content));
    }

    public function testGetTitleReturnsEmptyForEmptyContent(): void
    {
        $this->assertSame('', Oembed::get_title(''));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
