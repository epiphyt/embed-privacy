<?php

declare(strict_types=1);

namespace Tests\Unit;

use epiphyt\Embed_Privacy\Embed_Privacy_Widget_Output_Filter;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

use function Brain\Monkey\Filters\has as hasFilter;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Embed_Privacy_Widget_Output_Filter::class)]
final class WidgetOutputFilterTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            '_doing_it_wrong' => null,
        ]);

        $this->resetInstance();
    }

    private function resetInstance(): void
    {
        $property = new ReflectionProperty(Embed_Privacy_Widget_Output_Filter::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    public function testGetInstanceReturnsSingletonAndRegistersFilter(): void
    {
        $first = Embed_Privacy_Widget_Output_Filter::get_instance();
        $second = Embed_Privacy_Widget_Output_Filter::get_instance();

        $this->assertInstanceOf(Embed_Privacy_Widget_Output_Filter::class, $first);
        $this->assertSame($first, $second);
        $this->assertNotFalse(hasFilter('dynamic_sidebar_params', [$first, 'filter_dynamic_sidebar_params']));
    }

    public function testDefaultProperties(): void
    {
        $instance = Embed_Privacy_Widget_Output_Filter::get_instance();

        $this->assertSame('embed_privacy_widget_output_filter', $instance->id_base);
        $this->assertSame('Embed Privacy', $instance->name);
        $this->assertSame('widget_embed_privacy_widget_output_filter', $instance->option_name);
        $this->assertFalse($instance->updated);
        $this->assertSame([], $instance->widget_options);
    }

    public function testFilterDynamicSidebarParamsReturnsUnchangedInAdmin(): void
    {
        stubs(['is_admin' => true]);

        $instance = Embed_Privacy_Widget_Output_Filter::get_instance();
        $params = [['widget_id' => 'widget-1']];

        $this->assertSame($params, $instance->filter_dynamic_sidebar_params($params));
    }

    public function testFilterDynamicSidebarParamsSwapsCallback(): void
    {
        stubs(['is_admin' => false]);

        $GLOBALS['wp_registered_widgets'] = [
            'widget-1' => [
                'callback' => 'original_widget_callback',
            ],
        ];

        $instance = Embed_Privacy_Widget_Output_Filter::get_instance();
        $params = [['widget_id' => 'widget-1']];

        $result = $instance->filter_dynamic_sidebar_params($params);

        // $sidebar_params is returned unchanged
        $this->assertSame($params, $result);
        // the original callback is stored and the callback is replaced with display_widget
        $this->assertSame(
            'original_widget_callback',
            $GLOBALS['wp_registered_widgets']['widget-1']['original_callback']
        );
        $this->assertSame(
            [$instance, 'display_widget'],
            $GLOBALS['wp_registered_widgets']['widget-1']['callback']
        );

        unset($GLOBALS['wp_registered_widgets']);
    }

    public function testDisplayWidgetFiltersOriginalOutput(): void
    {
        $GLOBALS['wp_registered_widgets'] = [
            'widget-1' => [
                'callback' => 'placeholder',
                'original_callback' => static function (): void {
                    echo 'ORIGINAL_OUTPUT';
                },
            ],
        ];

        expect('apply_filters')
            ->once()
            ->with('embed_privacy_widget_output', 'ORIGINAL_OUTPUT', 'widget-1', 'sidebar-1')
            ->andReturnUsing(static function ($tag, $output) {
                return $output;
            });

        $instance = Embed_Privacy_Widget_Output_Filter::get_instance();

        $this->expectOutputString('ORIGINAL_OUTPUT');

        $instance->display_widget(['widget_id' => 'widget-1', 'id' => 'sidebar-1']);

        // the original callback must be restored after execution
        $this->assertSame(
            $GLOBALS['wp_registered_widgets']['widget-1']['original_callback'],
            $GLOBALS['wp_registered_widgets']['widget-1']['callback']
        );

        unset($GLOBALS['wp_registered_widgets']);
    }

    public function testDisplayWidgetProducesNoOutputWhenCallbackNotCallable(): void
    {
        $GLOBALS['wp_registered_widgets'] = [
            'widget-1' => [
                'callback' => 'placeholder',
                'original_callback' => 'this_callback_does_not_exist_xyz',
            ],
        ];

        // apply_filters must never be reached for a non-callable original callback
        expect('apply_filters')->never();

        $instance = Embed_Privacy_Widget_Output_Filter::get_instance();

        $this->expectOutputString('');

        $instance->display_widget(['widget_id' => 'widget-1', 'id' => 'sidebar-1']);

        unset($GLOBALS['wp_registered_widgets']);
    }

    protected function tearDown(): void
    {
        $this->resetInstance();

        tearDown();
        parent::tearDown();
    }
}
