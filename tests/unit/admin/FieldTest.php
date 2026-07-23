<?php

declare(strict_types=1);

namespace Tests\Unit\admin;

use epiphyt\Embed_Privacy\admin\Field;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Field::class)]
final class FieldTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        stubEscapeFunctions();
        stubTranslationFunctions();
        stubs([
            'wp_parse_args' => static function ($args, $defaults) {
                return \array_merge($defaults, (array) $args);
            },
            // checked() echoes when both values are equal; mimic that so the
            // "checked" branch is observable in the captured output.
            'checked' => static function ($checked, $current = true) {
                if ((string) $checked === (string) $current) {
                    echo ' checked="checked"';
                }
            },
            'wp_kses' => static function ($string) {
                return $string;
            },
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

    public function testGetReturnsEarlyWithoutName(): void
    {
        // guard hits before any value is read
        expect('get_post_meta')->never();
        expect('get_option')->never();

        $output = $this->render(static function () {
            Field::get(['title' => 'Only title']);
        });

        $this->assertSame('', $output);
    }

    public function testGetReturnsEarlyWithoutTitle(): void
    {
        expect('get_post_meta')->never();
        expect('get_option')->never();

        $output = $this->render(static function () {
            Field::get(['name' => 'only_name']);
        });

        $this->assertSame('', $output);
    }

    public function testGetRendersTextFieldFromMeta(): void
    {
        expect('get_post_meta')
            ->once()
            ->with(7, 'my_field', true)
            ->andReturn('stored-value');

        $output = $this->render(static function () {
            Field::get([
                'name' => 'my_field',
                'title' => 'My Field',
            ], 7);
        });

        // meta fields are wrapped in a table row with a label
        $this->assertStringContainsString('<tr>', $output);
        $this->assertStringContainsString('<label for="my_field">My Field</label>', $output);
        $this->assertStringContainsString('name="my_field"', $output);
        $this->assertStringContainsString('value="stored-value"', $output);
        $this->assertStringContainsString('type="text"', $output);
    }

    public function testGetReadsOptionValueForOptionType(): void
    {
        // option type reads from get_option, never from post meta
        expect('get_post_meta')->never();
        expect('get_option')
            ->once()
            ->with('my_option')
            ->andReturn('option-value');

        $output = $this->render(static function () {
            Field::get([
                'name' => 'my_option',
                'option_type' => 'option',
                'title' => 'My Option',
            ]);
        });

        // option fields are not wrapped in a table row
        $this->assertStringNotContainsString('<tr>', $output);
        $this->assertStringContainsString('value="option-value"', $output);
    }

    public function testGetRendersChoiceFieldForCheckbox(): void
    {
        expect('get_post_meta')->once()->andReturn('');

        $output = $this->render(static function () {
            Field::get([
                'name' => 'agree',
                'title' => 'I agree',
                'type' => 'checkbox',
            ]);
        });

        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('<label for="agree">', $output);
    }

    public function testGetChoiceDefaultsValueToYes(): void
    {
        $output = $this->render(static function () {
            Field::get_choice([
                'classes' => 'regular-text',
                'description' => '',
                'name' => 'toggle',
                'option_type' => 'meta',
                'title' => 'Toggle',
                'type' => 'checkbox',
                'validation' => '',
            ], '');
        });

        // empty value falls back to "yes"
        $this->assertStringContainsString('value="yes"', $output);
    }

    public function testGetChoiceMarksCheckedWhenValueMatches(): void
    {
        $output = $this->render(static function () {
            Field::get_choice([
                'classes' => '',
                'description' => '',
                'name' => 'toggle',
                'option_type' => 'meta',
                'title' => 'Toggle',
                'type' => 'checkbox',
                'validation' => '',
                'value' => 'yes',
            ], 'yes');
        });

        $this->assertStringContainsString('checked="checked"', $output);
    }

    public function testGetChoiceRendersPlainDescription(): void
    {
        $output = $this->render(static function () {
            Field::get_choice([
                'classes' => '',
                'description' => 'Just some text',
                'name' => 'toggle',
                'option_type' => 'meta',
                'title' => 'Toggle',
                'type' => 'checkbox',
                'validation' => '',
                'value' => 'yes',
            ], '');
        });

        $this->assertStringContainsString('Just some text', $output);
    }

    public function testGetChoiceAllowsLinksInDescription(): void
    {
        // wp_kses is stubbed in setUp() to pass the string through unchanged
        $output = $this->render(static function () {
            Field::get_choice([
                'classes' => '',
                'description' => 'See <a href="https://example.com">here</a>',
                'name' => 'toggle',
                'option_type' => 'meta',
                'title' => 'Toggle',
                'type' => 'checkbox',
                'validation' => 'allow-links',
                'value' => 'yes',
            ], '');
        });

        $this->assertStringContainsString('<a href="https://example.com">here</a>', $output);
    }

    public function testGetChoiceOptionTypeSkipsTableRow(): void
    {
        $output = $this->render(static function () {
            Field::get_choice([
                'classes' => '',
                'description' => '',
                'name' => 'toggle',
                'option_type' => 'option',
                'title' => 'Toggle',
                'type' => 'radio',
                'validation' => '',
                'value' => 'yes',
            ], '');
        });

        $this->assertStringNotContainsString('<tr>', $output);
        $this->assertStringContainsString('type="radio"', $output);
    }

    public function testGetTextRendersPlainDescription(): void
    {
        $output = $this->render(static function () {
            Field::get_text([
                'classes' => 'regular-text',
                'description' => 'Helpful hint',
                'name' => 'title_field',
                'option_type' => 'meta',
                'title' => 'Title',
                'type' => 'text',
                'validation' => '',
            ], 'current');
        });

        $this->assertStringContainsString('value="current"', $output);
        $this->assertStringContainsString('Helpful hint', $output);
        $this->assertStringContainsString('<tr>', $output);
    }

    public function testGetTextAllowsLinksInDescription(): void
    {
        // wp_kses is stubbed in setUp() to pass the string through unchanged
        $output = $this->render(static function () {
            Field::get_text([
                'classes' => '',
                'description' => 'See <a href="https://example.com">docs</a>',
                'name' => 'title_field',
                'option_type' => 'meta',
                'title' => 'Title',
                'type' => 'text',
                'validation' => 'allow-links',
            ], '');
        });

        $this->assertStringContainsString('<a href="https://example.com">docs</a>', $output);
    }

    public function testGetTextOptionTypeSkipsTableRow(): void
    {
        $output = $this->render(static function () {
            Field::get_text([
                'classes' => '',
                'description' => '',
                'name' => 'title_field',
                'option_type' => 'option',
                'title' => 'Title',
                'type' => 'text',
                'validation' => '',
            ], 'x');
        });

        $this->assertStringNotContainsString('<tr>', $output);
        $this->assertStringContainsString('value="x"', $output);
    }

    public function testGetImageReturnsEarlyWithoutName(): void
    {
        expect('get_post_meta')->never();

        $output = $this->render(static function () {
            Field::get_image(3, ['title' => 'Only title']);
        });

        $this->assertSame('', $output);
    }

    public function testGetImageReturnsEarlyWithoutTitle(): void
    {
        expect('get_post_meta')->never();

        $output = $this->render(static function () {
            Field::get_image(3, ['name' => 'only_name']);
        });

        $this->assertSame('', $output);
    }

    public function testGetImageRendersWithExistingImage(): void
    {
        expect('get_post_meta')
            ->once()
            ->with(3, 'background_image', true)
            ->andReturn('42');
        expect('wp_get_attachment_image')
            ->once()
            ->with(42)
            ->andReturn('<img src="image.png">');

        $output = $this->render(static function () {
            Field::get_image(3, [
                'description' => 'Choose a background',
                'name' => 'background_image',
                'title' => 'Background',
            ]);
        });

        $this->assertStringContainsString('<img src="image.png">', $output);
        $this->assertStringContainsString('name="background_image"', $output);
        $this->assertStringContainsString('value="42"', $output);
        $this->assertStringContainsString('Choose a background', $output);
        // with a value present, the upload button container is hidden
        $this->assertStringContainsString('embed-privacy-image-input-container embed-privacy-hidden', $output);
    }

    public function testGetImageRendersEmptyState(): void
    {
        expect('get_post_meta')->once()->andReturn('');
        expect('wp_get_attachment_image')->once()->with(0)->andReturn('');

        $output = $this->render(static function () {
            Field::get_image(3, [
                'name' => 'background_image',
                'title' => 'Background',
            ]);
        });

        // without a value the image container is hidden instead
        $this->assertStringContainsString('embed-privacy-image-container embed-privacy-hidden', $output);
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
