<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionMethod;

/**
 * A translatable attribute is validated once per locale, but its unique rule named the
 * column plainly: "unique:articles,slug" asks where slug = 'over-ons' while slug holds
 * {"nl": "over-ons", "en": "about-us"}. A json object never equals a string, so the
 * rule matched nothing and every duplicate passed -- and HasSlug then quietly appended
 * a -2 on save, leaving the editor neither warned nor given the slug they typed.
 */
class EditorUniqueRuleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);
    }

    private function rule(mixed $rule, string $locale = 'nl'): mixed
    {
        $method = new ReflectionMethod(Editor::class, 'localeUniqueRule');

        return $method->invoke(new Editor, $rule, $locale);
    }

    public function test_a_unique_rule_is_pointed_at_the_locale_being_validated(): void
    {
        $this->assertSame('unique:articles,slug->nl,5,id', $this->rule('unique:articles,slug,5,id'));
    }

    public function test_only_the_column_moves(): void
    {
        // Table, ignored id, id column and the soft-delete pair all keep their place.
        $this->assertSame(
            'unique:pages,slug->en,NULL,id,deleted_at,NULL',
            $this->rule('unique:pages,slug,NULL,id,deleted_at,NULL', 'en'),
        );
    }

    public function test_a_rule_already_addressing_a_json_key_is_left_alone(): void
    {
        $this->assertSame('unique:pages,meta->author,5,id', $this->rule('unique:pages,meta->author,5,id'));
    }

    public function test_other_rules_are_left_alone(): void
    {
        $this->assertSame('required', $this->rule('required'));
        $this->assertSame('max:255', $this->rule('max:255'));
        $this->assertSame('exists:pages,id', $this->rule('exists:pages,id'));
    }

    /**
     * A Rule object cannot be rewritten as a string, and must survive untouched.
     */
    public function test_a_rule_object_is_left_alone(): void
    {
        $object = Rule::unique('articles', 'slug');

        $this->assertSame($object, $this->rule($object));
    }

    /**
     * The point of the rewrite: the rule has to actually catch a duplicate.
     */
    public function test_the_rewritten_rule_catches_a_duplicate_translatable_value(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->text('html_title')->nullable();
            $table->timestamps();
        });

        $article = Article::create(['slug' => ['nl' => 'over-ons', 'en' => 'about-us']]);

        $fails = fn (string $value, string $rule): bool => Validator::make(['slug' => $value], ['slug' => $rule])->fails();

        // What the rule used to be: a json column compared to a string, matching nothing.
        $this->assertFalse($fails('over-ons', 'unique:articles,slug,NULL,id'), 'Precondition: the old rule caught nothing.');

        $this->assertTrue($fails('over-ons', $this->rule('unique:articles,slug,NULL,id')));
        $this->assertFalse($fails('iets-anders', $this->rule('unique:articles,slug,NULL,id')));

        // Editing the row that holds the value is not a collision with itself.
        $this->assertFalse($fails('over-ons', $this->rule('unique:articles,slug,'.$article->id.',id')));

        // Each language is unique in its own right: "about-us" is only taken in English.
        $this->assertFalse($fails('about-us', $this->rule('unique:articles,slug,NULL,id', 'nl')));
        $this->assertTrue($fails('about-us', $this->rule('unique:articles,slug,NULL,id', 'en')));
    }
}
