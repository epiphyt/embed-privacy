<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Activitypub;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Activitypub::class)]
final class ActivitypubTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        stubEscapeFunctions();
        stubs([
            'wp_parse_url' => 'parse_url',
        ]);
    }

    public function testInitRegistersHooks(): void
    {
        Activitypub::init();

        $this->assertNotFalse(
            \Brain\Monkey\Filters\has('pre_oembed_result', [Activitypub::class, 'maybe_set_cache'])
        );
        $this->assertNotFalse(
            \Brain\Monkey\Filters\has(
                'embed_privacy_custom_oembed_replacement',
                [Activitypub::class, 'maybe_set_local_toot']
            )
        );
        $this->assertNotFalse(
            \Brain\Monkey\Filters\has(
                'embed_privacy_is_ignored_request',
                [Activitypub::class, 'set_ignored_request']
            )
        );
    }

    public function testIsValidEmbedRejectsNonObject(): void
    {
        $this->assertFalse(Activitypub::is_valid_embed('not an object'));
        $this->assertFalse(Activitypub::is_valid_embed(null));
    }

    public function testIsValidEmbedAcceptsSupportedType(): void
    {
        $content = (object) [
            '@context' => ['https://www.w3.org/ns/activitystreams'],
            'type' => 'Note',
        ];

        $this->assertTrue(Activitypub::is_valid_embed($content));
    }

    public function testIsValidEmbedRejectsWrongContext(): void
    {
        $content = (object) [
            '@context' => ['https://example.com/other'],
            'type' => 'Note',
        ];

        $this->assertFalse(Activitypub::is_valid_embed($content));
    }

    public function testIsValidEmbedRejectsUnsupportedType(): void
    {
        $content = (object) [
            '@context' => ['https://www.w3.org/ns/activitystreams'],
            'type' => 'Video',
        ];

        $this->assertFalse(Activitypub::is_valid_embed($content));
    }

    public function testMaybeSetCacheReturnsResultWhenOptionDisabled(): void
    {
        when('get_option')->justReturn(false);
        expect('get_transient')->never();

        $this->assertSame('original', Activitypub::maybe_set_cache('original', 'https://mastodon.social/@foo/1'));
    }

    public function testMaybeSetCacheReturnsExistingResult(): void
    {
        when('get_option')->justReturn(true);
        expect('get_transient')->never();

        $this->assertSame('already', Activitypub::maybe_set_cache('already', 'https://mastodon.social/@foo/1'));
    }

    public function testMaybeSetCacheSkipsRemoteWhenCached(): void
    {
        when('get_option')->justReturn(true);
        when('get_transient')->justReturn((object) ['type' => 'Note']);
        expect('wp_safe_remote_get')->never();

        $this->assertNull(Activitypub::maybe_set_cache(null, 'https://mastodon.social/@foo/1'));
    }

    public function testMaybeSetCacheFetchesRemoteWhenNotCached(): void
    {
        when('get_option')->justReturn(true);
        when('get_transient')->justReturn(false);
        when('is_wp_error')->justReturn(false);
        when('wp_remote_retrieve_body')->justReturn('');
        expect('wp_safe_remote_get')->once()->andReturn([]);
        expect('set_transient')->once();

        // invalid JSON body results in null remote data, so the (null) result is returned unchanged
        $this->assertNull(Activitypub::maybe_set_cache(null, 'https://mastodon.social/@foo/1'));
    }

    public function testMaybeSetCacheReturnsNullWhenRemoteRequestErrors(): void
    {
        when('get_option')->justReturn(true);
        when('get_transient')->justReturn(false);
        when('is_wp_error')->justReturn(true);
        expect('wp_safe_remote_get')->once()->andReturn('error');
        expect('set_transient')->once();

        $this->assertNull(Activitypub::maybe_set_cache(null, 'https://mastodon.social/@foo/1'));
    }

    public function testMaybeSetLocalTootReturnsReplacementWhenOptionDisabled(): void
    {
        when('get_option')->justReturn(false);
        expect('get_transient')->never();

        $this->assertSame(
            'custom',
            Activitypub::maybe_set_local_toot('custom', 'content', null, 'https://mastodon.social/@foo/1')
        );
    }

    public function testMaybeSetLocalTootReturnsReplacementForInvalidEmbed(): void
    {
        when('get_option')->justReturn(true);
        when('get_transient')->justReturn(false);

        $this->assertSame(
            'custom',
            Activitypub::maybe_set_local_toot('custom', 'content', null, 'https://mastodon.social/@foo/1')
        );
    }

    public function testMaybeSetLocalTootBuildsTootFromUsersUrl(): void
    {
        $cache = (object) [
            '@context' => ['https://www.w3.org/ns/activitystreams'],
            'type' => 'Note',
            'published' => '2024-01-01T12:00:00Z',
            'content' => '<p>Hello world</p>',
            'attributedTo' => 'https://mastodon.social/users/foo',
            'url' => 'https://mastodon.social/@foo/123',
        ];
        stubs([
            'get_option' => static function ($name) {
                if ($name === 'date_format') {
                    return 'Y-m-d';
                }

                if ($name === 'time_format') {
                    return 'H:i';
                }

                return true;
            },
            'get_transient' => $cache,
            'wp_timezone' => new \DateTimeZone('UTC'),
            'wp_kses_post' => static function ($string) {
                return $string;
            },
        ]);

        $result = Activitypub::maybe_set_local_toot('custom', 'content', null, 'https://mastodon.social/@foo/123');

        $this->assertStringContainsString('embed-privacy__toot', $result);
        $this->assertStringContainsString('Hello world', $result);
        $this->assertStringContainsString('@foo@mastodon.social', $result);
    }

    public function testMaybeSetLocalTootBuildsTootFromAuthorUrl(): void
    {
        $cache = (object) [
            '@context' => ['https://www.w3.org/ns/activitystreams'],
            'type' => 'Article',
            'published' => '2024-01-01T12:00:00Z',
            'content' => '<p>Blog post</p>',
            'attributedTo' => 'https://example.com/author/bar/',
            'url' => 'https://example.com/2024/01/01/post',
        ];
        stubs([
            'get_option' => static function ($name) {
                if ($name === 'date_format') {
                    return 'Y-m-d';
                }

                if ($name === 'time_format') {
                    return 'H:i';
                }

                return true;
            },
            'get_transient' => $cache,
            'wp_timezone' => new \DateTimeZone('UTC'),
            'wp_kses_post' => static function ($string) {
                return $string;
            },
        ]);

        $result = Activitypub::maybe_set_local_toot('custom', 'content', null, 'https://example.com/2024/01/01/post');

        $this->assertStringContainsString('@bar@example.com', $result);
    }

    public function testSetIgnoredRequestKeepsTruthyValue(): void
    {
        $this->assertTrue(Activitypub::set_ignored_request(true));
    }

    public function testSetIgnoredRequestReturnsFalseWithoutActivityPubFunction(): void
    {
        $this->assertFalse(Activitypub::set_ignored_request(false));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
