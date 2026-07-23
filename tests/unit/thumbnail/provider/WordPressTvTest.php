<?php

declare(strict_types=1);

namespace Tests\Unit\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\provider\WordPress_TV;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(WordPress_TV::class)]
final class WordPressTvTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        stubTranslationFunctions();
    }

    public function testIsProviderEmbed(): void
    {
        $this->assertTrue(WordPress_TV::is_provider_embed('https://wordpress.tv/2023/01/01/foo/'));
        $this->assertFalse(WordPress_TV::is_provider_embed('https://vimeo.com/1'));
    }

    public function testGetIdFromEmbedUrl(): void
    {
        $content = '<iframe src="https://video.wordpress.com/embed/abcDEF12?hd=1" width="600"></iframe>';

        $this->assertSame('abcDEF12', WordPress_TV::get_id($content));
    }

    public function testGetIdFromMetadataFallback(): void
    {
        $url = 'https://wordpress.tv/2023/01/01/some-talk/';
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->andReturn([
            [
                'post_id' => 5,
                'meta_key' => 'embed_privacy_thumbnail_wordpress-tv_abcDEF12_url',
                'meta_value' => $url,
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $this->assertSame('abcDEF12', WordPress_TV::get_id($url));

        unset($GLOBALS['wpdb']);
    }

    public function testGetIdReturnsEmptyWhenMetadataHasNoMatch(): void
    {
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $GLOBALS['wpdb'] = $wpdb;

        $this->assertSame('', WordPress_TV::get_id('https://wordpress.tv/unknown/'));

        unset($GLOBALS['wpdb']);
    }

    public function testGetTitle(): void
    {
        $this->assertSame('WordPress TV', WordPress_TV::get_title());
    }

    public function testGetReturnsEarlyForNonProviderUrl(): void
    {
        expect('get_post')->never();

        WordPress_TV::get((object) ['html' => ''], 'https://vimeo.com/1');
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
