<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;
use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A translatable attribute is stored as json, so a query naming its column plainly
 * gets the whole object rather than the text in it. Ordering compared json objects:
 * every row sorted equal, and descending read exactly the same as ascending. A search
 * matched the raw json, keys and all, so looking for "nl" found every row. Neither
 * touched a plain column, which is what made the first look like a text-only,
 * descending-only fault.
 *
 * Mind what this suite can and cannot show. It runs on SQLite, which has no json type:
 * the column is text, "ORDER BY title" compares the raw json string, and since spatie
 * writes the keys in config order that string happens to sort by the first locale's
 * value. So the reported symptom -- ascending and descending reading alike -- does not
 * reproduce here at all; it needs MySQL, where json is a type and objects compare as
 * objects. Only test_a_translatable_column_orders_by_the_active_locale fails against
 * the old code on SQLite, because ordering by the raw json follows whichever locale is
 * written first rather than the one the panel is in. Same root cause, and the only part
 * of it this driver can see.
 */
class ResourceTranslatableQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->text('html_title')->nullable();
            $table->string('author')->nullable();
            $table->timestamps();
        });

        // Insertion order is deliberately neither ascending nor descending, so a failing
        // sort cannot pass by accident. The two languages of a row say different words,
        // so ordering and searching can be pinned to one of them -- and no word contains
        // "nl" or "en", which the search tests look for as the json keys they are.
        foreach ([
            ['nl' => 'Beta', 'en' => 'Bravo', 'author' => 'Carol'],
            ['nl' => 'Delta', 'en' => 'Dodo', 'author' => 'Alice'],
            ['nl' => 'Alpha', 'en' => 'Aloha', 'author' => 'Bob'],
        ] as $row) {
            Article::create([
                'title' => ['nl' => $row['nl'], 'en' => $row['en']],
                'author' => $row['author'],
            ]);
        }
    }

    private function resource(string $orderBy, bool $desc): Resource
    {
        $resource = new class extends Resource
        {
            public $model = Article::class;

            public function attributes(): array
            {
                return [
                    Attribute::make('id')->indexOnly(),
                    Attribute::make('title')->index(1)->searchable(),
                    Attribute::make('author')->index(2)->searchable(),
                ];
            }
        };

        $resource->orderBy = $orderBy;
        $resource->orderDesc = $desc;

        return $resource;
    }

    /**
     * @return array<int, string>
     */
    private function search(string $term): array
    {
        $resource = $this->resource('id', false);
        $resource->search = $term;

        return $resource->indexRows()
            ->map(fn ($row): string => $row->getTranslation('title', 'nl', false))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function titles(bool $desc): array
    {
        return $this->resource('title', $desc)->indexRows()
            ->map(fn ($row): string => $row->getTranslation('title', 'nl', false))
            ->all();
    }

    public function test_a_translatable_column_orders_ascending_by_the_text_not_the_json(): void
    {
        $this->assertSame(['Alpha', 'Beta', 'Delta'], $this->titles(false));
    }

    public function test_a_translatable_column_orders_descending(): void
    {
        $this->assertSame(['Delta', 'Beta', 'Alpha'], $this->titles(true));
    }

    /**
     * The symptom that was reported: the two read the same.
     */
    public function test_descending_is_not_the_same_as_ascending(): void
    {
        $this->assertSame(array_reverse($this->titles(false)), $this->titles(true));
    }

    /**
     * Ordering follows the language the panel is in, not whichever locale happens to
     * sit first in the json.
     */
    public function test_a_translatable_column_orders_by_the_active_locale(): void
    {
        // Last in Dutch, first in English: the two orders cannot agree by accident.
        Article::create(['title' => ['nl' => 'Zulu', 'en' => 'Aardvark']]);

        app()->setLocale('en');

        $this->assertSame(
            ['Aardvark', 'Aloha', 'Bravo', 'Dodo'],
            $this->resource('title', false)->indexRows()
                ->map(fn ($row): string => $row->getTranslation('title', 'en', false))
                ->all(),
        );
    }

    /**
     * A plain column keeps addressing itself: no json path, no behaviour change.
     */
    public function test_a_plain_column_is_unaffected(): void
    {
        $authors = fn (bool $desc): array => $this->resource('author', $desc)->indexRows()->pluck('author')->all();

        $this->assertSame(['Alice', 'Bob', 'Carol'], $authors(false));
        $this->assertSame(['Carol', 'Bob', 'Alice'], $authors(true));
    }

    /**
     * The reported half of this: "nl" is a key of every {"nl": .., "en": ..}, so a
     * search for it matched the raw json of every row. So did "en", and so would any
     * term that happened to appear in the punctuation around the values.
     */
    public function test_searching_for_a_locale_key_finds_nothing(): void
    {
        $this->assertSame([], $this->search('nl'));
        $this->assertSame([], $this->search('en'));
    }

    public function test_searching_a_translatable_column_finds_by_its_text(): void
    {
        $this->assertSame(['Alpha'], $this->search('Alph'));
    }

    /**
     * The panel is the one place the site's languages sit side by side, so a title is
     * worth finding by whatever language it is written in.
     */
    public function test_searching_finds_a_value_in_a_language_other_than_the_active_one(): void
    {
        app()->setLocale('nl');

        // "Aloha" is only what this row's English says; its Dutch is "Alpha".
        $this->assertSame(['Alpha'], $this->search('Aloha'));
    }

    public function test_searching_a_plain_column_is_unaffected(): void
    {
        $this->assertSame(['Delta'], $this->search('Alice'));
    }
}
