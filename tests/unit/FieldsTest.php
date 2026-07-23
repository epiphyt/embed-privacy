<?php

declare(strict_types=1);

namespace Tests\Unit;

use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\Fields;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Tests for the deprecated custom fields wrapper class. Every public method
 * emits a deprecation notice and delegates to the new admin\Fields / admin\Field
 * implementation.
 */
#[CoversClass(Fields::class)]
final class FieldsTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        $this->resetInstance();
        Embed_Privacy::$instance = null;

        stubEscapeFunctions();
        stubTranslationFunctions();
    }

    /**
     * Reset the private static singleton between tests.
     */
    private function resetInstance(): void
    {
        $property = new ReflectionProperty(Fields::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Replace the Embed_Privacy singleton with a stub exposing a mocked
     * fields property.
     *
     * @param \Mockery\MockInterface $fields The mocked fields object
     */
    private function stubEmbedPrivacyFields($fields): void
    {
        Embed_Privacy::$instance = (object) ['fields' => $fields];
    }

    public function testConstructorRegistersInstance(): void
    {
        $fields = new Fields();

        $this->assertSame($fields, Fields::get_instance());
    }

    public function testGetInstanceCreatesAndReusesInstance(): void
    {
        $instance = Fields::get_instance();

        $this->assertInstanceOf(Fields::class, $instance);
        $this->assertSame($instance, Fields::get_instance());
    }

    public function testInitTriggersDeprecation(): void
    {
        expect('_doing_it_wrong')->once();

        ( new Fields() )->init();
    }

    public function testAddMetaBoxesDelegates(): void
    {
        expect('_doing_it_wrong')->once();

        $inner = Mockery::mock();
        $inner->shouldReceive('add_meta_boxes')->once();
        $this->stubEmbedPrivacyFields($inner);

        ( new Fields() )->add_meta_boxes();
    }

    public function testEnqueueAdminAssetsDelegates(): void
    {
        expect('_doing_it_wrong')->once();
        // User_Interface::enqueue_assets() returns early without a screen
        stubs([
            'get_current_screen' => static function () {
                return null;
            },
        ]);
        expect('wp_enqueue_script')->never();

        ( new Fields() )->enqueue_admin_assets('post.php');
    }

    public function testGetTheFieldsHtmlDelegates(): void
    {
        expect('_doing_it_wrong')->once();

        $inner = Mockery::mock();
        $inner->shouldReceive('get')->once();
        $this->stubEmbedPrivacyFields($inner);

        ( new Fields() )->get_the_fields_html();
    }

    public function testGetTheImageFieldHtmlDelegates(): void
    {
        expect('_doing_it_wrong')->once();
        stubs([
            'wp_parse_args' => static function ($args, $defaults) {
                return \array_merge($defaults, (array) $args);
            },
        ]);
        // empty attributes make Field::get_image() return before rendering
        expect('get_post_meta')->never();

        ( new Fields() )->get_the_image_field_html(1, []);
    }

    public function testGetTheInputFieldHtmlDelegates(): void
    {
        expect('_doing_it_wrong')->once();
        stubs([
            'wp_parse_args' => static function ($args, $defaults) {
                return \array_merge($defaults, (array) $args);
            },
        ]);
        // empty attributes make Field::get() return before reading values
        expect('get_post_meta')->never();
        expect('get_option')->never();

        ( new Fields() )->get_the_input_field_html(1, []);
    }

    public function testRegisterDelegates(): void
    {
        expect('_doing_it_wrong')->once();

        $registered = ['foo' => ['name' => 'foo']];
        $inner = Mockery::mock();
        $inner->shouldReceive('register')->once()->with($registered);
        $this->stubEmbedPrivacyFields($inner);

        ( new Fields() )->register($registered);
    }

    public function testRegisterDefaultFieldsDelegates(): void
    {
        expect('_doing_it_wrong')->once();

        $inner = Mockery::mock();
        $inner->shouldReceive('register_default')->once();
        $this->stubEmbedPrivacyFields($inner);

        ( new Fields() )->register_default_fields();
    }

    public function testRemoveDefaultFieldsDelegates(): void
    {
        expect('_doing_it_wrong')->once();

        $inner = Mockery::mock();
        $inner->shouldReceive('remove_default')->once();
        $this->stubEmbedPrivacyFields($inner);

        ( new Fields() )->remove_default_fields();
    }

    public function testSaveFieldsDelegates(): void
    {
        expect('_doing_it_wrong')->once();

        $inner = Mockery::mock();
        $inner->shouldReceive('save')->once()->with(42);
        $this->stubEmbedPrivacyFields($inner);

        ( new Fields() )->save_fields(42, (object) ['ID' => 42]);
    }

    public function testUploadFileDelegatesAndReturnsId(): void
    {
        expect('_doing_it_wrong')->once();

        $file = ['name' => 'image.png', 'content' => 'binary'];
        $inner = Mockery::mock();
        $inner->shouldReceive('upload_file')->once()->with($file)->andReturn(123);
        $this->stubEmbedPrivacyFields($inner);

        $this->assertSame(123, ( new Fields() )->upload_file($file));
    }

    protected function tearDown(): void
    {
        $this->resetInstance();
        Embed_Privacy::$instance = null;

        tearDown();
        parent::tearDown();
    }
}
