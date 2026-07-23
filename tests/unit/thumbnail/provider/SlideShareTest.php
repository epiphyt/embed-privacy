<?php

declare(strict_types=1);

namespace Tests\Unit\thumbnail\provider;

use epiphyt\Embed_Privacy\thumbnail\provider\SlideShare;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(SlideShare::class)]
final class SlideShareTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        stubTranslationFunctions();
    }

    public function testIsProviderEmbed(): void
    {
        $this->assertTrue(SlideShare::is_provider_embed('https://www.slideshare.net/foo/bar'));
        $this->assertFalse(SlideShare::is_provider_embed('https://vimeo.com/1'));
    }

    public function testGetIdFromEmbedCodeKey(): void
    {
        $content = '<iframe src="https://www.slideshare.net/slideshow/embed_code/key/AbC123XyZ" '
            . 'width="597"></iframe>';

        $this->assertSame('AbC123XyZ', SlideShare::get_id($content));
    }

    public function testGetIdFromMetadataFallback(): void
    {
        $url = 'https://www.slideshare.net/foo/bar-presentation';
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->andReturn([
            [
                'post_id' => 5,
                'meta_key' => 'embed_privacy_thumbnail_slideshare_98765_url',
                'meta_value' => $url,
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        // no embed_code/key in the content triggers the metadata lookup branch
        $this->assertSame('98765', SlideShare::get_id($url));

        unset($GLOBALS['wpdb']);
    }

    public function testGetIdReturnsEmptyWhenMetadataHasNoMatch(): void
    {
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $GLOBALS['wpdb'] = $wpdb;

        $this->assertSame('', SlideShare::get_id('https://www.slideshare.net/unknown'));

        unset($GLOBALS['wpdb']);
    }

    public function testGetTitle(): void
    {
        $this->assertSame('SlideShare', SlideShare::get_title());
    }

    public function testGetReturnsEarlyForNonProviderUrl(): void
    {
        expect('get_post')->never();

        SlideShare::get((object) ['html' => '', 'thumbnail_url' => ''], 'https://vimeo.com/1');
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
