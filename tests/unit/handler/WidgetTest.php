<?php

declare(strict_types=1);

namespace Tests\Unit\handler;

use epiphyt\Embed_Privacy\handler\Widget;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Filters\expectAdded;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Widget::class)]
final class WidgetTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testInitAddsFilters(): void
    {
        expectAdded('dynamic_sidebar_params')->once();
        expectAdded('embed_privacy_widget_output')->once();

        Widget::init();
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $first = Widget::get_instance();
        $second = Widget::get_instance();

        $this->assertInstanceOf(Widget::class, $first);
        $this->assertSame($first, $second);
    }

    public function testFilterDynamicSidebarParamsReturnsUnchangedInAdmin(): void
    {
        expect('is_admin')->once()->andReturn(true);

        $params = [['widget_id' => 'text-2']];

        $this->assertSame($params, Widget::filter_dynamic_sidebar_params($params));
    }

    public function testFilterDynamicSidebarParamsSwapsCallback(): void
    {
        expect('is_admin')->once()->andReturn(false);

        $original = static function (): void {
        };
        $GLOBALS['wp_registered_widgets'] = [
            'text-2' => ['callback' => $original],
        ];

        $params = [['widget_id' => 'text-2']];
        $result = Widget::filter_dynamic_sidebar_params($params);

        // params are returned unchanged
        $this->assertSame($params, $result);
        // the original callback is stored and the display callback is swapped in
        $this->assertSame($original, $GLOBALS['wp_registered_widgets']['text-2']['original_callback']);
        $this->assertSame(
            [Widget::class, 'display_widget'],
            $GLOBALS['wp_registered_widgets']['text-2']['callback']
        );

        unset($GLOBALS['wp_registered_widgets']);
    }

    public function testDisplayWidgetReturnsWithoutWidgetId(): void
    {
        // no widget_id in the params means an early return with no output
        Widget::display_widget([]);

        $this->expectNotToPerformAssertions();
    }

    public function testDisplayWidgetReturnsWhenWidgetNotRegistered(): void
    {
        $GLOBALS['wp_registered_widgets'] = [];

        Widget::display_widget(['widget_id' => 'missing']);

        $this->expectNotToPerformAssertions();

        unset($GLOBALS['wp_registered_widgets']);
    }

    public function testDisplayWidgetRendersFilteredOutput(): void
    {
        $GLOBALS['wp_registered_widgets'] = [
            'text-2' => [
                'original_callback' => static function (): void {
                    echo 'widget body';
                },
            ],
        ];

        $this->expectOutputString('widget body');

        Widget::display_widget(['widget_id' => 'text-2', 'id' => 'sidebar-1']);

        unset($GLOBALS['wp_registered_widgets']);
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
