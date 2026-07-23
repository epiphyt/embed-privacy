<?php

declare(strict_types=1);

namespace {
    // The Post handler references the global \WP_Block_Type_Registry, which is
    // not available in the unit-test environment. Provide a minimal stand-in so
    // Post::get_ignored_blocks() can be exercised.
    if (!\class_exists('WP_Block_Type_Registry')) {
        class WP_Block_Type_Registry
        {
            /**
             * @var array<string, mixed>
             */
            public static $registered = [];

            /**
             * @var \WP_Block_Type_Registry|null
             */
            private static $instance;

            public static function get_instance(): self
            {
                if (self::$instance === null) {
                    self::$instance = new self();
                }

                return self::$instance;
            }

            /**
             * @return array<string, mixed>
             */
            public function get_all_registered(): array
            {
                return self::$registered;
            }
        }
    }
}

namespace Tests\Unit\handler {

    use epiphyt\Embed_Privacy\data\Providers;
    use epiphyt\Embed_Privacy\embed\Provider;
    use epiphyt\Embed_Privacy\Embed_Privacy;
    use epiphyt\Embed_Privacy\handler\Post;
    use Mockery;
    use Mockery\Adapter\Phpunit\MockeryTestCase;
    use PHPUnit\Framework\Attributes\CoversClass;

    use function Brain\faker;
    use function Brain\Monkey\Filters\expectApplied;
    use function Brain\Monkey\Filters\has;
    use function Brain\Monkey\Functions\expect;
    use function Brain\Monkey\Functions\stubs;
    use function Brain\Monkey\setUp;
    use function Brain\Monkey\tearDown;

    #[CoversClass(Post::class)]
    final class PostTest extends MockeryTestCase
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

            // reset singletons so state does not leak between tests
            Embed_Privacy::$instance = null;
            Providers::$instance = null;

