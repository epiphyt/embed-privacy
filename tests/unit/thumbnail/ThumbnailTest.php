<?php

declare(strict_types=1);

namespace Tests\Unit\thumbnail;

use epiphyt\Embed_Privacy\thumbnail\provider\SlideShare;
use epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider;
use epiphyt\Embed_Privacy\thumbnail\provider\Vimeo;
use epiphyt\Embed_Privacy\thumbnail\provider\WordPress_TV;
use epiphyt\Embed_Privacy\thumbnail\provider\YouTube;
use epiphyt\Embed_Privacy\thumbnail\Thumbnail;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

use function Brain\faker;
use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Thumbnail::class)]
#[UsesClass(Thumbnail_Provider::class)]
#[UsesClass(SlideShare::class)]
#[UsesClass(Vimeo::class)]
#[UsesClass(WordPress_TV::class)]
#[UsesClass(YouTube::class)]
final class ThumbnailTest extends MockeryTestCase
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

        stubTranslationFunctions();
        stubs([
            'wp_get_upload_dir' => [
                'error' => false,
                'basedir' => '/var/www/uploads',
                'baseurl' => 'https://www.example.com/uploads',
            ],
            'wp_mkdir_p' => true,
        ]);

        $this->faker = faker();
        $this->wpFaker = $this->faker->wp();
    }

    public function testInitReturnsEarlyWhenOptionDisabled(): void
    {
        stubs([
            'get_option' => static function () {
                return false;
            },
        ]);
        // no hook registration must happen when the feature is disabled
        expect('add_action')->never();
        expect('add_filter')->never();

        (new Thumbnail())->init();
    }

    public function testInitRegistersHooksWhenOptionEnabled(): void
    {
        stubs([
            'get_option' => static function () {
                return true;
            },
        ]);

        (new Thumbnail())->init();

        // Actions/Filters\has() return the registered priority (int), not bool true
        $this->assertNotFalse(hasAction('before_delete_post'));
        $this->assertNotFalse(hasAction('init'));
        $this->assertNotFalse(hasAction('post_updated'));
        $this->assertNotFalse(hasFilter('oembed_dataparse'));
    }

    public function testGetDirectoryReturnsPaths(): void
    {
        $directory = Thumbnail::get_directory();

        $this->assertSame('/var/www/uploads/embed-privacy/thumbnails', $directory['base_dir']);
        $this->assertSame(
            'https://www.example.com/uploads/embed-privacy/thumbnails',
            $directory['base_url']
        );
    }

    public function testGetDirectoryReturnsEmptyOnUploadError(): void
    {
        stubs([
            'wp_get_upload_dir' => [
                'error' => 'disk full',
                'basedir' => '/var/www/uploads',
                'baseurl' => 'https://www.example.com/uploads',
            ],
        ]);

        $this->assertSame(
            [
                'base_dir' => '',
                'base_url' => '',
            ],
            Thumbnail::get_directory()
        );
    }

    public function testGetDirectoryReturnsEmptyWhenUploadDirFalsy(): void
    {
        stubs([
            'wp_get_upload_dir' => static function () {
                return false;
            },
        ]);

        $this->assertSame(
            [
                'base_dir' => '',
                'base_url' => '',
            ],
            Thumbnail::get_directory()
        );
    }

    public function testGetMetadataWithoutProviderUsesGlobalKey(): void
    {
        $expected = [
            [
                'post_id' => 1,
                'meta_key' => 'embed_privacy_thumbnail_youtube_abc',
                'meta_value' => 'youtube-abc.jpg',
            ],
        ];
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 'embed_privacy_thumbnail_%')
            ->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->once()->with('SQL', \ARRAY_A)->andReturn($expected);
        $GLOBALS['wpdb'] = $wpdb;

        $this->assertSame($expected, Thumbnail::get_metadata());

        unset($GLOBALS['wpdb']);
    }

    public function testGetMetadataWithProviderLimitsKey(): void
    {
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), 'embed_privacy_thumbnail_youtube_%')
            ->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->once()->andReturn([]);
        $GLOBALS['wpdb'] = $wpdb;

        $this->assertSame([], Thumbnail::get_metadata('youtube'));

        unset($GLOBALS['wpdb']);
    }

    public function testGetProviderByUrlReturnsYouTube(): void
    {
        $provider = (new Thumbnail())->get_provider_by_url('https://www.youtube.com/watch?v=1');

        $this->assertInstanceOf(YouTube::class, $provider);
    }

    public function testGetProviderByUrlMatchesYouTubeShortDomain(): void
    {
        $provider = (new Thumbnail())->get_provider_by_url('https://youtu.be/abc');

        $this->assertInstanceOf(YouTube::class, $provider);
    }

    public function testGetProviderByUrlReturnsVimeo(): void
    {
        $provider = (new Thumbnail())->get_provider_by_url('https://vimeo.com/12345');

        $this->assertInstanceOf(Vimeo::class, $provider);
    }

    public function testGetProviderByUrlReturnsSlideShare(): void
    {
        $provider = (new Thumbnail())->get_provider_by_url('https://www.slideshare.net/foo/bar');

        $this->assertInstanceOf(SlideShare::class, $provider);
    }

    public function testGetProviderByUrlReturnsWordPressTv(): void
    {
        $provider = (new Thumbnail())->get_provider_by_url('https://wordpress.tv/2020/01/foo/');

        $this->assertInstanceOf(WordPress_TV::class, $provider);
    }

    public function testGetProviderByUrlReturnsNullForUnknownDomain(): void
    {
        $this->assertNull((new Thumbnail())->get_provider_by_url('https://www.example.com/embed/1'));
    }

    public function testGetProviderTitlesAreSortedNaturally(): void
    {
        // stubTranslationFunctions() makes _x() return its first argument
        $titles = (new Thumbnail())->get_provider_titles();

        $this->assertSame(
            ['SlideShare', 'Vimeo', 'WordPress TV', 'YouTube'],
            \array_values($titles)
        );
    }

    public function testGetFromProviderReturnsOutputUnchangedForUnknownUrl(): void
    {
        // an unknown URL means every provider's get() early-returns (no HTTP/save)
        $output = '<iframe></iframe>';
        $data = (object) ['html' => '', 'thumbnail_url' => ''];

        $this->assertSame(
            $output,
            (new Thumbnail())->get_from_provider($output, $data, 'https://www.example.com/none')
        );
    }

    public function testGetDataReturnsEmptyForNonPost(): void
    {
        $this->assertSame(
            [
                'thumbnail_path' => '',
                'thumbnail_url' => '',
            ],
            (new Thumbnail())->get_data(null, 'https://www.youtube.com/watch?v=1')
        );
    }

    public function testGetDataReturnsEmptyForUnknownProvider(): void
    {
        $post = $this->wpFaker->post();

        $this->assertSame(
            [
                'thumbnail_path' => '',
                'thumbnail_url' => '',
            ],
            (new Thumbnail())->get_data($post, 'https://www.example.com/none')
        );
    }

    public function testGetDataReturnsEmptyPathsWhenNoThumbnailStored(): void
    {
        $post = $this->wpFaker->post();
        // no thumbnail meta stored => path/url stay empty even for a known provider
        stubs([
            'get_post_meta' => static function () {
                return '';
            },
        ]);

        $this->assertSame(
            [
                'thumbnail_path' => '',
                'thumbnail_url' => '',
            ],
            (new Thumbnail())->get_data($post, 'https://www.youtube.com/watch?v=1')
        );
    }

    public function testDeleteThumbnailsSkipsNonPrefixedMeta(): void
    {
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $GLOBALS['wpdb'] = $wpdb;

        stubs([
            'get_post_meta' => ['some_other_key' => ['value']],
        ]);
        // no prefixed key => nothing is deleted
        expect('wp_delete_file')->never();

        Thumbnail::delete_thumbnails(42);

        unset($GLOBALS['wpdb']);
    }

    public function testDeleteThumbnailsSkipsThumbnailStillInUse(): void
    {
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        // the same file is used by a different post => must not be deleted
        $wpdb->shouldReceive('get_results')->andReturn([
            [
                'post_id' => 99,
                'meta_key' => 'embed_privacy_thumbnail_youtube_abc',
                'meta_value' => 'youtube-abc.jpg',
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        stubs([
            'get_post_meta' => ['embed_privacy_thumbnail_youtube_abc' => ['youtube-abc.jpg']],
        ]);
        expect('wp_delete_file')->never();

        Thumbnail::delete_thumbnails(42);

        unset($GLOBALS['wpdb']);
    }

    public function testDeleteOrphanedReturnsEarlyWithoutPostData(): void
    {
        $_POST = [];
        // empty $_POST => bail before touching metadata
        expect('get_post_meta')->never();

        (new Thumbnail())->delete_orphaned(42, $this->wpFaker->post());

        unset($_POST);
    }

    public function testDeleteOrphanedSkipsNonPrefixedMeta(): void
    {
        $_POST = ['dummy' => '1'];
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $GLOBALS['wpdb'] = $wpdb;

        stubs([
            'get_post_meta' => ['unrelated_key' => ['value']],
        ]);
        $post = $this->wpFaker->post(['post_content' => 'no ids here']);
        // no prefixed meta => nothing is ever deleted
        expect('delete_post_meta')->never();

        (new Thumbnail())->delete_orphaned(42, $post);

        unset($GLOBALS['wpdb'], $_POST);
    }

    public function testRegisterProvidersDoesNotDieForValidProviders(): void
    {
        stubs([
            'apply_filters_deprecated' => static function ($hook, $value) {
                return $value;
            },
        ]);
        // all default providers extend Thumbnail_Provider => no wp_die()
        expect('wp_die')->never();

        (new Thumbnail())->register_providers();
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
