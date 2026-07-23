<?php

declare(strict_types=1);

namespace Tests\Unit;

use epiphyt\Embed_Privacy\Migration;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Tests for the migration/upgrade routines and default-provider data builder.
 *
 * The class is almost entirely private methods driving option and DB writes,
 * so most behaviour is exercised through the public entry points (migrate(),
 * register_default_embed_providers(), init(), the admin notice) and, where the
 * logic is otherwise unreachable, through reflection on the private helpers.
 */
#[CoversClass(Migration::class)]
final class MigrationTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        // reset the singleton so each test starts from a known state
        $this->resetInstance();

        stubEscapeFunctions();
        stubTranslationFunctions();
    }

    /**
     * Reset the private static singleton to null.
     */
    private function resetInstance(): void
    {
        $property = (new ReflectionClass(Migration::class))->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Invoke a private/protected method.
     *
     * @param mixed[] $args
     * @return mixed
     */
    private function invokePrivate(Migration $object, string $method, array $args = [])
    {
        $reflection = new ReflectionClass(Migration::class);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    /**
     * Read a private/protected property.
     *
     * @return mixed
     */
    private function getPrivateProperty(Migration $object, string $property)
    {
        $reflection = new ReflectionClass(Migration::class);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /*
     * ---------------------------------------------------------------------
     * Singleton / construction
     * ---------------------------------------------------------------------
     */

    public function testGetInstanceCreatesSingleton(): void
    {
        $instance = Migration::get_instance();

        $this->assertInstanceOf(Migration::class, $instance);
        $this->assertSame($instance, Migration::get_instance());
    }

    public function testConstructorRegistersItselfAsInstance(): void
    {
        $migration = new Migration();

        $this->assertSame($migration, Migration::get_instance());
    }

    public function testInitRegistersAdminHooks(): void
    {
        expect('add_action')
            ->once()
            ->with('admin_init', Mockery::type('array'), 10, 0);
        expect('add_action')
            ->once()
            ->with('admin_notices', Mockery::type('array'));

        $migration = new Migration();
        $migration->init();
    }

    /*
     * ---------------------------------------------------------------------
     * Option helpers (get/update/delete) — assert the "embed_privacy_" prefix
     * ---------------------------------------------------------------------
     */

    public function testGetOptionPrefixesOptionName(): void
    {
        expect('get_option')
            ->once()
            ->with('embed_privacy_migrate_version', 'fallback')
            ->andReturn('1.2.0');

        $migration = new Migration();

        $this->assertSame(
            '1.2.0',
            $this->invokePrivate($migration, 'get_option', ['migrate_version', 'fallback'])
        );
    }

    public function testUpdateOptionPrefixesOptionName(): void
    {
        expect('update_option')
            ->once()
            ->with('embed_privacy_is_migrating', 123)
            ->andReturn(true);

        $migration = new Migration();

        $this->assertTrue(
            $this->invokePrivate($migration, 'update_option', ['is_migrating', 123])
        );
    }

    public function testDeleteOptionPrefixesOptionName(): void
    {
        expect('delete_option')
            ->once()
            ->with('embed_privacy_is_migrating')
            ->andReturn(true);

        $migration = new Migration();

        $this->assertTrue(
            $this->invokePrivate($migration, 'delete_option', ['is_migrating'])
        );
    }

    /*
     * ---------------------------------------------------------------------
     * migrate() — deprecated arguments and early-return guards
     * ---------------------------------------------------------------------
     */

    public function testMigrateWarnsAboutFirstDeprecatedParameter(): void
    {
        stubs(['wp_doing_ajax' => true]);
        expect('_doing_it_wrong')->once();

        $migration = new Migration();
        $migration->migrate('deprecated');
    }

    public function testMigrateWarnsAboutSecondDeprecatedParameter(): void
    {
        stubs(['wp_doing_ajax' => true]);
        expect('_doing_it_wrong')->once();

        $migration = new Migration();
        $migration->migrate(null, 'deprecated');
    }

    public function testMigrateReturnsEarlyDuringAjax(): void
    {
        stubs(['wp_doing_ajax' => true]);
        // no option access happens once the ajax guard fires
        expect('get_option')->never();
        expect('update_option')->never();

        $migration = new Migration();
        $migration->migrate();
    }

    public function testMigrateReturnsEarlyWhileAnotherMigrationRuns(): void
    {
        stubs([
            'wp_doing_ajax' => false,
            'get_option' => (string) \time(), // recent, non-legacy timestamp
        ]);
        // an in-progress migration must not start another one
        expect('update_option')->never();

        $migration = new Migration();
        $migration->migrate();
    }

    public function testMigrateDoesNotBlockOnLegacyMigratingValue(): void
    {
        // legacy value "1" must NOT be treated as an active migration; because
        // the version already matches, the run still returns without writing.
        stubs([
            'wp_doing_ajax' => false,
            'is_multisite' => false,
            'get_option' => function ($name, $default = false) {
                if (\str_contains($name, 'is_migrating')) {
                    return '1';
                }

                if (\str_contains($name, 'migrate_version')) {
                    return '1.13.0';
                }

                return $default;
            },
        ]);
        expect('update_option')->never();

        $migration = new Migration();
        $migration->migrate();
    }

    public function testMigrateReturnsEarlyWhenAlreadyUpToDate(): void
    {
        stubs([
            'wp_doing_ajax' => false,
            'is_multisite' => false,
            'get_option' => function ($name, $default = false) {
                if (\str_contains($name, 'is_migrating')) {
                    return false;
                }

                if (\str_contains($name, 'migrate_version')) {
                    return '1.13.0'; // current version
                }

                return $default;
            },
        ]);
        expect('update_option')->never();

        $migration = new Migration();
        $migration->migrate();
    }

    public function testMigrateReadsNetworkVersionOnMultisiteInitial(): void
    {
        stubs([
            'wp_doing_ajax' => false,
            'is_multisite' => true,
            'get_site_option' => '1.13.0', // network already up to date
            'get_option' => function ($name, $default = false) {
                if (\str_contains($name, 'is_migrating')) {
                    return false;
                }

                if (\str_contains($name, 'migrate_version')) {
                    return 'initial';
                }

                return $default;
            },
        ]);
        // network version matches current, so nothing is written
        expect('update_option')->never();

        $migration = new Migration();
        $migration->migrate();
    }

    public function testMigrateRunsAndFinalizesVersion(): void
    {
        stubs([
            'wp_doing_ajax' => false,
            'is_multisite' => false,
            'load_plugin_textdomain' => true,
            'plugin_basename' => 'embed-privacy/embed-privacy.php',
            'get_posts' => [], // every migration step finds no providers
            'get_option' => function ($name, $default = false) {
                if (\str_contains($name, 'migrate_version')) {
                    return '1.12.0'; // one step behind → runs migrate_1_13_0()
                }

                if (\str_contains($name, 'is_migrating')) {
                    return false;
                }

                if (\str_contains($name, 'migration_count')) {
                    return 0;
                }

                return $default;
            },
        ]);

        $updated = [];
        expect('update_option')
            ->times(3)
            ->andReturnUsing(function ($name, $value) use (&$updated) {
                $updated[$name] = $value;

                return true;
            });
        // is_migrating and migration_count are cleaned up on success
        expect('delete_option')->twice()->andReturn(true);

        $migration = new Migration();
        $migration->migrate();

        $this->assertArrayHasKey('embed_privacy_migrate_version', $updated);
        $this->assertSame('1.13.0', $updated['embed_privacy_migrate_version']);
        $this->assertArrayHasKey('embed_privacy_is_migrating', $updated);
    }

    /*
     * ---------------------------------------------------------------------
     * register_default_embed_providers() — the default provider data builder
     * ---------------------------------------------------------------------
     */

    public function testRegisterDefaultEmbedProvidersBuildsProviderList(): void
    {
        $migration = new Migration();
        $migration->register_default_embed_providers();

        $providers = $this->getPrivateProperty($migration, 'providers');

        $this->assertIsArray($providers);
        $this->assertGreaterThanOrEqual(40, \count($providers));
    }

    public function testRegisterDefaultEmbedProvidersHaveConsistentShape(): void
    {
        $migration = new Migration();
        $migration->register_default_embed_providers();

        $providers = $this->getPrivateProperty($migration, 'providers');
        $titles = [];

        foreach ($providers as $provider) {
            $this->assertArrayHasKey('meta_input', $provider);
            $this->assertArrayHasKey('post_content', $provider);
            $this->assertArrayHasKey('post_status', $provider);
            $this->assertArrayHasKey('post_title', $provider);
            $this->assertArrayHasKey('post_type', $provider);

            $this->assertSame('publish', $provider['post_status']);
            $this->assertSame('epi_embed', $provider['post_type']);

            $this->assertArrayHasKey('is_system', $provider['meta_input']);
            $this->assertSame('yes', $provider['meta_input']['is_system']);
            $this->assertArrayHasKey('privacy_policy_url', $provider['meta_input']);
            $this->assertArrayHasKey('regex_default', $provider['meta_input']);

            $titles[] = $provider['post_title'];
        }

        // provider titles must be unique
        $this->assertSame(\array_unique($titles), $titles);
    }

    public function testRegisterDefaultEmbedProvidersContainWellKnownEntries(): void
    {
        $migration = new Migration();
        $migration->register_default_embed_providers();

        $providers = $this->getPrivateProperty($migration, 'providers');
        $byTitle = [];

        foreach ($providers as $provider) {
            $byTitle[$provider['post_title']] = $provider;
        }

        // stubTranslationFunctions returns the source string, so titles are literal
        $this->assertArrayHasKey('YouTube', $byTitle);
        $this->assertArrayHasKey('X', $byTitle);
        $this->assertArrayHasKey('Amazon Kindle', $byTitle);

        $this->assertSame(
            '/(https?:)?\\\/\\\/(?:.+?.)?youtu(?:.be|be.com)/',
            $byTitle['YouTube']['meta_input']['regex_default']
        );
        $this->assertSame(
            '/\\\/\\\/(www\\\.)?(twitter|x)\\\.com/',
            $byTitle['X']['meta_input']['regex_default']
        );
    }

    /*
     * ---------------------------------------------------------------------
     * add_embed() — post + meta insertion
     * ---------------------------------------------------------------------
     */

    public function testAddEmbedInsertsPostAndMeta(): void
    {
        expect('wp_insert_post')
            ->once()
            ->with(Mockery::on(static function ($embed) {
                // meta_input is stripped before insertion
                return ! isset($embed['meta_input']) && $embed['post_title'] === 'Example';
            }))
            ->andReturn(42);
        expect('add_post_meta')->twice();

        $migration = new Migration();
        $this->invokePrivate($migration, 'add_embed', [[
            'meta_input' => [
                'is_system' => 'yes',
                'regex_default' => '/example\\.com/',
            ],
            'post_title' => 'Example',
            'post_type' => 'epi_embed',
        ]]);
    }

    public function testAddEmbedWithoutMetaSkipsMeta(): void
    {
        expect('wp_insert_post')->once()->andReturn(42);
        expect('add_post_meta')->never();

        $migration = new Migration();
        $this->invokePrivate($migration, 'add_embed', [[
            'post_title' => 'No Meta',
            'post_type' => 'epi_embed',
        ]]);
    }

    public function testAddEmbedDoesNotAddMetaWhenInsertFails(): void
    {
        // non-int return (e.g. WP_Error) means no meta is stored
        expect('wp_insert_post')->once()->andReturn(Mockery::mock('WP_Error'));
        expect('add_post_meta')->never();

        $migration = new Migration();
        $this->invokePrivate($migration, 'add_embed', [[
            'meta_input' => ['is_system' => 'yes'],
            'post_title' => 'Broken',
            'post_type' => 'epi_embed',
        ]]);
    }

    /*
     * ---------------------------------------------------------------------
     * create_thumbnails_dir() — guard branch
     * ---------------------------------------------------------------------
     */

    public function testCreateThumbnailsDirReturnsEarlyOnEmptyDirectory(): void
    {
        // Thumbnail::get_directory() returns an empty base_dir on upload errors
        stubs([
            'wp_get_upload_dir' => [
                'basedir' => '',
                'baseurl' => '',
                'error' => 'upload error',
            ],
        ]);
        expect('wp_mkdir_p')->never();

        $migration = new Migration();
        $this->invokePrivate($migration, 'create_thumbnails_dir');
    }

    /*
     * ---------------------------------------------------------------------
     * set_translated_*() — early returns when no translation is active
     * ---------------------------------------------------------------------
     */

    public function testSetTranslatedContentItemNamesReturnsEarlyWithoutTranslation(): void
    {
        // stubTranslationFunctions returns the source string, so the guard fires
        expect('remove_action')->never();
        expect('update_post_meta')->never();

        $migration = new Migration();
        $this->invokePrivate($migration, 'set_translated_content_item_names');
    }

    public function testSetTranslatedDescriptionsReturnsEarlyWithoutTranslation(): void
    {
        expect('remove_action')->never();
        expect('wp_update_post')->never();

        $migration = new Migration();
        $this->invokePrivate($migration, 'set_translated_descriptions');
    }

    /*
     * ---------------------------------------------------------------------
     * register_migration_failed_notice()
     * ---------------------------------------------------------------------
     */

    public function testMigrationFailedNoticeHiddenBelowThreshold(): void
    {
        stubs(['get_option' => 2]); // migration_count below 3

        $migration = new Migration();

        \ob_start();
        $migration->register_migration_failed_notice();
        $output = \ob_get_clean();

        $this->assertSame('', $output);
    }

    public function testMigrationFailedNoticeShownAtThreshold(): void
    {
        stubs([
            'get_option' => function ($name, $default = false) {
                if (\str_contains($name, 'migration_count')) {
                    return 3;
                }

                if (\str_contains($name, 'migrate_version')) {
                    return '1.12.0';
                }

                return $default;
            },
        ]);

        $migration = new Migration();

        \ob_start();
        $migration->register_migration_failed_notice();
        $output = \ob_get_clean();

        $this->assertStringContainsString('notice notice-error', $output);
        $this->assertStringContainsString('embed_privacy_migration_failed_notice', $output);
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
