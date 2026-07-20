<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\ArticleResource;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionProperty;

/**
 * showIf() hides a section field until another field of the same section is filled. The
 * x-show it produced pointed straight at the trigger — which works for a plain field, but
 * a translatable one is stored per locale: {"nl": "", "en": ""}. An object is always
 * truthy in JavaScript, so the dependent field appeared the moment the trigger was touched
 * in any language, and stayed there after it was cleared again.
 */
class SectionShowIfTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);
    }

    private function editor(string $activeLocale = 'nl'): Editor
    {
        // editorLocales() asks the module being edited whether its model is translatable.
        Leap::context()->setModule(ArticleResource::class);

        $editor = new Editor;

        $property = new ReflectionProperty(Editor::class, 'activeLocale');
        $property->setValue($editor, $activeLocale);

        return $editor;
    }

    private function section(bool $translatableTrigger): Section
    {
        return Section::make('news')->attributes(
            Attribute::make('link_label')->translatable($translatableTrigger),
            Attribute::make('link')->showIf('link_label'),
        );
    }

    private function expression(Section $section, string $activeLocale = 'nl'): string
    {
        $link = $section->attributes[1];

        return $this->editor($activeLocale)->showIf($section, $link, 'sections', 2);
    }

    public function test_a_plain_trigger_is_read_as_it_is(): void
    {
        $this->assertSame(
            "\$wire.data['sections'][2]['link_label']",
            $this->expression($this->section(translatableTrigger: false)),
        );
    }

    public function test_a_translatable_trigger_is_read_at_the_locale_being_edited(): void
    {
        $this->assertSame(
            "\$wire.data['sections'][2]['link_label']?.['nl']",
            $this->expression($this->section(translatableTrigger: true)),
        );
    }

    public function test_it_follows_the_locale_the_editor_switched_to(): void
    {
        $this->assertSame(
            "\$wire.data['sections'][2]['link_label']?.['en']",
            $this->expression($this->section(translatableTrigger: true), 'en'),
        );
    }

    /**
     * showWhenTrue() is what this was called before, and projects are using it. It has to
     * keep setting the same thing, or their fields quietly become always-visible.
     */
    public function test_the_old_name_still_works(): void
    {
        $attribute = Attribute::make('link')->showWhenTrue('link_label');

        $this->assertSame('link_label', $attribute->showIf);
    }

    /**
     * A trigger named by a field that is not in the section — a typo, or a field removed
     * later — must not take the editor down. The dependent field simply stays hidden.
     */
    public function test_an_unknown_trigger_falls_back_to_the_plain_path(): void
    {
        $section = Section::make('news')->attributes(
            Attribute::make('link')->showIf('nonexistent'),
        );

        $this->assertSame(
            "\$wire.data['sections'][2]['nonexistent']",
            $this->editor()->showIf($section, $section->attributes[0], 'sections', 2),
        );
    }
}
