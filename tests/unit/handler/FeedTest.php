<?php

declare(strict_types=1);

namespace Tests\Unit\handler;

use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\handler\Feed;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Filters\expectAdded;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Feed::class)]
final class FeedTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        stubEscapeFunctions();
        stubTranslationFunctions();
    }

    /**
     * Build a real Provider (final class) with the given title, bypassing the
     * WordPress-heavy constructor.
     */
    private function provider(string $title): Provider
    {
        $reflection = new \ReflectionClass(Provider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('title');
        $property->setAccessible(true);
        $property->setValue($provider, $title);

        return $provider;
    }

    public function testInitAddsTemplateMarkupFilter(): void
    {
        expectAdded('embed_privacy_template_markup')->once();

        Feed::init();
    }

    public function testReplaceTemplateReturnsMarkupOutsideFeed(): void
    {
        expect('is_feed')->once()->andReturn(false);

        $markup = '<div class="embed-privacy-overlay"></div>';

        $this->assertSame(
            $markup,
            Feed::replace_template($markup, $this->provider('YouTube'), ['embed_url' => 'https://youtu.be/x'])
        );
    }

    public function testReplaceTemplateReturnsNonLinkMarkupWithoutEmbedUrl(): void
    {
        expect('is_feed')->once()->andReturn(true);

        $result = Feed::replace_template('<div></div>', $this->provider('YouTube'), []);

        $this->assertSame('Embedded content from YouTube', $result);
        $this->assertStringNotContainsString('<a ', $result);
    }

    public function testReplaceTemplateReturnsLinkMarkupWithEmbedUrl(): void
    {
        expect('is_feed')->once()->andReturn(true);

        $result = Feed::replace_template(
            '<div></div>',
            $this->provider('YouTube'),
            ['embed_url' => 'https://youtu.be/dQw4w9WgXcQ']
        );

        $this->assertStringContainsString('<a href="https://youtu.be/dQw4w9WgXcQ">', $result);
        $this->assertStringContainsString('Open embedded content from YouTube', $result);
        $this->assertStringContainsString('embed-privacy-url', $result);
    }

    public function testGetLinkMarkup(): void
    {
        $result = Feed::get_link_markup(
            ['embed_url' => 'https://vimeo.com/123'],
            $this->provider('Vimeo')
        );

        $this->assertSame(
            '<span class="embed-privacy-url"><a href="https://vimeo.com/123">'
            . 'Open embedded content from Vimeo</a></span>',
            $result
        );
    }

    public function testGetNonLinkMarkup(): void
    {
        $this->assertSame(
            'Embedded content from Vimeo',
            Feed::get_non_link_markup($this->provider('Vimeo'))
        );
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
