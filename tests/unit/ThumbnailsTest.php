<?php

declare(strict_types=1);

namespace Tests\Unit;

use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\thumbnail\provider\SlideShare;
use epiphyt\Embed_Privacy\thumbnail\provider\Thumbnail_Provider;
use epiphyt\Embed_Privacy\thumbnail\provider\Vimeo;
use epiphyt\Embed_Privacy\thumbnail\provider\WordPress_TV;
use epiphyt\Embed_Privacy\thumbnail\provider\YouTube;
use epiphyt\Embed_Privacy\thumbnail\Thumbnail;
use epiphyt\Embed_Privacy\Thumbnails;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionProperty;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

// Thumbnails::DIRECTORY is a class constant built from WP_CONTENT_DIR, evaluated when
// the class is autoloaded; define it here so the class can be loaded under test.
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/var/www/wp-content');
}

#[CoversClass(Thumbnails::class)]
#[UsesClass(Embed_Privacy::class)]
#[UsesClass(Thumbnail::class)]
#[UsesClass(Thumbnail_Provider::class)]
#[UsesClass(SlideShare::class)]
#[UsesClass(Vimeo::class)]
#[UsesClass(WordPress_TV::class)]
#[UsesClass(YouTube::class)]
final class ThumbnailsTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        // reset singletons so instances don't leak between tests
        $instance = new ReflectionProperty(Thumbnails::class, 'instance');
        $instance->setValue(null, null);
        Embed_Privacy::$instance = null;

        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'wp_get_upload_dir' => [
                'error' => false,
                'basedir' => '/var/www/uploads',
                'baseurl' => 'https://www.example.com/uploads',
            ],
            'wp_mkdir_p' => true,
        ]);
    }

    public function testConstructorTriggersDoingItWrongAndSetsInstance(): void
    {
        expect('_doing_it_wrong')->once();

        $instance = new Thumbnails();

        $this->assertSame($instance, Thumbnails::get_instance());
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        expect('_doing_it_wrong')->atLeast()->once();

        $first = Thumbnails::get_instance();
        $second = Thumbnails::get_instance();

        $this->assertSame($first, $second);
    }

    public function testInitTriggersDoingItWrong(): void
    {
        expect('_doing_it_wrong')->atLeast()->once();

        Thumbnails::get_instance()->init();
    }

    public function testGetDirectoryDelegatesToThumbnail(): void
    {
        expect('_doing_it_wrong')->atLeast()->once();

        $this->assertSame(
            Thumbnail::get_directory(),
            Thumbnails::get_instance()->get_directory()
        );
    }

    public function testDeleteThumbnailsDelegatesToThumbnail(): void
    {
        $wpdb = Mockery::mock();
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $GLOBALS['wpdb'] = $wpdb;

        stubs([
            'get_post_meta' => ['unrelated_key' => ['value']],
        ]);
        expect('_doing_it_wrong')->atLeast()->once();
        // no prefixed meta => Thumbnail::delete_thumbnails() deletes nothing
        expect('wp_delete_file')->never();

        Thumbnails::get_instance()->delete_thumbnails(42);

        unset($GLOBALS['wpdb']);
    }

    // NOTE: get_supported_providers() is intentionally left uncovered. Its body calls
    // \apply_filters_deprecated( $hook, $providers, '1.9.0' ) with only 3 arguments, but
    // Brain Monkey's native shim for that function requires at least 4, causing an
    // ArgumentCountError before the body runs. It cannot be exercised under Brain Monkey
    // without modifying source.

    public function testCheckOrphanedDelegatesToThumbnailInstance(): void
    {
        Embed_Privacy::$instance = (object) ['thumbnail' => new Thumbnail()];
        $_POST = [];
        expect('_doing_it_wrong')->atLeast()->once();
        // delegation reaches Thumbnail::delete_orphaned(), which bails on empty $_POST
        expect('get_post_meta')->never();

        Thumbnails::get_instance()->check_orphaned(42, (object) ['post_content' => '']);

        unset($_POST);
    }

    public function testGetDataDelegatesToThumbnailInstance(): void
    {
        Embed_Privacy::$instance = (object) ['thumbnail' => new Thumbnail()];
        expect('_doing_it_wrong')->atLeast()->once();

        // delegation reaches Thumbnail::get_data(), which returns empty paths for a non-post
        $this->assertSame(
            [
                'thumbnail_path' => '',
                'thumbnail_url' => '',
            ],
            Thumbnails::get_instance()->get_data(null, 'https://www.youtube.com/watch?v=1')
        );
    }

    public function testGetFromProviderDelegatesToThumbnailInstance(): void
    {
        Embed_Privacy::$instance = (object) ['thumbnail' => new Thumbnail()];
        expect('_doing_it_wrong')->atLeast()->once();

        // unknown URL => every provider get() early-returns, output passes through
        $this->assertSame(
            '<iframe></iframe>',
            Thumbnails::get_instance()->get_from_provider(
                '<iframe></iframe>',
                (object) ['html' => '', 'thumbnail_url' => ''],
                'https://www.example.com/none'
            )
        );
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
