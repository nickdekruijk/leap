<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Tests\TestCase;

class AttributeTest extends TestCase
{
    public function test_slug_from_declares_the_source_on_the_slug_field(): void
    {
        $attribute = Attribute::make('slug')->slugFrom('title');

        $this->assertSame('title', $attribute->slugFrom);
        $this->assertNull($attribute->slugify);
    }

    public function test_slugify_declares_the_relationship_on_the_source_and_makes_it_live(): void
    {
        $attribute = Attribute::make('title')->slugify('slug');

        $this->assertSame('slug', $attribute->slugify);
        $this->assertSame('live', $attribute->wire);
    }

    public function test_translatable_flag_defaults_off_and_can_be_set(): void
    {
        $this->assertFalse(Attribute::make('body')->translatable);
        $this->assertTrue(Attribute::make('body')->translatable()->translatable);
    }

    public function test_label_resolves_a_per_locale_array_to_the_current_locale(): void
    {
        $this->app->setLocale('en');
        $this->assertSame('Title', Attribute::make('x')->label(['nl' => 'Titel', 'en' => 'Title'])->label);

        $this->app->setLocale('nl');
        $this->assertSame('Titel', Attribute::make('x')->label(['nl' => 'Titel', 'en' => 'Title'])->label);
    }

    public function test_label_falls_back_to_the_first_entry_for_an_unknown_locale(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Titel', Attribute::make('x')->label(['nl' => 'Titel', 'en' => 'Title'])->label);
    }

    public function test_placeholder_and_hint_resolve_a_per_locale_array(): void
    {
        $this->app->setLocale('en');

        $attribute = Attribute::make('x')
            ->placeholder(['nl' => 'Voer in', 'en' => 'Enter'])
            ->hint(['nl' => 'Tip', 'en' => 'Hint']);

        $this->assertSame('Enter', $attribute->placeholder);
        $this->assertSame('Hint', $attribute->hint);
    }

    public function test_section_label_resolves_a_per_locale_array(): void
    {
        $this->app->setLocale('en');

        $section = Section::make('slide')->label(['nl' => 'Dia', 'en' => 'Slide']);

        $this->assertSame('Slide', $section->label);
    }
}
