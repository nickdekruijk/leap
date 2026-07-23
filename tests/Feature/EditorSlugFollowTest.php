<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\Fixtures\SlugResource;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * A slug follows its title in the editor: silently while the record is still fresh and the
 * slug has not been hand-edited, or as an inline suggestion otherwise (a hand-set slug, or a
 * record older than leap.slug_follow_minutes — whose live URL must not change silently).
 */
class EditorSlugFollowTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);
        $app['config']->set('leap.slug_follow_minutes', 60);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->text('html_title')->nullable();
            $table->timestamps();
        });

        Leap::context()->setModule(SlugResource::class);
    }

    private function editor(array $data, array $overrides = []): Editor
    {
        $editor = new Editor;

        (new ReflectionProperty(Editor::class, 'activeLocale'))->setValue($editor, 'nl');
        $editor->data = $data;
        $editor->slugFresh = $overrides['slugFresh'] ?? true;
        $editor->slugCustomized = $overrides['slugCustomized'] ?? ['slug' => ['nl' => false, 'en' => false]];

        return $editor;
    }

    public function test_a_fresh_unedited_slug_follows_the_title_silently(): void
    {
        $editor = $this->editor(['title' => ['nl' => 'Test 2'], 'slug' => ['nl' => 'test']]);

        $editor->updated('data.title.nl', 'Test 2');

        $this->assertSame('test-2', $editor->data['slug']['nl']);
        $this->assertArrayNotHasKey('slug', $editor->slugSuggestion);
    }

    public function test_an_older_record_suggests_instead_of_following(): void
    {
        $editor = $this->editor(
            ['title' => ['nl' => 'Test 2'], 'slug' => ['nl' => 'test']],
            ['slugFresh' => false],
        );

        $editor->updated('data.title.nl', 'Test 2');

        $this->assertSame('test', $editor->data['slug']['nl'], 'The stored slug must not change.');
        $this->assertSame('test-2', $editor->slugSuggestion['slug']['nl']);
    }

    public function test_a_hand_edited_slug_suggests_even_while_fresh(): void
    {
        $editor = $this->editor(
            ['title' => ['nl' => 'Test 2'], 'slug' => ['nl' => 'eigen-slug']],
            ['slugCustomized' => ['slug' => ['nl' => true]]],
        );

        $editor->updated('data.title.nl', 'Test 2');

        $this->assertSame('eigen-slug', $editor->data['slug']['nl']);
        $this->assertSame('test-2', $editor->slugSuggestion['slug']['nl']);
    }

    public function test_an_empty_slug_stays_empty_on_a_title_change(): void
    {
        $editor = $this->editor(['title' => ['nl' => 'Test 2'], 'slug' => ['nl' => '']]);

        $editor->updated('data.title.nl', 'Test 2');

        $this->assertSame('', $editor->data['slug']['nl']);
        $this->assertArrayNotHasKey('slug', $editor->slugSuggestion);
    }

    public function test_editing_the_slug_by_hand_marks_it_customized(): void
    {
        $editor = $this->editor(['title' => ['nl' => 'Test'], 'slug' => ['nl' => 'iets-anders']]);

        $editor->updated('data.slug.nl', 'iets-anders');

        $this->assertTrue($editor->slugCustomized['slug']['nl']);
    }

    public function test_applying_a_suggestion_fills_the_slug_and_lets_it_follow_again(): void
    {
        $editor = $this->editor(
            ['title' => ['nl' => 'Test 2'], 'slug' => ['nl' => 'eigen-slug']],
            ['slugCustomized' => ['slug' => ['nl' => true]]],
        );
        $editor->slugSuggestion = ['slug' => ['nl' => 'test-2']];

        $editor->applySlugSuggestion('slug', 'nl');

        $this->assertSame('test-2', $editor->data['slug']['nl']);
        $this->assertFalse($editor->slugCustomized['slug']['nl']);
        $this->assertArrayNotHasKey('nl', $editor->slugSuggestion['slug'] ?? []);
    }

    public function test_dismissing_a_suggestion_keeps_the_custom_slug(): void
    {
        $editor = $this->editor(
            ['title' => ['nl' => 'Test 2'], 'slug' => ['nl' => 'eigen-slug']],
            ['slugCustomized' => ['slug' => ['nl' => true]]],
        );
        $editor->slugSuggestion = ['slug' => ['nl' => 'test-2']];

        $editor->dismissSlugSuggestion('slug', 'nl');

        $this->assertSame('eigen-slug', $editor->data['slug']['nl']);
        $this->assertArrayNotHasKey('nl', $editor->slugSuggestion['slug'] ?? []);
        $this->assertTrue($editor->slugCustomized['slug']['nl'], 'The slug stays a deliberate hand edit.');
    }

    public function test_init_state_detects_customization_and_freshness(): void
    {
        $fresh = Article::create(['title' => ['nl' => 'Test'], 'slug' => ['nl' => 'test']]);

        $editor = new Editor;
        (new ReflectionProperty(Editor::class, 'activeLocale'))->setValue($editor, 'nl');
        $editor->data = ['title' => ['nl' => 'Test'], 'slug' => ['nl' => 'test']];

        $init = new ReflectionMethod(Editor::class, 'initSlugState');
        $init->invoke($editor, $fresh);

        $this->assertTrue($editor->slugFresh);
        $this->assertFalse($editor->slugCustomized['slug']['nl'], 'A slug equal to its title-slug is not customized.');

        // A record older than the window is not fresh, and a mismatching slug is customized.
        $old = Article::create(['title' => ['nl' => 'Test'], 'slug' => ['nl' => 'eigen-slug']]);
        $old->forceFill(['created_at' => now()->subHours(2)])->save();

        $editor->data = ['title' => ['nl' => 'Test'], 'slug' => ['nl' => 'eigen-slug']];
        $init->invoke($editor, $old->fresh());

        $this->assertFalse($editor->slugFresh);
        $this->assertTrue($editor->slugCustomized['slug']['nl']);
    }
}
