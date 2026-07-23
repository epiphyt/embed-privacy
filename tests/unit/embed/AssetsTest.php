<?php

declare(strict_types=1);

namespace Tests\Unit\embed;

use epiphyt\Embed_Privacy\embed\Assets;
use epiphyt\Embed_Privacy\embed\Provider;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Assets::class)]
final class AssetsTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        stubEscapeFunctions();
        stubs([
            'wp_json_encode' => function ($data) {
                return \json_encode($data);
            },
        ]);
    }

    public function testGetStaticReturnsEmptyForNoAssets(): void
    {
        $this->assertSame('', Assets::get_static([]));
    }

    public function testGetStaticSkipsAssetsWithoutType(): void
    {
        $this->assertSame('', Assets::get_static([['handle' => 'foo', 'src' => 'https://x/y.js']]));
    }

    public function testGetStaticRendersScriptAsset(): void
    {
        $output = Assets::get_static([
            [
                'type' => 'script',
                'handle' => 'my-script',
                'src' => 'https://example.com/embed.js',
                'version' => '1.2.3',
            ],
        ]);

        $this->assertStringContainsString('<script src="https://example.com/embed.js?ver=1.2.3"', $output);
        $this->assertStringContainsString('id="my-script"', $output);
    }

    public function testGetStaticScriptWithoutHandleOrSrcIsSkipped(): void
    {
        $this->assertSame('', Assets::get_static([['type' => 'script', 'handle' => 'x']]));
        $this->assertSame('', Assets::get_static([['type' => 'script', 'src' => 'https://x/y.js']]));
    }

    public function testGetStaticRendersInlineStringData(): void
    {
        $output = Assets::get_static([
            [
                'type' => 'inline',
                'object_name' => 'myVar',
                'data' => '{&quot;a&quot;:1}',
            ],
        ]);

        // string data is entity-decoded and then JSON-encoded as a whole
        $this->assertStringContainsString('<script>var myVar = ', $output);
        $this->assertStringContainsString('{\\"a\\":1}', $output);
    }

    public function testGetStaticRendersInlineArrayData(): void
    {
        // regression: array data must not throw and is JSON-encoded per key
        $output = Assets::get_static([
            [
                'type' => 'inline',
                'object_name' => 'myVar',
                'data' => ['key' => 'value', 'num' => 5],
            ],
        ]);

        // each scalar value is cast to string before encoding
        $this->assertStringContainsString('var myVar = {"key":"value","num":"5"}', $output);
    }

    public function testGetStaticInlineArraySkipsNonScalarValues(): void
    {
        // non-scalar entries are dropped rather than crashing
        $output = Assets::get_static([
            [
                'type' => 'inline',
                'object_name' => 'myVar',
                'data' => ['keep' => 'yes', 'drop' => ['nested']],
            ],
        ]);

        $this->assertStringContainsString('var myVar = {"keep":"yes"}', $output);
    }

    public function testGetStaticInlineWithoutDataOrObjectNameIsSkipped(): void
    {
        $this->assertSame('', Assets::get_static([['type' => 'inline', 'object_name' => 'x']]));
        $this->assertSame('', Assets::get_static([['type' => 'inline', 'data' => 'x']]));
    }

    public function testGetStaticRendersAssetsInReverseOrder(): void
    {
        $output = Assets::get_static([
            ['type' => 'script', 'handle' => 'first', 'src' => 'https://example.com/1.js'],
            ['type' => 'script', 'handle' => 'second', 'src' => 'https://example.com/2.js'],
        ]);

        // array is reversed then prepended, so the first asset ends up first in output
        $this->assertLessThan(\strpos($output, 'second'), \strpos($output, 'first'));
    }

    public function testConstructWithUnknownProviderHasEmptyAssetData(): void
    {
        $empty = ['path' => null, 'url' => null, 'version' => null];
        $assets = new Assets(new Provider());

        $this->assertSame($empty, $assets->get_background());
        $this->assertSame($empty, $assets->get_thumbnail());
        // logo has no path because there is no matching provider image on disk
        $this->assertNull($assets->get_logo()['path']);
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
