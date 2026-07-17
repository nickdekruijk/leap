<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;
use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A translatable attribute is stored as json, so ordering the index by the column
 * itself compared json objects rather than the text in them: every row sorted equal,
 * and descending read exactly the same as ascending. Ordering by a plain column was
 * never affected, which is what made it look like a text-only, descending-only fault.
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
        // so an assertion can pin down which of them was ordered by.
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
                    Attribute::make('title')->index(1),
                    Attribute::make('author')->index(2),
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
}
