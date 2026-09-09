<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\Fixtures\SlugifyResource;
use NickDeKruijk\Leap\Tests\Fixtures\SlugResource;
use NickDeKruijk\Leap\Tests\Fixtures\TreeSlugResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionProperty;

/**
 * What a slug may look like.
 *
 * Nothing checked this, so whatever was typed was stored: a site running this ended up
 * with a page whose slug was "flora en fauna", and the spaces made its sitemap invalid
 * XML — every address in the file, not only that one. It was not a one-off either; the
 * same site had "Dry needling" and "Manuele therapie" before an editor cleaned them up.
 *
 * The rule allows an accent, because a Dutch or German word reads better with one and
 * the sitemap encodes it anyway, and refuses an uppercase letter, because a URL path is
 * case sensitive and a site that mixes them collects redirects it did not mean to need.
 */
class EditorSlugFormatTest extends TestCase
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

    /**
     * @return array<int, string> The messages for the slug, empty when it passes.
     */
    private function errors(string $slug, ?int $parent = null): array
    {
        $editor = $this->editor(TreeSlugResource::class, ['title' => 'Whatever', 'slug' => $slug, 'parent' => $parent]);

        $validator = Validator::make(['data' => $editor->data], $editor->rules(), $editor->messages());

        return $validator->errors()->get('data.slug');
    }

    public function test_a_slug_may_not_contain_a_space(): void
    {
        $this->assertNotEmpty($this->errors('flora en fauna'));
        $this->assertEmpty($this->errors('flora-en-fauna'));
    }

    public function test_a_slug_may_not_contain_an_uppercase_letter(): void
    {
        // /Praktijk and /praktijk are two addresses, and a site that has both collects
        // redirects it did not mean to need.
        $this->assertNotEmpty($this->errors('Dry-needling'));
        $this->assertEmpty($this->errors('dry-needling'));
    }

    public function test_a_slug_may_carry_an_accent(): void
    {
        $this->assertEmpty($this->errors('cliëntroute'));
        $this->assertEmpty($this->errors('über-uns'));
    }

    public function test_a_slug_may_not_start_or_end_with_a_hyphen_or_double_one(): void
    {
        $this->assertNotEmpty($this->errors('-begin'));
        $this->assertNotEmpty($this->errors('eind-'));
        $this->assertNotEmpty($this->errors('twee--streepjes'));
    }

    public function test_an_empty_slug_still_passes(): void
    {
        // It means "derive one from the title", and in a locale nobody has translated
        // yet it means the page has no address there. A field that must be filled says
        // so with required.
        $this->assertEmpty($this->errors(''));
    }

    public function test_the_homepage_slug_passes_the_format_and_is_refused_below_the_root(): void
    {
        $this->assertEmpty($this->errors('/'));
        $this->assertNotEmpty($this->errors('/', 7));
    }

    public function test_the_message_names_the_shape_rather_than_saying_the_format_is_invalid(): void
    {
        $this->assertSame([__('leap::resource.slug_format')], $this->errors('flora en fauna'));
    }

    public function test_only_a_slug_field_shapes_what_is_typed_into_it(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        $article = Article::create(['title' => 'News', 'slug' => 'news']);

        Leap::context()->setModule(SlugResource::class);
        Leap::context()->setPermissions([
            SlugResource::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);

        $html = Livewire::test(Editor::class)->call('openEditor', $article->id)->html();

        // SlugResource has a title and a slug; only the slug is shaped. Counted on the
        // mask's own call, since the editor itself listens on input to know it was
        // touched — and reads $event.isTrusted there, so the mask's synthetic event
        // does not register as someone typing.
        $this->assertSame(1, substr_count($html, 'clean(value)'));
        $this->assertStringContainsString('setSelectionRange', $html);
        // Shaped while typing, tidied when you leave: a hyphen at the end is usually a
        // word you have not finished, so eating it as you type would make
        // "onze-tarieven" impossible to write.
        $this->assertStringContainsString('x-on:blur', $html);
    }

    public function test_the_older_slugify_declaration_is_shaped_too(): void
    {
        // slugify() sits on the source field and names its target, so the slug field
        // carries no sign of its own that it is one. A project written before
        // slugFrom() existed looks like this, and is exactly where the bad slug came
        // from.
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        $article = Article::create(['title' => 'News', 'slug' => 'news']);

        Leap::context()->setModule(SlugifyResource::class);
        Leap::context()->setPermissions([
            SlugifyResource::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);

        $html = Livewire::test(Editor::class)->call('openEditor', $article->id)->html();

        $this->assertSame(1, substr_count($html, 'clean(value)'));
    }

    public function test_a_slug_field_with_no_rules_of_its_own_is_still_checked(): void
    {
        // SlugResource declares slugFrom() and nothing else, which is all a project has
        // to write for the field to be a slug.
        $editor = $this->editor(SlugResource::class, ['title' => 'News', 'slug' => 'two words']);

        $validator = Validator::make(['data' => $editor->data], $editor->rules(), $editor->messages());

        $this->assertNotEmpty($validator->errors()->get('data.slug'));
    }
}
