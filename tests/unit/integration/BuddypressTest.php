<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\integration\Buddypress;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Buddypress::class)]
final class BuddypressTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        Embed_Privacy::$instance = null;
    }

    /**
     * Inject a mocked Embed_Privacy singleton with a controllable frontend.
     */
    private function mockFrontend(): object
    {
        $frontend = Mockery::mock();
        $instance = Mockery::mock(Embed_Privacy::class);
        $instance->frontend = $frontend;
        Embed_Privacy::$instance = $instance;

        return $frontend;
    }

    public function testInitRegistersHooks(): void
    {
        Buddypress::init();

        $this->assertNotFalse(
            \Brain\Monkey\Actions\has('bp_enqueue_community_scripts', [Buddypress::class, 'register_scripts'])
        );
        $this->assertNotFalse(
            \Brain\Monkey\Actions\has('bp_enqueue_community_scripts', [Buddypress::class, 'enqueue_assets'])
        );
        $this->assertNotFalse(
            \Brain\Monkey\Filters\has('bp_get_activity_content_body', [Buddypress::class, 'replace_activity_content'])
        );
    }

    public function testEnqueueAssetsDelegatesToFrontend(): void
    {
        $frontend = $this->mockFrontend();
        $frontend->shouldReceive('print_assets')->once();

        Buddypress::enqueue_assets();
    }

    public function testRegisterScriptsDelegatesToFrontend(): void
    {
        $frontend = $this->mockFrontend();
        $frontend->shouldReceive('register_assets')->once();

        Buddypress::register_scripts();
    }

    public function testReplaceActivityContentReturnsUnchangedWithoutEmbeds(): void
    {
        Embed_Privacy::$instance = null;
        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'get_option' => false,
            'get_posts' => [],
            'get_transient' => false,
            'set_transient' => true,
            'is_admin' => false,
            'has_blocks' => false,
            'wp_json_encode' => 'json',
            'get_current_blog_id' => 1,
            'get_locale' => 'en_US',
            'home_url' => 'https://www.example.com',
            'sanitize_title' => function ($title) {
                return \strtolower(\trim(\preg_replace('/[^a-z0-9]+/i', '-', (string) $title), '-'));
            },
            'update_meta_cache' => null,
            'wp_generate_uuid4' => '00000000-0000-0000-0000-000000000000',
            'wp_kses' => function ($string) {
                return $string;
            },
            'wp_kses_post' => function ($string) {
                return $string;
            },
            'wp_parse_args' => function ($value, $default) {
                return \array_merge($default, $value);
            },
            'wp_parse_url' => 'parse_url',
        ]);

        $content = '<p>Just some text without any embed.</p>';

        $this->assertSame($content, Buddypress::replace_activity_content($content));
    }

    protected function tearDown(): void
    {
        tearDown();
        Embed_Privacy::$instance = null;
        parent::tearDown();
    }
}
