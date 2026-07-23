<?php

declare(strict_types=1);

namespace Tests\Unit\data;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\Embed_Privacy;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\faker;
use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Providers::class)]
final class ProvidersTest extends MockeryTestCase
{
    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * @var \Brain\Faker\Providers
     */
    protected $wpFaker;

    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        // reset singletons so cached lists / cookies don't leak between tests
        Providers::$instance = null;
        Embed_Privacy::$instance = null;

        stubs([
            'sanitize_title' => static function ($title) {
                return \strtolower(\trim(\preg_replace('/[^a-z0-9]+/i', '-', (string) $title), '-'));
            },
            'get_post_thumbnail_id' => 0,
            'update_meta_cache' => null,
            'wp_json_encode' => static function ($value) {
                return \json_encode($value);
            },
        ]);

        $this->faker = faker();
        $this->wpFaker = $this->faker->wp();
    }

    protected function tearDown(): void
    {
        Providers::$instance = null;
        Embed_Privacy::$instance = null;

        tearDown();
        parent::tearDown();
    }

    /**
     * Build a fresh instance so cached lists are isolated per test.
     */
    private function makeInstance(): Providers
    {
        Providers::$instance = null;

        return Providers::get_instance();
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $first = Providers::get_instance();
        $second = Providers::get_instance();

        $this->assertInstanceOf(Providers::class, $first);
        $this->assertSame($first, $second);
    }

    public function testInitRegistersHooks(): void
    {
        Providers::init();

        // has*() returns the registered priority (int), not bool true
        $this->assertNotFalse(hasAction('added_post_meta', Providers::class . '::clear_cache_on_meta'));
        $this->assertNotFalse(hasAction('deleted_post', Providers::class . '::clear_cache_on_post'));
        $this->assertNotFalse(hasAction('deleted_post_meta', Providers::class . '::clear_cache_on_meta'));
        $this->assertNotFalse(hasAction('save_post_epi_embed', Providers::class . '::clear_cache'));
        $this->assertNotFalse(hasAction('trashed_post', Providers::class . '::clear_cache_on_post'));
        $this->assertNotFalse(hasAction('untrashed_post', Providers::class . '::clear_cache_on_post'));
        $this->assertNotFalse(hasAction('updated_post_meta', Providers::class . '::clear_cache_on_meta'));
        $this->assertNotFalse(hasFilter('embed_privacy_provider_name', Providers::class . '::sanitize_name'));
    }

    public function testClearCacheDeletesAllTransients(): void
    {
        expect('delete_transient')->once()->with('embed_privacy_providers_all');
        expect('delete_transient')->once()->with('embed_privacy_providers_custom');
        expect('delete_transient')->once()->with('embed_privacy_providers_custom_google');
        expect('delete_transient')->once()->with('embed_privacy_providers_oembed');

        Providers::clear_cache();
    }

    public function testClearCacheOnPostClearsForEmbedPost(): void
    {
        $post = $this->wpFaker->post([
            'ID' => 5,
            'post_type' => 'epi_embed',
        ]);

        expect('delete_transient')->times(4);

        Providers::clear_cache_on_post($post->ID, $post);
    }

    public function testClearCacheOnPostDoesNothingForOtherPostType(): void
    {
        $post = $this->wpFaker->post([
            'ID' => 6,
            'post_type' => 'post',
        ]);

        expect('delete_transient')->never();

        Providers::clear_cache_on_post($post->ID, $post);
    }

    public function testClearCacheOnPostFetchesPostWhenNotProvided(): void
    {
        $post = $this->wpFaker->post([
            'ID' => 7,
            'post_type' => 'epi_embed',
        ]);

        expect('get_post')->once()->with(7)->andReturn($post);
        expect('delete_transient')->times(4);

        Providers::clear_cache_on_post(7);
    }

    public function testClearCacheOnMetaDelegatesToPost(): void
    {
        $post = $this->wpFaker->post([
            'ID' => 8,
            'post_type' => 'epi_embed',
        ]);

        expect('get_post')->once()->with(8)->andReturn($post);
        expect('delete_transient')->times(4);

        Providers::clear_cache_on_meta('123', 8);
    }

    public function testAddMatchStoresNoneForEmptyProvider(): void
    {
        $providers = $this->makeInstance();

        $providers->add_match('some content', '');

        $this->assertSame('none', $providers->get_content_matches('some content'));
    }

    public function testAddMatchAppendsProviderName(): void
    {
        $providers = $this->makeInstance();

        $providers->add_match('content', 'youtube');
        $providers->add_match('content', 'vimeo');

        $this->assertSame(['youtube', 'vimeo'], $providers->get_content_matches('content'));
    }

    public function testGetContentMatchesReturnsFalseWhenUnknown(): void
    {
        $providers = $this->makeInstance();

        $this->assertFalse($providers->get_content_matches('missing'));
    }

    public function testGetByNameReturnsUnknownProviderForEmptyName(): void
    {
        $providers = $this->makeInstance();

        $provider = $providers->get_by_name('');

        $this->assertInstanceOf(Provider::class, $provider);
        $this->assertTrue($provider->is_unknown());
    }

    public function testGetByNameMatchesProviderFromList(): void
    {
        $providers = $this->makeInstance();
        $post = $this->wpFaker->post([
            'ID' => 1,
            'post_name' => 'youtube',
            'post_status' => 'publish',
            'post_title' => 'YouTube',
            'post_type' => 'epi_embed',
        ]);

        stubs([
            'get_transient' => false,
            'set_transient' => true,
            'get_post_meta' => '',
        ]);
        expect('get_posts')->andReturn([$post]);

        $provider = $providers->get_by_name('YouTube');

        $this->assertSame('youtube', $provider->get_name());
        $this->assertFalse($provider->is_unknown());
    }

    public function testGetByPostReturnsProviderForPost(): void
    {
        $post = $this->wpFaker->post([
            'ID' => 2,
            'post_name' => 'vimeo',
            'post_title' => 'Vimeo',
            'post_type' => 'epi_embed',
        ]);

        stubs(['get_post_meta' => '']);

        $provider = Providers::get_by_post($post);

        $this->assertInstanceOf(Provider::class, $provider);
        $this->assertSame('vimeo', $provider->get_name());
    }

    public function testGetByPostsMapsAllPosts(): void
    {
        $posts = [
            $this->wpFaker->post(['ID' => 3, 'post_name' => 'a', 'post_type' => 'epi_embed']),
            $this->wpFaker->post(['ID' => 4, 'post_name' => 'b', 'post_type' => 'epi_embed']),
        ];

        stubs(['get_post_meta' => '']);

        $providers = Providers::get_by_posts($posts);

        $this->assertCount(2, $providers);
        $this->assertContainsOnlyInstancesOf(Provider::class, $providers);
    }

    public function testGetListQueriesAndCachesResult(): void
    {
        $providers = $this->makeInstance();
        $post = $this->wpFaker->post([
            'ID' => 10,
            'post_name' => 'youtube',
            'post_type' => 'epi_embed',
        ]);

        stubs([
            'get_transient' => false,
            'set_transient' => true,
            'get_post_meta' => '',
        ]);
        // the query must run exactly once; the second get_list() call is cached
        expect('get_posts')->once()->andReturn([$post]);

        $first = $providers->get_list('all');
        $second = $providers->get_list('all');

        $this->assertCount(1, $first);
        $this->assertSame($first, $second);
    }

    public function testGetListReturnsCachedTransientWithoutQuery(): void
    {
        $providers = $this->makeInstance();
        $post = $this->wpFaker->post([
            'ID' => 11,
            'post_name' => 'youtube',
            'post_type' => 'epi_embed',
        ]);

        stubs(['get_post_meta' => '']);
        expect('get_transient')->once()->andReturn([$post]);
        // when the transient already holds the posts, no query is made
        expect('get_posts')->never();

        $list = $providers->get_list('all');

        $this->assertCount(1, $list);
    }

    public function testGetListCustomMergesCustomAndGoogle(): void
    {
        $providers = $this->makeInstance();
        $custom = $this->wpFaker->post(['ID' => 20, 'post_name' => 'custom', 'post_type' => 'epi_embed']);
        $google = $this->wpFaker->post(['ID' => 21, 'post_name' => 'google-maps', 'post_type' => 'epi_embed']);

        stubs([
            'get_transient' => false,
            'set_transient' => true,
            'get_post_meta' => '',
        ]);
        // one query for custom providers, one for the google provider
        expect('get_posts')->twice()->andReturn([$custom], [$google]);

        $list = $providers->get_list('custom');

        $this->assertCount(2, $list);
    }

    public function testGetListOembedQueries(): void
    {
        $providers = $this->makeInstance();
        $post = $this->wpFaker->post(['ID' => 30, 'post_name' => 'youtube', 'post_type' => 'epi_embed']);

        stubs([
            'get_transient' => false,
            'set_transient' => true,
            'get_post_meta' => '',
        ]);
        expect('get_posts')->once()->andReturn([$post]);

        $list = $providers->get_list('oembed');

        $this->assertCount(1, $list);
    }

    public function testGetListAllMergesFromCustomAndOembedCaches(): void
    {
        $providers = $this->makeInstance();
        $custom = $this->wpFaker->post(['ID' => 40, 'post_name' => 'custom', 'post_type' => 'epi_embed']);
        $google = $this->wpFaker->post(['ID' => 41, 'post_name' => 'google-maps', 'post_type' => 'epi_embed']);
        $oembed = $this->wpFaker->post(['ID' => 42, 'post_name' => 'youtube', 'post_type' => 'epi_embed']);

        stubs([
            'get_transient' => false,
            'set_transient' => true,
            'get_post_meta' => '',
        ]);
        expect('get_posts')->andReturn([$custom], [$google], [$oembed]);

        // populate the custom + oembed caches first
        $providers->get_list('custom');
        $providers->get_list('oembed');

        // 'all' is now assembled by merging the two cached lists (no further query)
        $all = $providers->get_list('all');

        $this->assertCount(3, $all);
    }

    public function testGetListWithCustomArgsUsesUncachedQuery(): void
    {
        $providers = $this->makeInstance();
        $post = $this->wpFaker->post(['ID' => 50, 'post_name' => 'youtube', 'post_type' => 'epi_embed']);

        stubs(['get_post_meta' => '']);
        // custom args skip the transient cache entirely
        expect('get_transient')->never();
        expect('set_transient')->never();
        expect('get_posts')->once()->andReturn([$post]);

        $list = $providers->get_list('all', ['author' => 5]);

        $this->assertCount(1, $list);
    }

    public function testIsAlwaysActiveReturnsTrueWhenCookieSet(): void
    {
        $cookie = (object) ['youtube' => true];
        $embedPrivacy = Mockery::mock();
        $embedPrivacy->shouldReceive('get_cookie')->andReturn($cookie);
        Embed_Privacy::$instance = $embedPrivacy;

        $this->assertTrue(Providers::is_always_active('YouTube'));
    }

    public function testIsAlwaysActiveReturnsFalseWhenCookieMissing(): void
    {
        $cookie = (object) ['vimeo' => true];
        $embedPrivacy = Mockery::mock();
        $embedPrivacy->shouldReceive('get_cookie')->andReturn($cookie);
        Embed_Privacy::$instance = $embedPrivacy;

        $this->assertFalse(Providers::is_always_active('youtube'));
    }

    public function testIsDisabledUsesPostId(): void
    {
        $post = $this->wpFaker->post(['ID' => 60, 'post_type' => 'epi_embed']);

        expect('get_post_meta')->once()->with(60, 'is_disabled', true)->andReturn('yes');

        $this->assertTrue(Providers::is_disabled($post));
    }

    public function testIsDisabledFallsBackToCurrentPost(): void
    {
        expect('get_the_ID')->once()->andReturn(61);
        expect('get_post_meta')->once()->with(61, 'is_disabled', true)->andReturn('no');

        $this->assertFalse(Providers::is_disabled());
    }

    public function testSanitizeNameLowercasesAndStripsTrailingNumber(): void
    {
        $this->assertSame('youtube', Providers::sanitize_name('YouTube-2'));
        $this->assertSame('google-maps', Providers::sanitize_name('Google Maps'));
    }
}
