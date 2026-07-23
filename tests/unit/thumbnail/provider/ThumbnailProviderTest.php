<?php

declare(strict_types=1);

namespace Tests\Unit\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Concrete fixture to exercise the abstract base provider via late static binding.
 */
final class FixtureThumbnailProvider extends Thumbnail_Provider
{
    public static $domains = [
        'example.com',
        'test.org',
    ];

    public static $name = 'fixture';
}

#[CoversClass(Thumbnail_Provider::class)]
final class ThumbnailProviderTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        stubs([
            'wp_get_upload_dir' => [
                'error' => false,
                'basedir' => '/var/www/uploads',
                'baseurl' => 'https://www.example.com/uploads',
            ],
            'wp_mkdir_p' => true,
        ]);
    }

    public function testIsProviderEmbedMatchesConfiguredDomain(): void
    {
        $this->assertTrue(
            FixtureThumbnailProvider::is_provider_embed('https://www.example.com/watch?v=1')
        );
        $this->assertTrue(
            FixtureThumbnailProvider::is_provider_embed('https://test.org/x')
        );
    }

    public function testIsProviderEmbedRejectsUnknownDomain(): void
    {
        $this->assertFalse(
            FixtureThumbnailProvider::is_provider_embed('https://www.youtube.com/watch?v=1')
        );
    }

    public function testIsProviderEmbedIsFalseWhenNoDomainsConfigured(): void
    {
        // the abstract base declares an empty $domains list
        $this->assertFalse(
            Thumbnail_Provider::is_provider_embed('https://www.example.com/')
        );
    }

    public function testGetPathAppendsFilenameToBaseDirectory(): void
    {
        $this->assertSame(
            '/var/www/uploads/embed-privacy/thumbnails/fixture-1-hqdefault.jpg',
            FixtureThumbnailProvider::get_path('fixture-1-hqdefault.jpg')
        );
    }

    public function testGetUrlAppendsFilenameToBaseUrl(): void
    {
        $this->assertSame(
            'https://www.example.com/uploads/embed-privacy/thumbnails/fixture-1-hqdefault.jpg',
            FixtureThumbnailProvider::get_url('fixture-1-hqdefault.jpg')
        );
    }

    public function testGetTitleDefaultsToEmptyString(): void
    {
        $this->assertSame('', Thumbnail_Provider::get_title());
    }

    public function testDefaultStubsReturnNull(): void
    {
        // the base implementations are intentionally empty no-ops
        $this->assertNull(FixtureThumbnailProvider::get((object) [], 'https://www.example.com/'));
        $this->assertNull(FixtureThumbnailProvider::get_id('https://www.example.com/'));
        $this->assertNull(FixtureThumbnailProvider::save('1', 'https://www.example.com/'));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
