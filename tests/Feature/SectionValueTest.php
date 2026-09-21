<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsResource;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The value a section field hands its input.
 *
 * sections.blade.php used to pass the attribute alone, so an input reading the `value`
 * prop got nothing. A prop declared without a default is simply undefined when it is not
 * passed, which took the whole editor down with "Undefined variable $value" as soon as a
 * section held a json field. It now passes the stored value the way editor.blade.php
 * does for a top level field, translations resolved to the locale being edited.
 */
class SectionValueTest extends TestCase
{
    private function editor(?array $locales = null): Editor
    {
        config(['leap.locales' => $locales]);
        Leap::context()->setModule(SectionsResource::class);

        return new Editor;
    }

    private function attribute(string $name, bool $translatable = false): Attribute
    {
        $attribute = Attribute::make($name);

        return $translatable ? $attribute->translatable() : $attribute;
    }

    public function test_it_returns_a_plain_value(): void
    {
        $value = $this->editor()->sectionValue(
            ['_name' => 'block', 'layout' => 'left'],
            $this->attribute('layout'),
        );

        $this->assertSame('left', $value);
    }

    public function test_a_field_the_section_does_not_have_yet_is_null(): void
    {
        $value = $this->editor()->sectionValue(
            ['_name' => 'block'],
            $this->attribute('meta'),
        );

        $this->assertNull($value);
    }

    public function test_a_translatable_field_resolves_to_the_locale_being_edited(): void
    {
        $editor = $this->editor(['nl' => 'Nederlands', 'en' => 'English']);
        $editor->activeLocale = 'en';

        $value = $editor->sectionValue(
            ['_name' => 'block', 'head' => ['nl' => 'Hallo', 'en' => 'Hello']],
            $this->attribute('head', translatable: true),
        );

        $this->assertSame('Hello', $value);
    }

    /**
     * A json field holds an array that is data, not a translation set, so it has to reach
     * the input whole. This is the one that used to crash the editor.
     */
    public function test_an_array_that_is_not_a_translation_set_is_left_alone(): void
    {
        $meta = ['columns' => 3, 'width' => 800];

        $value = $this->editor(['nl' => 'Nederlands'])->sectionValue(
            ['_name' => 'block', 'meta' => $meta],
            $this->attribute('meta'),
        );

        $this->assertSame($meta, $value);
    }
}
