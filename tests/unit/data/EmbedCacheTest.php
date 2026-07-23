<?php

declare(strict_types=1);

namespace Tests\Unit\data;

use epiphyt\Embed_Privacy\data\Embed_Cache;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Embed_Cache::class)]
final class EmbedCacheTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testGetKeyIsPrefixedMd5(): void
    {
        $url = 'https://www.example.com/embed/123';

        $this->assertSame(
            'embed_privacy_embed_' . \md5($url),
            Embed_Cache::get_key($url)
        );
    }

    public function testGetKeyIsDeterministic(): void
    {
        $url = 'https://www.example.com/embed/123';

        $this->assertSame(Embed_Cache::get_key($url), Embed_Cache::get_key($url));
    }

    public function testGetKeyDiffersPerUrl(): void
    {
        $this->assertNotSame(
            Embed_Cache::get_key('https://www.example.com/a'),
            Embed_Cache::get_key('https://www.example.com/b')
        );
    }

    public function testGetReadsTransientByKey(): void
    {
        $url = 'https://www.example.com/embed/123';
        $expected = ['embed' => '<iframe></iframe>'];

        expect('get_transient')
            ->once()
            ->with(Embed_Cache::get_key($url))
            ->andReturn($expected);

        $this->assertSame($expected, Embed_Cache::get($url));
    }

    public function testGetReturnsFalseWhenTransientMissing(): void
    {
        expect('get_transient')
            ->once()
            ->andReturn(false);

        $this->assertFalse(Embed_Cache::get('https://www.example.com/missing'));
    }

    public function testSetStoresTransientForOneWeek(): void
    {
        $url = 'https://www.example.com/embed/123';
        $data = ['embed' => '<iframe></iframe>'];

        expect('set_transient')
            ->once()
            ->with(Embed_Cache::get_key($url), $data, \WEEK_IN_SECONDS)
            ->andReturn(true);

        $this->assertTrue(Embed_Cache::set($url, $data));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
