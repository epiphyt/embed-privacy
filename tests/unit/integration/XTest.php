<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\X;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(X::class)]
final class XTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    /**
     * A representative Twitter/X blockquote embed with its widgets.js script.
     */
    private function tweetEmbed(string $host): string
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return '<blockquote class="twitter-tweet">'
            . '<p lang="en" dir="ltr">Hello world</p>'
            . '&mdash; Author Name (@author) '
            . '<a href="https://' . $host . '/author/status/123456789">January 1, 2024</a>'
            . '</blockquote>' . "\n"
            . '<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    public function testInitRegistersCustomReplacementFilter(): void
    {
        X::init();

        $this->assertNotFalse(
            hasFilter('embed_privacy_custom_oembed_replacement', [X::class, 'set_local_tweet'])
        );
    }

    public function testSetLocalTweetReturnsReplacementForNonXProvider(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('is')->with('x')->andReturn(false);

        $this->assertSame(
            'custom',
            X::set_local_tweet('custom', 'content', $provider)
        );
    }

    public function testSetLocalTweetReturnsReplacementWhenOptionDisabled(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('is')->with('x')->andReturn(true);
        when('get_option')->justReturn(false);

        $this->assertSame(
            'custom',
            X::set_local_tweet('custom', 'content', $provider)
        );
    }

    public function testSetLocalTweetBuildsLocalTweetWhenEnabled(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('is')->with('x')->andReturn(true);
        stubs([
            'get_option' => static function ($name) {
                if ($name === 'embed_privacy_local_tweets') {
                    return true;
                }

                return 'Y-m-d';
            },
            'wp_date' => 'FORMATTED-DATE',
        ]);

        $result = X::set_local_tweet('custom', $this->tweetEmbed('twitter.com'), $provider);

        $this->assertStringContainsString('embed-privacy-local-tweet', $result);
        $this->assertStringContainsString('Hello world', $result);
    }

    public function testGetLocalTweetRemovesScriptAndWrapsMarkup(): void
    {
        stubs([
            'get_option' => 'Y-m-d',
            'wp_date' => 'FORMATTED-DATE',
        ]);

        $result = X::get_local_tweet($this->tweetEmbed('twitter.com'));

        // the whole embed is wrapped in the local tweet container
        $this->assertStringStartsWith('<div class="embed-privacy-local-tweet">', $result);
        $this->assertStringEndsWith('</div>', $result);
        // the widgets.js script is stripped
        $this->assertStringNotContainsString('<script', $result);
        // the author text node becomes a span with the author-meta class inside a cite
        $this->assertStringContainsString('embed-privacy-author-meta', $result);
        $this->assertStringContainsString('embed-privacy-tweet-meta', $result);
        $this->assertStringContainsString('Author Name (@author)', $result);
        // original body content survives
        $this->assertStringContainsString('Hello world', $result);
    }

    public function testGetLocalTweetReformatsStatusLinkDate(): void
    {
        stubs([
            'get_option' => 'Y-m-d',
            'wp_date' => 'FORMATTED-DATE',
        ]);

        $result = X::get_local_tweet($this->tweetEmbed('twitter.com'));

        // the status link's visible date is replaced by the localized date
        $this->assertStringContainsString('FORMATTED-DATE', $result);
        $this->assertStringNotContainsString('January 1, 2024', $result);
    }

    public function testGetLocalTweetHandlesXComStatusLink(): void
    {
        stubs([
            'get_option' => 'Y-m-d',
            'wp_date' => 'FORMATTED-DATE',
        ]);

        $result = X::get_local_tweet($this->tweetEmbed('x.com'));

        $this->assertStringContainsString('FORMATTED-DATE', $result);
        $this->assertStringContainsString('embed-privacy-local-tweet', $result);
    }

    public function testGetLocalTweetKeepsOriginalDateWhenWpDateNotString(): void
    {
        stubs(['get_option' => 'Y-m-d']);
        // wp_date returning a non-string leaves the link text untouched
        when('wp_date')->justReturn(false);

        $result = X::get_local_tweet($this->tweetEmbed('twitter.com'));

        $this->assertStringContainsString('January 1, 2024', $result);
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
