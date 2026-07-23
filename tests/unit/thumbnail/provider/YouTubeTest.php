<?php

declare(strict_types=1);

namespace Tests\Unit\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\provider\YouTube;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(YouTube::class)]
final class YouTubeTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        stubTranslationFunctions();
    }

    public function testIsProviderEmbedMatchesYouTubeDomains(): void
    {
        $this->assertTrue(YouTube::is_provider_embed('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertTrue(YouTube::is_provider_embed('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertFalse(YouTube::is_provider_embed('https://vimeo.com/123'));
    }

    public function testGetIdFromWatchUrl(): void
    {
        $this->assertSame('dQw4w9WgXcQ', YouTube::get_id('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function testGetIdFromEmbedUrl(): void
    {
        $this->assertSame('dQw4w9WgXcQ', YouTube::get_id('https://www.youtube.com/embed/dQw4w9WgXcQ'));
    }

    public function testGetIdFromShortsUrl(): void
    {
        $this->assertSame('abc123', YouTube::get_id('https://www.youtube.com/shorts/abc123'));
    }

    public function testGetIdFromShortUrl(): void
    {
        $this->assertSame('dQw4w9WgXcQ', YouTube::get_id('https://youtu.be/dQw4w9WgXcQ'));
    }

    public function testGetIdStripsQueryString(): void
    {
        $this->assertSame(
            'dQw4w9WgXcQ',
            YouTube::get_id('https://www.youtube.com/embed/dQw4w9WgXcQ?start=30&rel=0')
        );
    }

    public function testGetIdByThumbnailUrl(): void
    {
        $this->assertSame(
            'dQw4w9WgXcQ',
            YouTube::get_id_by_thumbnail_url('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
        );
    }

    public function testGetIdByThumbnailUrlWithoutKnownPrefix(): void
    {
        // when the prefix does not match, the whole string is treated as the first path part
        $this->assertSame(
            'https:',
            YouTube::get_id_by_thumbnail_url('https://example.com/vi/foo/bar.jpg')
        );
    }

    public function testGetTitle(): void
    {
        $this->assertSame('YouTube', YouTube::get_title());
    }

    public function testGetReturnsEarlyForNonProviderUrl(): void
    {
        // a non-YouTube URL must not trigger any thumbnail lookup/download/save
        expect('get_post')->never();

        YouTube::get((object) ['thumbnail_url' => ''], 'https://vimeo.com/123');
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
