<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use NickDeKruijk\Leap\Contracts\Sitemapable;
use NickDeKruijk\Leap\Traits\HasDocumentMeta;
use NickDeKruijk\Leap\Traits\HasLocaleRouting;
use Spatie\Translatable\HasTranslations;

/**
 * A flat (non-tree) translatable model for exercising HasLocaleRouting,
 * HasDocumentMeta and the Sitemapable default. Its routes are registered in the
 * tests with Route::leapLocalized(), producing article.nl / article.en.
 *
 * @property int $id
 * @property bool $active
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $html_title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Article extends Model implements Sitemapable
{
    use HasDocumentMeta;
    use HasLocaleRouting;
    use HasTranslations;

    protected $table = 'articles';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public array $translatable = ['title', 'slug', 'html_title', 'description', 'intro'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
