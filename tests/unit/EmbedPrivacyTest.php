<?php

declare(strict_types=1);

namespace Tests\Unit;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\Embed_Privacy;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

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

/**
 * Unit tests for the main plugin singleton.
 *
 * These cover the singleton accessor, constructor-derived state, pure helpers
 * (cookie parsing, DOM node checks), option/meta driven and deprecated
 * delegation branches, plus hook registration.
 */
#[CoversClass(Embed_Privacy::class)]
final class EmbedPrivacyTest extends MockeryTestCase
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

        // reset singletons so cached state doesn't leak between tests
        Embed_Privacy::$instance = null;
        Providers::$instance = null;

        // reset superglobals touched by the class under test
        $_COOKIE = [];
        $_POST = [];

        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'is_admin' => false,
            'wp_doing_ajax' => false,
            '_doing_it_wrong' => static function () {
            },
            'sanitize_text_field' => static function ($value) {
                return $value;
            },
            'wp_unslash' => static function ($value) {
                return $value;
            },
        ]);

        $this->faker = faker();
        $this->wpFaker = $this->faker->wp();
    }

    /**
     * Reset the fake integration state before each integration test.
     */
    private function resetFakeIntegrations(): void
    {
        FakeStaticIntegration::$called = false;
        FakeInstanceIntegration::$called = false;
    }

    public function testGetInstanceReturnsInstance(): void
    {
        $instance = Embed_Privacy::get_instance();

        $this->assertInstanceOf(Embed_Privacy::class, $instance);
    }

    public function testGetInstanceIsSingleton(): void
    {
        $this->assertSame(Embed_Privacy::get_instance(), Embed_Privacy::get_instance());
    }

    public function testConstructorInitializesHandlers(): void
    {
        $instance = Embed_Privacy::get_instance();

        $this->assertInstanceOf(\epiphyt\Embed_Privacy\admin\Fields::class, $instance->fields);
        $this->assertInstanceOf(\epiphyt\Embed_Privacy\Frontend::class, $instance->frontend);
        $this->assertInstanceOf(\epiphyt\Embed_Privacy\handler\Shortcode::class, $instance->shortcode);
        $this->assertInstanceOf(\epiphyt\Embed_Privacy\thumbnail\Thumbnail::class, $instance->thumbnail);
    }

    public function testUseCacheIsTrueOnFrontend(): void
    {
        // is_admin() stubbed to false in setUp
        $this->assertTrue(Embed_Privacy::get_instance()->use_cache);
    }

    public function testUseCacheIsFalseInAdminWithoutAjax(): void
    {
        stubs([
            'is_admin' => true,
            'wp_doing_ajax' => false,
        ]);

        $this->assertFalse(Embed_Privacy::get_instance()->use_cache);
    }

    public function testUseCacheIsTrueInAdminDuringAjax(): void
    {
        stubs([
            'is_admin' => true,
            'wp_doing_ajax' => true,
        ]);

        $this->assertTrue(Embed_Privacy::get_instance()->use_cache);
    }

    public function testGetCookieReturnsEmptyStringWithoutCookie(): void
    {
        $this->assertSame('', Embed_Privacy::get_instance()->get_cookie());
    }

    public function testGetCookieReturnsDecodedObject(): void
    {
        $_COOKIE['embed-privacy'] = '{"youtube":true}';

        $cookie = Embed_Privacy::get_instance()->get_cookie();

        $this->assertIsObject($cookie);
        $this->assertTrue($cookie->youtube);
    }

    public function testGetCookieReturnsEmptyStringForNonObjectJson(): void
    {
        // a JSON array decodes to an array, not an object, and is discarded
        $_COOKIE['embed-privacy'] = '["youtube"]';

        $this->assertSame('', Embed_Privacy::get_instance()->get_cookie());
    }

    public function testGetCookieReturnsEmptyStringForInvalidJson(): void
    {
        $_COOKIE['embed-privacy'] = 'not-json';

        $this->assertSame('', Embed_Privacy::get_instance()->get_cookie());
    }

    public function testGetCookieReturnsCachedValue(): void
    {
        $instance = Embed_Privacy::get_instance();
        $_COOKIE['embed-privacy'] = '{"youtube":true}';

        $first = $instance->get_cookie();
        // change the raw cookie: cached object must still be returned
        $_COOKIE['embed-privacy'] = '{"vimeo":true}';
        $second = $instance->get_cookie();

        $this->assertSame($first, $second);
        $this->assertTrue($second->youtube);
    }

    public function testRunChecksReturnsTrueForEmptyChecks(): void
    {
        $document = new \DOMDocument();
        $element = $document->createElement('iframe');

        $this->assertTrue(Embed_Privacy::get_instance()->run_checks([], $element));
    }

    public function testRunChecksIgnoresNonAttributeChecks(): void
    {
        $document = new \DOMDocument();
        $element = $document->createElement('iframe');
        $checks = [
            [
                'type' => 'something-else',
            ],
        ];

        $this->assertTrue(Embed_Privacy::get_instance()->run_checks($checks, $element));
    }

    public function testRunChecksPassesMatchingAttribute(): void
    {
        $document = new \DOMDocument();
        $element = $document->createElement('iframe');
        $element->setAttribute('src', 'https://www.youtube.com/embed/1');
        $checks = [
            [
                'type' => 'attribute',
                'attribute' => 'src',
                'compare' => '===',
                'value' => 'https://www.youtube.com/embed/1',
            ],
        ];

        $this->assertTrue(Embed_Privacy::get_instance()->run_checks($checks, $element));
    }

    public function testRunChecksFailsMismatchedAttribute(): void
    {
        $document = new \DOMDocument();
        $element = $document->createElement('iframe');
        $element->setAttribute('src', 'https://www.example.com/');
        $checks = [
            [
                'type' => 'attribute',
                'attribute' => 'src',
                'compare' => '===',
                'value' => 'https://www.youtube.com/embed/1',
            ],
        ];

        $this->assertFalse(Embed_Privacy::get_instance()->run_checks($checks, $element));
    }

    /**
     * Exercise the private run_check_compare() through the public run_checks().
     *
     * @param string $compare  Compare operator
     * @param string $attribute  Attribute value on the element
     * @param string $value  Value to compare against
     * @param bool   $expected  Expected outcome
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('compareProvider')]
    public function testRunChecksCompareOperators(
        string $compare,
        string $attribute,
        string $value,
        bool $expected
    ): void {
        $document = new \DOMDocument();
        $element = $document->createElement('iframe');
        $element->setAttribute('width', $attribute);
        $checks = [
            [
                'type' => 'attribute',
                'attribute' => 'width',
                'compare' => $compare,
                'value' => $value,
            ],
        ];

        $this->assertSame($expected, Embed_Privacy::get_instance()->run_checks($checks, $element));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public static function compareProvider(): array
    {
        return [
            'strict equal true' => ['===', '560', '560', true],
            'strict equal false' => ['===', '560', '561', false],
            'loose equal true' => ['==', '560', '560', true],
            'strict not equal true' => ['!==', '560', '561', true],
            'loose not equal false' => ['!=', '560', '560', false],
            'greater than true' => ['>', '560', '100', true],
            'greater equal true' => ['>=', '560', '560', true],
            'less than true' => ['<', '100', '560', true],
            'less equal true' => ['<=', '560', '560', true],
            'unknown operator false' => ['??', '560', '560', false],
        ];
    }

    public function testPreserveBackslashesReturnsEarlyWithoutPostValue(): void
    {
        expect('wp_slash')->never();

        Embed_Privacy::get_instance()->preserve_backslashes();

        $this->assertArrayNotHasKey('regex_default', $_POST);
    }

    public function testPreserveBackslashesSlashesPostValue(): void
    {
        $_POST['regex_default'] = 'a\\b';
        expect('wp_slash')
            ->once()
            ->with('a\\b')
            ->andReturn('a\\\\b');

        Embed_Privacy::get_instance()->preserve_backslashes();

        $this->assertSame('a\\\\b', $_POST['regex_default']);
    }

    public function testSetIgnoredRequestInTemplateIncludeReturnsTemplate(): void
    {
        $template = '/path/to/template.php';

        $result = Embed_Privacy::get_instance()->set_ignored_request_in_template_include($template);

        $this->assertSame($template, $result);
    }

    public function testSetIgnoredRequestInTemplateIncludeAppliesFilter(): void
    {
        expectApplied('embed_privacy_is_ignored_request')
            ->once()
            ->andReturn(true);

        $instance = Embed_Privacy::get_instance();
        $instance->set_ignored_request_in_template_include('/tpl.php');

        $this->assertTrue($instance->is_ignored_request);
    }

    public function testSetIgnoredRequestDeprecatedAppliesFilter(): void
    {
        expectApplied('embed_privacy_is_ignored_request')
            ->once()
            ->andReturn(true);

        $instance = Embed_Privacy::get_instance();
        $instance->set_ignored_request();

        $this->assertTrue($instance->is_ignored_request);
    }

    public function testSetPluginFileUpdatesWhenFileExists(): void
    {
        $instance = Embed_Privacy::get_instance();
        $instance->set_plugin_file(\EPI_EMBED_PRIVACY_FILE);

        $this->assertSame(\EPI_EMBED_PRIVACY_FILE, $instance->plugin_file);
    }

    public function testSetPluginFileIgnoresMissingFile(): void
    {
        $instance = Embed_Privacy::get_instance();
        $before = $instance->plugin_file;
        $instance->set_plugin_file('/does/not/exist-' . \uniqid() . '.php');

        $this->assertSame($before, $instance->plugin_file);
    }

    public function testLoadTextdomainRegistersTextdomain(): void
    {
        stubs([
            'plugin_basename' => static function ($file) {
                return $file;
            },
        ]);
        expect('load_plugin_textdomain')
            ->once()
            ->with('embed-privacy', false, \Mockery::type('string'));

        Embed_Privacy::get_instance()->load_textdomain();
    }

    public function testRegisterPostTypeRegistersEpiEmbed(): void
    {
        expect('register_post_type')
            ->once()
            ->with('epi_embed', \Mockery::type('array'));

        Embed_Privacy::register_post_type();
    }

    public function testInitRegistersHooks(): void
    {
        stubs([
            'get_option' => false,
            'add_shortcode' => true,
            'register_activation_hook' => null,
            'register_deactivation_hook' => null,
        ]);

        Embed_Privacy::get_instance()->init();

        // hooks registered directly by Embed_Privacy::init()
        $this->assertNotFalse(hasAction('init', [Embed_Privacy::class, 'register_post_type']));
        $this->assertNotFalse(hasAction('plugins_loaded'));
        $this->assertNotFalse(hasAction('save_post_epi_embed'));
        $this->assertNotFalse(hasFilter('template_include'));
    }

    public function testInitIntegrationsCallsStaticInit(): void
    {
        $this->resetFakeIntegrations();
        expectApplied('embed_privacy_integrations')
            ->once()
            ->andReturn([FakeStaticIntegration::class]);

        Embed_Privacy::get_instance()->init_integrations();

        $this->assertTrue(FakeStaticIntegration::$called);
    }

    public function testInitIntegrationsCallsInstanceInit(): void
    {
        $this->resetFakeIntegrations();
        expectApplied('embed_privacy_integrations')
            ->once()
            ->andReturn([FakeInstanceIntegration::class]);

        Embed_Privacy::get_instance()->init_integrations();

        $this->assertTrue(FakeInstanceIntegration::$called);
    }

    public function testInitIntegrationsSkipsClassesWithoutInit(): void
    {
        $this->resetFakeIntegrations();
        expectApplied('embed_privacy_integrations')
            ->once()
            ->andReturn([FakeNoInitIntegration::class]);

        // no exception must be thrown; the class is simply skipped
        Embed_Privacy::get_instance()->init_integrations();

        $this->assertFalse(FakeStaticIntegration::$called);
    }

    public function testGetEmbedsReturnsAllProviders(): void
    {
        $providers = $this->wpFaker->posts(2, ['post_type' => 'epi_embed']);
        expect('get_posts')
            ->once()
            ->andReturn($providers);

        $result = Embed_Privacy::get_instance()->get_embeds();

        $this->assertSame($providers, $result);
    }

    public function testGetEmbedsCachesResult(): void
    {
        $providers = $this->wpFaker->posts(2, ['post_type' => 'epi_embed']);
        // get_posts must only be called once thanks to caching
        expect('get_posts')
            ->once()
            ->andReturn($providers);

        $instance = Embed_Privacy::get_instance();
        $first = $instance->get_embeds();
        $second = $instance->get_embeds();

        $this->assertSame($first, $second);
    }

    public function testGetEmbedByNameReturnsMatchingProvider(): void
    {
        $youtube = $this->wpFaker->post([
            'post_name' => 'youtube',
            'post_type' => 'epi_embed',
        ]);
        $vimeo = $this->wpFaker->post([
            'post_name' => 'vimeo',
            'post_type' => 'epi_embed',
        ]);
        expect('get_posts')
            ->once()
            ->andReturn([$vimeo, $youtube]);

        $result = Embed_Privacy::get_instance()->get_embed_by_name('youtube');

        $this->assertSame($youtube, $result);
    }

    public function testGetEmbedByNameReturnsNullForEmptyName(): void
    {
        $this->assertNull(Embed_Privacy::get_instance()->get_embed_by_name(''));
    }

    public function testIsAlwaysActiveProviderReadsFromCookie(): void
    {
        stubs([
            'sanitize_title' => static function ($title) {
                return \strtolower((string) $title);
            },
        ]);
        $_COOKIE['embed-privacy'] = '{"youtube":true}';

        $this->assertTrue(Embed_Privacy::get_instance()->is_always_active_provider('YouTube'));
        $this->assertFalse(Embed_Privacy::get_instance()->is_always_active_provider('vimeo'));
    }

    public function testOutputBufferCallbackReturnsBufferUnchanged(): void
    {
        $buffer = '<p>content</p>';

        $this->assertSame($buffer, Embed_Privacy::get_instance()->output_buffer_callback($buffer));
    }

    public function testReplaceEmbedsDiviReturnsItemUnchanged(): void
    {
        $item = '<iframe></iframe>';

        $this->assertSame($item, Embed_Privacy::get_instance()->replace_embeds_divi($item, 'https://maps.google.com'));
    }

    public function testSetPostTypeDelegatesToRegisterPostType(): void
    {
        expect('register_post_type')
            ->once()
            ->with('epi_embed', \Mockery::type('array'));

        Embed_Privacy::get_instance()->set_post_type();
    }

    public function testGetIgnoredShortcodesReturnsList(): void
    {
        $result = Embed_Privacy::get_instance()->get_ignored_shortcodes();

        $this->assertIsArray($result);
    }

    public function testDeprecatedNoopMethodsRunWithoutError(): void
    {
        // _doing_it_wrong is stubbed in setUp; these deprecated no-ops must
        // simply run to completion without throwing
        $instance = Embed_Privacy::get_instance();
        $instance->deregister_assets();
        $instance->enqueue_assets();
        $instance->start_output_buffer();

        // lightweight sanity check that the object is still intact afterwards
        $property = new ReflectionProperty(Embed_Privacy::class, 'integrations');
        $this->assertIsArray($property->getValue($instance));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}

/**
 * Fake integration exposing a static init() for init_integrations() coverage.
 */
class FakeStaticIntegration
{
    /**
     * @var bool
     */
    public static $called = false;

    public static function init(): void
    {
        self::$called = true;
    }
}

/**
 * Fake integration exposing an instance init() for init_integrations() coverage.
 */
class FakeInstanceIntegration
{
    /**
     * @var bool
     */
    public static $called = false;

    public function init(): void
    {
        self::$called = true;
    }
}

/**
 * Fake integration without an init() method (must be skipped).
 */
class FakeNoInitIntegration
{
}
