<?php

declare(strict_types=1);

namespace Tests\Unit\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\provider\Vimeo;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Vimeo::class)]
final class VimeoTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        stubTranslationFunctions();
    }

    public function testIsProviderEmbed(): void
    {
        $this->assertTrue(Vimeo::is_provider_embed('https://vimeo.com/123456'));
        $this->assertFalse(Vimeo::is_provider_embed('https://www.youtube.com/watch?v=1'));
    }

    public function testGetIdFromShareUrl(): void
    {
        $this->assertSame('123456', Vimeo::get_id('https://vimeo.com/123456'));
    }

    public function testGetIdFromPlayerUrl(): void
    {
        $this->assertSame('123456', Vimeo::get_id('https://player.vimeo.com/video/123456'));
    }

    public function testGetIdStripsQueryString(): void
    {
        $this->assertSame('123456', Vimeo::get_id('https://vimeo.com/123456?h=abcdef&title=0'));
    }

    public function testGetTitle(): void
    {
        $this->assertSame('Vimeo', Vimeo::get_title());
    }

    public function testGetReturnsEarlyForNonProviderUrl(): void
    {
        expect('get_post')->never();

        Vimeo::get((object) ['thumbnail_url' => ''], 'https://www.youtube.com/watch?v=1');
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