            $this->faker = faker();
            $this->wpFaker = $this->faker->wp();
        }

        /**
         * Build a Provider with the given pattern, name and title, bypassing the
         * WordPress-heavy constructor.
         */
        private function provider(string $pattern = '', string $name = '', string $title = ''): Provider
        {
            $reflection = new \ReflectionClass(Provider::class);
            $provider = $reflection->newInstanceWithoutConstructor();

            foreach (['pattern' => $pattern, 'name' => $name, 'title' => $title] as $key => $value) {
                $property = $reflection->getProperty($key);
                $property->setAccessible(true);
                $property->setValue($provider, $value);
            }

            return $provider;
        }

        /**
         * Install an Embed_Privacy singleton (built without its constructor) with
         * the given has_embed value.
         */
        private function installEmbedPrivacy(bool $hasEmbed): void
        {
            $reflection = new \ReflectionClass(Embed_Privacy::class);
            $instance = $reflection->newInstanceWithoutConstructor();
            $instance->has_embed = $hasEmbed;

            Embed_Privacy::$instance = $instance;
        }

        /**
         * Install a Providers singleton (built without its constructor) whose
         * 'all' list is the given provider list.
         *
         * @param \epiphyt\Embed_Privacy\embed\Provider[] $providers
         */
        private function installProviders(array $providers): void
        {
            $reflection = new \ReflectionClass(Providers::class);
            $instance = $reflection->newInstanceWithoutConstructor();
            $property = $reflection->getProperty('list');
            $property->setAccessible(true);
            $property->setValue($instance, ['all' => $providers]);

            Providers::$instance = $instance;
        }

        public function testInitRegistersHooks(): void
        {
            stubs([
                'register_activation_hook' => null,
                'register_deactivation_hook' => null,
            ]);

            Post::init();

            $this->assertNotFalse(has('the_content'));
            $this->assertNotFalse(has('acf_the_content'));
            $this->assertNotFalse(has('do_shortcode_tag'));
            $this->assertNotFalse(has('embed_oembed_html'));
            $this->assertNotFalse(has('render_block'));
            $this->assertNotFalse(has('wp_video_shortcode'));
        }

        public function testClearEmbedCacheSingleSite(): void
        {
            stubs(['is_plugin_active_for_network' => false]);
            expect('get_sites')->never();

            $wpdb = Mockery::mock();
            $wpdb->postmeta = 'wp_postmeta';
            $wpdb->shouldReceive('prepare')->once()->andReturn('SQL');
            $wpdb->shouldReceive('query')->once()->with('SQL')->andReturn(1);
            $GLOBALS['wpdb'] = $wpdb;

            Post::clear_embed_cache();

            unset($GLOBALS['wpdb']);
        }

        public function testClearEmbedCacheNetworkIteratesSites(): void
        {
            stubs([
                'is_plugin_active_for_network' => true,
                // fewer than the batch size (50), so the loop runs once
                'get_sites' => [1, 2],
            ]);

            $wpdb = Mockery::mock();
            $wpdb->postmeta = 'wp_postmeta';
            $wpdb->shouldReceive('get_blog_prefix')->twice()->andReturn('wp_2_');
            $wpdb->shouldReceive('prepare')->twice()->andReturn('SQL');
            $wpdb->shouldReceive('query')->twice()->with('SQL')->andReturn(1);
            $GLOBALS['wpdb'] = $wpdb;

            Post::clear_embed_cache();

            unset($GLOBALS['wpdb']);
        }

        public function testGetIgnoredBlocksReturnsCoreBlocksExceptHtml(): void
        {
            \WP_Block_Type_Registry::$registered = [
                'core/paragraph' => true,
                'core/html' => true,
                'core/image' => true,
                'custom/widget' => true,
            ];

            $blocks = Post::get_ignored_blocks();

            $this->assertContains('core/paragraph', $blocks);
            $this->assertContains('core/image', $blocks);
            $this->assertNotContains('core/html', $blocks);
            $this->assertNotContains('custom/widget', $blocks);

            // a second call is served from the static cache and returns the same list
            $this->assertSame($blocks, Post::get_ignored_blocks());
        }

        public function testHasEmbedReturnsFilterValueWhenNotNull(): void
        {
            expectApplied('embed_privacy_has_embed')->andReturn(true);

            // the filter value wins even for a non-WP_Post argument
            $this->assertTrue(Post::has_embed(5));
        }

        public function testHasEmbedReturnsFalseForNonPost(): void
        {
            // the default filter value is null, so the WP_Post guard applies
            $this->assertFalse(Post::has_embed(5));
        }

        public function testHasEmbedResolvesQueriedObjectWhenPostIsNull(): void
        {
            stubs(['get_queried_object_id' => 42]);
            expect('get_post')->once()->with(42)->andReturn(null);

            // get_post returns null, which is not a WP_Post, so the result is false
            $this->assertFalse(Post::has_embed(null));
        }

        public function testHasEmbedReturnsTrueWhenSingletonFlagSet(): void
        {
            $this->installEmbedPrivacy(true);

            $post = $this->wpFaker->post(['post_content' => 'no embed here']);

            $this->assertTrue(Post::has_embed($post));
        }

        public function testHasEmbedReturnsTrueWhenProviderMatches(): void
        {
            $this->installEmbedPrivacy(false);
            $this->installProviders([$this->provider('/youtube/', 'youtube', 'YouTube')]);

            $post = $this->wpFaker->post(['post_content' => 'Watch on youtube now']);

            $this->assertTrue(Post::has_embed($post));
        }

        public function testHasEmbedReturnsFalseWhenNoProviderMatches(): void
        {
            $this->installEmbedPrivacy(false);
            $this->installProviders([$this->provider('/vimeo/', 'vimeo', 'Vimeo')]);

            $post = $this->wpFaker->post(['post_content' => 'Just some plain text.']);

            $this->assertFalse(Post::has_embed($post));
        }

        protected function tearDown(): void
        {
            Embed_Privacy::$instance = null;
            Providers::$instance = null;

            tearDown();
            parent::tearDown();
        }
    }
}
