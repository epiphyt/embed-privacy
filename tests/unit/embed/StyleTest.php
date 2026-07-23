<?php

declare(strict_types=1);

namespace Tests\Unit\embed;

use epiphyt\Embed_Privacy\embed\Provider;
use epiphyt\Embed_Privacy\embed\Style;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Style::class)]
final class StyleTest extends MockeryTestCase
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

    public function testGetReturnsEmptyStringForUnknownElement(): void
    {
        $style = new Style(new Provider());

        $this->assertSame('', $style->get('container'));
    }

    public function testRegisterAndGetFormatsCss(): void
    {
        $style = new Style(new Provider());
        $style->register('container', 'color', 'red');
        $style->register('container', 'display', 'flex');

        $this->assertSame('color: red; display: flex;', $style->get('container'));
    }

    public function testRegisterIsScopedPerElement(): void
    {
        $style = new Style(new Provider());
        $style->register('logo', 'width', '40px');

        $this->assertSame('width: 40px;', $style->get('logo'));
        $this->assertSame('', $style->get('container'));
    }

    public function testAspectRatioFromNumericAttributes(): void
    {
        $style = new Style(new Provider(), null, [
            'height' => '315',
            'width' => '560',
        ]);

        $this->assertSame('aspect-ratio: 560/315;', $style->get('container'));
    }

    public function testAspectRatioStripsUnits(): void
    {
        $style = new Style(new Provider(), null, [
            'height' => '315px',
            'width' => '560px',
        ]);

        $this->assertSame('aspect-ratio: 560/315;', $style->get('container'));
    }

    public function testNoAspectRatioWhenPercentageHeight(): void
    {
        // a percentage height means the aspect ratio cannot be determined, so none is registered
        $style = new Style(new Provider(), null, [
            'height' => '50%',
            'width' => '560',
        ]);

        $this->assertSame('', $style->get('container'));
    }

    public function testNoAspectRatioWhenDimensionMissing(): void
    {
        $style = new Style(new Provider(), null, ['width' => '560']);

        $this->assertSame('', $style->get('container'));
    }

    public function testNoAspectRatioWhenIgnoreFlagSet(): void
    {
        $style = new Style(new Provider(), null, [
            'height' => '315',
            'width' => '560',
            'ignore_aspect_ratio' => true,
        ]);

        $this->assertSame('', $style->get('container'));
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
