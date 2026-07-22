<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\SlugResource;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionProperty;

/**
 * The editor's updated() hook refreshes a slug field's placeholder from the source
 * field's value. On a translatable source Livewire may hand it the whole per-locale
 * array rather than the active locale's string, which made Str::slug() throw
 * "Array to string conversion" -- crashing the editor when a title was edited on a
 * multilingual page (e.g. changing the Dutch title of a page with no English content).
 */
class EditorSlugPlaceholderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);
    }

    private function editor(string $activeLocale = 'nl'): Editor
    {
        Leap::context()->setModule(SlugResource::class);

        $editor = new Editor;

        $property = new ReflectionProperty(Editor::class, 'activeLocale');
        $property->setValue($editor, $activeLocale);

        return $editor;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Article::getModel() during editorLocales() touches the table to read casts.
        Schema::create('articles', function ($table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->text('html_title')->nullable();
            $table->timestamps();
        });
    }

    public function test_a_per_locale_array_value_does_not_crash_and_sets_the_active_locale_placeholder(): void
    {
        $editor = $this->editor('nl');

        // Livewire hands the whole per-locale array as the value; the active locale is Dutch.
        $editor->updated('data.title', ['nl' => 'Test', 'en' => '']);

        $property = new ReflectionProperty(Editor::class, 'placeholder');

        $this->assertSame('test', $property->getValue($editor)['slug'] ?? null);
    }

    public function test_a_plain_string_value_still_slugifies(): void
    {
        $editor = $this->editor('nl');

        $editor->updated('data.title.nl', 'Over Ons');

        $property = new ReflectionProperty(Editor::class, 'placeholder');

        $this->assertSame('over-ons', $property->getValue($editor)['slug'] ?? null);
    }
}
