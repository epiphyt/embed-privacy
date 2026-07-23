<?php

declare(strict_types=1);

namespace Tests\Unit\embed;

use epiphyt\Embed_Privacy\embed\Provider;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\faker;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Provider::class)]
final class ProviderTest extends MockeryTestCase
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
        stubs([
            'sanitize_title' => function ($title) {
                return \strtolower(\trim(\preg_replace('/[^a-z0-9]+/i', '-', (string) $title), '-'));
            },
        ]);
        $this->faker = faker();
        $this->wpFaker = $this->faker->wp();
    }

    public function testUnknownProviderWhenConstructedWithoutPost(): void
    {
        $provider = new Provider();

        $this->assertTrue($provider->is_unknown());
        $this->assertSame('', $provider->get_name());
        $this->assertSame('', $provider->get_title());
        $this->assertNull($provider->get_post_object());
        $this->assertFalse($provider->is_disabled());
        $this->assertFalse($provider->is_system());
    }

    public function testSettersAndGettersRoundtrip(): void
    {
        $provider = new Provider();
        $provider->set_background_image_id(42);
        $provider->set_content_name('video');
        $provider->set_description('A description.');
        $provider->set_privacy_policy_url('https://example.com/privacy');
        $provider->set_thumbnail_id(7);
        $provider->set_title('YouTube');

        $this->assertSame(42, $provider->get_background_image_id());
        $this->assertSame('video', $provider->get_content_name());
        $this->assertSame('A description.', $provider->get_description());
        $this->assertSame('https://example.com/privacy', $provider->get_privacy_policy_url());
        $this->assertSame(7, $provider->get_thumbnail_id());
        $this->assertSame('YouTube', $provider->get_title());
    }

    public function testSetPatternNormalizesSlashes(): void
    {
        $provider = new Provider();

        $provider->set_pattern('youtube\.com');
        $this->assertSame('/youtube\.com/', $provider->get_pattern());

        $provider->set_pattern('/vimeo\.com/');
        $this->assertSame('/vimeo\.com/', $provider->get_pattern());

        $provider->set_pattern('///example///');
        $this->assertSame('/example/', $provider->get_pattern());
    }

    public function testSetEmptyPatternStaysEmpty(): void
    {
        $provider = new Provider();
        $provider->set_pattern('');

        $this->assertSame('', $provider->get_pattern());
    }

    public function testSetNameAppliesFilter(): void
    {
        // Brain Monkey returns the filtered value unchanged by default
        $provider = new Provider();
        $provider->set_name('youtube');

        $this->assertSame('youtube', $provider->get_name());
        $this->assertSame('youtube', (string) $provider);
    }

    public function testIsMatchesNameOrTitle(): void
    {
        $provider = new Provider();
        $provider->set_name('youtube');
        $provider->set_title('YouTube');

        $this->assertTrue($provider->is('youtube'));
        $this->assertTrue($provider->is('YouTube'));
        $this->assertFalse($provider->is('vimeo'));
    }

    public function testIsMatchingUsesStoredPattern(): void
    {
        $provider = new Provider();
        $provider->set_pattern('youtube\.com');

        $this->assertTrue($provider->is_matching('https://www.youtube.com/watch?v=1'));
        $this->assertFalse($provider->is_matching('https://vimeo.com/1'));
    }

    public function testIsMatchingUsesAlternativePattern(): void
    {
        $provider = new Provider();
        $provider->set_pattern('youtube\.com');

        // an explicit pattern overrides the stored one
        $this->assertTrue($provider->is_matching('https://vimeo.com/1', '/vimeo\.com/'));
    }

    public function testIsMatchingIsFalseWithoutPattern(): void
    {
        $provider = new Provider();

        $this->assertFalse($provider->is_matching('https://www.youtube.com/'));
    }

    public function testIsSystemCastsToBool(): void
    {
        $provider = new Provider();
        $provider->set_is_system('yes');

        $this->assertTrue($provider->is_system());
    }

    public function testConstructFromPostPopulatesFields(): void
    {
        stubs([
            'get_post_thumbnail_id' => 12,
            'get_post_meta' => function ($id, $key, $single = false) {
                switch ($key) {
                    case 'is_system':
                        return 'yes';
                    case 'is_disabled':
                        return 'no';
                    case 'regex_default':
                        return '/youtube\.com/';
                    case 'privacy_policy_url':
                        return 'https://youtube.com/privacy';
                    case 'background_image':
                        return 5;
                    case 'content_item_name':
                        return 'video';
                }

                return '';
            },
        ]);
        $post = $this->wpFaker->post([
            'ID' => 1,
            'post_content' => 'Watch our video.',
            'post_name' => 'youtube',
            'post_title' => 'YouTube',
            'post_type' => 'epi_embed',
        ]);

        $provider = new Provider($post);

        $this->assertFalse($provider->is_unknown());
        $this->assertSame('youtube', $provider->get_name());
        $this->assertSame('YouTube', $provider->get_title());
        $this->assertTrue($provider->is_system());
        $this->assertFalse($provider->is_disabled());
        $this->assertSame('/youtube\.com/', $provider->get_pattern());
        $this->assertSame('Watch our video.', $provider->get_description());
        $this->assertSame('https://youtube.com/privacy', $provider->get_privacy_policy_url());
        $this->assertSame(5, $provider->get_background_image_id());
        $this->assertSame(12, $provider->get_thumbnail_id());
        $this->assertSame('video', $provider->get_content_name());
        $this->assertSame($post, $provider->get_post_object());
        // the extended pattern wraps the default pattern in tag matchers
        $this->assertStringContainsString('original_pattern', $provider->get_pattern('extended'));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
