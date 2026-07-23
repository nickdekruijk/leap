<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\SlugResource;
use NickDeKruijk\Leap\Tests\Fixtures\TreeSlugModel;
use NickDeKruijk\Leap\Tests\Fixtures\TreeSlugResource;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionProperty;

/**
 * HasSlug scopes slug uniqueness to siblings (same parent), but the editor's unique rule was
 * global — so a page called "Options" could not be created under a second parent even though
 * the model would allow it, and the two URLs (/a/options, /b/options) are distinct anyway.
 * The unique rule now carries the same sibling scope.
 */
class EditorSiblingSlugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tree_slugs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent')->nullable();
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('flat_slugs', function (Blueprint $table): void {
            $table->id();
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->text('html_title')->nullable();
            $table->timestamps();
        });
    }

    private function editor(string $resource, array $data): Editor
    {
        Leap::context()->setModule($resource);

        $editor = new Editor;
        (new ReflectionProperty(Editor::class, 'activeLocale'))->setValue($editor, '');
        $editor->data = $data;

        return $editor;
    }

    private function slugRule(Editor $editor): string
    {
        $rules = $editor->rules();
        $slugRules = $rules['data.slug'] ?? [];

        return collect($slugRules)->first(fn ($rule) => is_string($rule) && str_starts_with($rule, 'unique:')) ?? '';
    }

    public function test_a_tree_models_slug_rule_is_scoped_to_its_parent(): void
    {
        $editor = $this->editor(TreeSlugResource::class, ['title' => 'Options', 'slug' => 'options', 'parent' => 7]);

        $this->assertStringEndsWith(',parent,7', $this->slugRule($editor));
    }

    public function test_a_root_record_scopes_on_null(): void
    {
        $editor = $this->editor(TreeSlugResource::class, ['title' => 'Options', 'slug' => 'options', 'parent' => null]);

        $this->assertStringEndsWith(',parent,NULL', $this->slugRule($editor));
    }

    public function test_a_flat_model_keeps_a_global_unique_rule(): void
    {
        // SlugResource's model has no "parent" column, so nothing is appended.
        $editor = $this->editor(SlugResource::class, ['title' => 'News', 'slug' => 'news']);

        $this->assertStringNotContainsString(',parent,', $this->slugRule($editor));
    }

    public function test_the_scope_is_not_appended_twice_on_a_second_call(): void
    {
        $editor = $this->editor(TreeSlugResource::class, ['title' => 'Options', 'slug' => 'options', 'parent' => 7]);

        $first = $this->slugRule($editor);
        $second = $this->slugRule($editor);

        $this->assertSame($first, $second);
        $this->assertSame(1, substr_count($second, ',parent,'));
    }

    public function test_only_a_root_page_may_use_the_reserved_homepage_slug(): void
    {
        $child = $this->editor(TreeSlugResource::class, ['title' => 'Home', 'slug' => '/', 'parent' => 7]);
        $root = $this->editor(TreeSlugResource::class, ['title' => 'Home', 'slug' => '/', 'parent' => null]);

        $this->assertContains('not_in:/', $child->rules()['data.slug']);
        $this->assertNotContains('not_in:/', $root->rules()['data.slug']);

        // "/" deeper in the tree collides with its parent's own URL, so it is refused there.
        $this->assertTrue(Validator::make(['data' => $child->data], $child->rules())->fails());
        $this->assertFalse(Validator::make(['data' => $root->data], $root->rules())->fails());
    }

    public function test_the_reserved_slug_error_explains_itself(): void
    {
        $child = $this->editor(TreeSlugResource::class, ['title' => 'Home', 'slug' => '/', 'parent' => 7]);

        $validator = Validator::make(['data' => $child->data], $child->rules(), $child->messages(), $child->validationAttributes());

        $this->assertSame(
            'Only the homepage — a page without a parent — can use “/” as its slug.',
            $validator->messages()->first('data.slug'),
        );
    }

    public function test_the_reserved_slug_rule_is_not_added_twice_on_a_second_call(): void
    {
        $editor = $this->editor(TreeSlugResource::class, ['title' => 'Home', 'slug' => '/', 'parent' => 7]);

        $editor->rules();

        $this->assertSame(1, count(array_keys($editor->rules()['data.slug'], 'not_in:/', true)));
    }

    /**
     * The real thing, on the multilingual path a page tree actually uses: the slug column
     * holds {"en": "options"}, so the rule addresses slug->en and carries the sibling scope.
     */
    public function test_the_same_slug_validates_under_a_different_parent_but_not_under_the_same_one(): void
    {
        config()->set('leap.locales', ['en' => 'English']);

        TreeSlugModel::create(['title' => ['en' => 'Options'], 'parent' => 1]);

        $fails = function (?int $parent): bool {
            $editor = $this->editor(TreeSlugResource::class, [
                'title' => ['en' => 'Options'],
                'slug' => ['en' => 'options'],
                'parent' => $parent,
            ]);
            (new ReflectionProperty(Editor::class, 'activeLocale'))->setValue($editor, 'en');

            return Validator::make(['data' => $editor->data], $editor->rules())->fails();
        };

        $this->assertTrue($fails(1), 'A sibling already uses this slug.');
        $this->assertFalse($fails(2), 'Another parent is free to reuse it.');
    }
}
