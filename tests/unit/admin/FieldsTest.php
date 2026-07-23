<?php

declare(strict_types=1);

namespace Tests\Unit\admin;

use epiphyt\Embed_Privacy\admin\Fields;
use epiphyt\Embed_Privacy\Embed_Privacy;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

use function Brain\faker;
use function Brain\Monkey\Actions\has as hasAction;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Fields::class)]
final class FieldsTest extends MockeryTestCase
{
    /**
     * @var \Brain\Faker\Providers
     */
    protected $wpFaker;

    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        require_once \dirname(__DIR__, 2) . '/helper/WpErrorStub.php';

        $this->wpFaker = faker()->wp();

        Embed_Privacy::$instance = null;
    }

    /**
     * Build a WP_Post with the given ID and post type via Brain Faker.
     *
     * @param int    $id        The post ID
     * @param string $post_type The post type
     */
    private function makePost(int $id, string $post_type): \WP_Post
    {
        return $this->wpFaker->post([
            'ID' => $id,
            'post_type' => $post_type,
        ]);
    }

    /**
     * Render a callable while capturing its direct output.
     *
     * @param callable $callback The callback to run
     */
    private function render(callable $callback): string
    {
        \ob_start();
        $callback();

        return (string) \ob_get_clean();
    }

    public function testInitRegistersHooks(): void
    {
        $fields = new Fields();
        $fields->init();

        $this->assertNotFalse(hasAction('add_meta_boxes', [$fields, 'add_meta_boxes']));
        $this->assertNotFalse(hasAction('do_meta_boxes', [Fields::class, 'remove_default']));
        $this->assertNotFalse(hasAction('init', [$fields, 'register_default']));
        $this->assertNotFalse(hasAction('save_post_epi_embed', [$fields, 'save']));
        $this->assertNotFalse(hasFilter('map_meta_cap', [Fields::class, 'disallow_deleting_system_embeds']));
    }

    public function testAddMetaBoxesRegistersMetaBox(): void
    {
        stubTranslationFunctions();

        $fields = new Fields();

        expect('add_meta_box')
            ->once()
            ->with(
                'embed-privacy-custom-fields',
                Mockery::any(),
                [$fields, 'get'],
                'epi_embed',
                'normal',
                'high'
            );

        $fields->add_meta_boxes();
    }

    public function testDisallowDeletingReturnsCapsForOtherCapability(): void
    {
        expect('get_post')->never();

        $caps = ['edit_posts'];

        $this->assertSame(
            $caps,
            Fields::disallow_deleting_system_embeds($caps, 'edit_post', 1, [5])
        );
    }

    public function testDisallowDeletingReturnsCapsWithoutPostId(): void
    {
        expect('get_post')->never();

        $caps = ['delete_post'];

        // reset() of [0] is falsy, so the post lookup is skipped
        $this->assertSame(
            $caps,
            Fields::disallow_deleting_system_embeds($caps, 'delete_post', 1, [0])
        );
    }

    public function testDisallowDeletingAddsDoNotAllowForSystemEmbed(): void
    {
        expect('get_post')->once()->with(5)->andReturn($this->makePost(5, 'epi_embed'));
        expect('get_post_meta')->once()->with(5, 'is_system', true)->andReturn('yes');

        $caps = ['delete_post'];
        $result = Fields::disallow_deleting_system_embeds($caps, 'delete_post', 1, [5]);

        $this->assertContains('do_not_allow', $result);
    }

    public function testDisallowDeletingKeepsCapsForNonSystemEmbed(): void
    {
        expect('get_post')->once()->andReturn($this->makePost(5, 'epi_embed'));
        expect('get_post_meta')->once()->andReturn('');

        $caps = ['delete_post'];
        $result = Fields::disallow_deleting_system_embeds($caps, 'delete_post', 1, [5]);

        $this->assertNotContains('do_not_allow', $result);
    }

    public function testDisallowDeletingKeepsCapsForOtherPostType(): void
    {
        expect('get_post')->once()->andReturn($this->makePost(5, 'post'));
        expect('get_post_meta')->never();

        $caps = ['delete_post'];
        $result = Fields::disallow_deleting_system_embeds($caps, 'delete_post', 1, [5]);

        $this->assertNotContains('do_not_allow', $result);
    }

    public function testDisallowDeletingKeepsCapsWhenPostMissing(): void
    {
        expect('get_post')->once()->andReturn(null);
        expect('get_post_meta')->never();

        $caps = ['delete_post'];
        $result = Fields::disallow_deleting_system_embeds($caps, 'delete_post', 1, [5]);

        $this->assertNotContains('do_not_allow', $result);
    }

    public function testGetRendersFieldsTable(): void
    {
        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'wp_parse_args' => static function ($args, $defaults) {
                return \array_merge($defaults, (array) $args);
            },
        ]);
        expect('get_post_meta')->andReturn('value');

        $GLOBALS['post'] = (object) ['ID' => 1];

        $fields = new Fields();
        $fields->fields = [
            [
                'field_type' => 'input',
                'name' => 'privacy_policy_url',
                'title' => 'Privacy Policy URL',
                'type' => 'url',
            ],
        ];

        $output = $this->render(static function () use ($fields) {
            $fields->get();
        });

        $this->assertStringContainsString('class="form-table"', $output);
        $this->assertStringContainsString('name="privacy_policy_url"', $output);

        unset($GLOBALS['post']);
    }

    public function testGetRendersHiddenFieldFirst(): void
    {
        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'wp_parse_args' => static function ($args, $defaults) {
                return \array_merge($defaults, (array) $args);
            },
        ]);
        expect('get_post_meta')->andReturn('secret');

        $GLOBALS['post'] = (object) ['ID' => 2];

        $fields = new Fields();
        $fields->fields = [
            [
                'field_type' => 'input',
                'name' => 'is_system',
                'title' => '',
                'type' => 'hidden',
            ],
        ];

        $output = $this->render(static function () use ($fields) {
            $fields->get();
        });

        // the leading loop prints the hidden input before the table
        $this->assertStringContainsString('type="hidden"', $output);
        $this->assertStringContainsString('value="secret"', $output);

        unset($GLOBALS['post']);
    }

    public function testGetOutputsAdditionalFieldsFromFilter(): void
    {
        $GLOBALS['post'] = (object) ['ID' => 3];

        // the filter returns a string different from the post ID, so it is echoed
        expectApplied('embed_privacy_editor_fields')
            ->once()
            ->with(3)
            ->andReturn('<p>Additional field</p>');

        $fields = new Fields();
        $fields->fields = [];

        $output = $this->render(static function () use ($fields) {
            $fields->get();
        });

        $this->assertStringContainsString('<p>Additional field</p>', $output);

        unset($GLOBALS['post']);
    }

    public function testRegisterMergesFields(): void
    {
        $fields = new Fields();
        $fields->register([
            'foo' => ['name' => 'foo', 'title' => 'Foo'],
        ]);

        $this->assertArrayHasKey('foo', $fields->fields);
        $this->assertSame('Foo', $fields->fields['foo']['title']);
    }

    public function testRegisterDiesOnInvalidAdditionalFields(): void
    {
        stubEscapeFunctions();
        stubTranslationFunctions();

        expectApplied('embed_privacy_register_fields')->andReturn('not-an-array');
        expect('wp_die')
            ->once()
            ->andReturnUsing(static function () {
                throw new RuntimeException('wp_die called');
            });

        $this->expectException(RuntimeException::class);

        $fields = new Fields();
        $fields->register();
    }

    public function testRegisterDefaultAddsDefaultFields(): void
    {
        stubEscapeFunctions();
        stubTranslationFunctions();

        $fields = new Fields();
        $fields->register_default();

        $expected = [
            'privacy_policy_url',
            'background_image',
            'content_item_name',
            'regex_default',
            'is_disabled',
            'is_system',
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $fields->fields);
        }
    }

    public function testRemoveDefaultRemovesMetaBoxes(): void
    {
        expect('remove_meta_box')
            ->times(3)
            ->with('postcustom', 'epi_embed', Mockery::any());

        Fields::remove_default();
    }

    public function testSaveReturnsEarlyWhenGuardFails(): void
    {
        stubs([
            'current_action' => 'save_post',
        ]);

        expect('update_post_meta')->never();
        expect('delete_post_meta')->never();

        $fields = new Fields();
        $fields->fields = [['name' => 'foo']];
        $fields->save(1);
    }

    public function testSaveTriggersDeprecationForSecondParameter(): void
    {
        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'current_action' => 'save_post',
        ]);

        expect('_doing_it_wrong')->once();

        $fields = new Fields();
        $fields->save(1, (object) ['ID' => 1]);
    }

    public function testSaveReturnsOnTrashAction(): void
    {
        stubs([
            'current_action' => 'save_post_epi_embed',
            'sanitize_text_field' => static function ($value) {
                return $value;
            },
            'wp_unslash' => static function ($value) {
                return $value;
            },
        ]);

        $_GET['action'] = 'trash';

        expect('update_post_meta')->never();
        expect('delete_post_meta')->never();

        $fields = new Fields();
        $fields->fields = [['name' => 'foo']];
        $fields->save(1);

        unset($_GET['action']);
    }

    public function testSaveReturnsOnInlineSave(): void
    {
        stubs([
            'current_action' => 'save_post_epi_embed',
            'sanitize_text_field' => static function ($value) {
                return $value;
            },
            'wp_unslash' => static function ($value) {
                return $value;
            },
        ]);

        $_POST['action'] = 'inline-save';

        expect('update_post_meta')->never();
        expect('delete_post_meta')->never();

        $fields = new Fields();
        $fields->fields = [['name' => 'foo']];
        $fields->save(1);

        unset($_POST['action']);
    }

    public function testSaveDiesWithoutCapability(): void
    {
        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'current_action' => 'save_post_epi_embed',
            'current_user_can' => false,
        ]);

        expect('wp_die')
            ->once()
            ->andReturnUsing(static function () {
                throw new RuntimeException('wp_die called');
            });

        $this->expectException(RuntimeException::class);

        $fields = new Fields();
        $fields->save(1);
    }

    public function testSaveUpdatesAndDeletesPostMeta(): void
    {
        stubs([
            'current_action' => 'save_post_epi_embed',
            'current_user_can' => true,
            'sanitize_text_field' => static function ($value) {
                return $value;
            },
            'wp_unslash' => static function ($value) {
                return $value;
            },
        ]);

        // validate_files() asks for the filesystem before reading $_FILES
        $GLOBALS['wp_filesystem'] = Mockery::mock();

        $_POST = [
            'text_field' => 'hello',
            'regex_default' => '/foo\\/bar/',
            'array_field' => ['x' => 'y', 'nested' => ['z']],
        ];

        expect('delete_post_meta')->once()->with(9, 'empty_field');
        expect('update_post_meta')->once()->with(9, 'text_field', 'hello');
        // regex fields keep their raw, unsanitized value
        expect('update_post_meta')->once()->with(9, 'regex_default', '/foo\\/bar/');
        expect('update_post_meta')->once()->with(9, 'array_field', ['x' => 'y', 'nested' => ['z']]);

        $fields = new Fields();
        $fields->fields = [
            ['name' => 'text_field'],
            ['name' => 'regex_default'],
            ['name' => 'empty_field'],
            ['name' => 'array_field'],
        ];
        $fields->save(9);

        $_POST = [];
        unset($GLOBALS['wp_filesystem']);
    }

    public function testSaveProcessesUploadedFiles(): void
    {
        stubs([
            'current_action' => 'save_post_epi_embed',
            'current_user_can' => true,
            'sanitize_file_name' => static function ($name) {
                return $name;
            },
            'wp_check_filetype' => ['ext' => 'png', 'type' => 'image/png'],
        ]);

        $filesystem = Mockery::mock();
        $filesystem->shouldReceive('get_contents')->andReturn('binary-data');
        $GLOBALS['wp_filesystem'] = $filesystem;

        $_FILES = [
            'background_image' => [
                'name' => 'image.png',
                'tmp_name' => '/tmp/php123',
            ],
        ];

        // upload_file() fails early, so no attachment meta is written
        expect('wp_upload_bits')
            ->once()
            ->andReturn(['error' => 'upload failed']);
        expect('update_post_meta')->never();

        $fields = new Fields();
        $fields->fields = [];
        $fields->save(9);

        unset($GLOBALS['wp_filesystem']);
    }

    public function testSaveSkipsInvalidMimeType(): void
    {
        stubs([
            'current_action' => 'save_post_epi_embed',
            'current_user_can' => true,
            'wp_check_filetype' => ['ext' => 'pdf', 'type' => 'application/pdf'],
        ]);

        $GLOBALS['wp_filesystem'] = Mockery::mock();

        $_FILES = [
            'background_image' => [
                'name' => 'document.pdf',
                'tmp_name' => '/tmp/php456',
            ],
        ];

        // an invalid mime type is skipped, so no upload happens
        expect('wp_upload_bits')->never();
        expect('update_post_meta')->never();

        $fields = new Fields();
        $fields->fields = [];
        $fields->save(9);

        unset($GLOBALS['wp_filesystem']);
    }

    public function testUploadFileReturnsZeroOnUploadError(): void
    {
        expect('wp_upload_bits')
            ->once()
            ->with('image.png', null, 'binary')
            ->andReturn(['error' => 'disk full']);

        $this->assertSame(0, Fields::upload_file([
            'content' => 'binary',
            'name' => 'image.png',
        ]));
    }

    public function testUploadFileReturnsZeroWhenInsertFails(): void
    {
        stubs([
            'sanitize_title' => static function ($title) {
                return $title;
            },
            'is_wp_error' => true,
        ]);

        expect('wp_upload_bits')
            ->once()
            ->andReturn([
                'error' => false,
                'file' => '/uploads/image.png',
                'type' => 'image/png',
            ]);
        expect('wp_insert_attachment')
            ->once()
            ->andReturn(Mockery::mock('WP_Error'));

        $this->assertSame(0, Fields::upload_file([
            'content' => 'binary',
            'name' => 'image.png',
        ]));
    }

    protected function tearDown(): void
    {
        Embed_Privacy::$instance = null;

        tearDown();
        parent::tearDown();
    }
}
