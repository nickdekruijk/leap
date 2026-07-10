<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Contracts\Sitemapable;
use NickDeKruijk\Leap\Traits\HasDocumentMeta;
use NickDeKruijk\Leap\Traits\HasLocaleRouting;
use Spatie\Translatable\HasTranslations;

/**
 * A flat (non-tree) translatable model for exercising HasLocaleRouting,
 * HasDocumentMeta and the Sitemapable default. Its routes are registered in the
 * tests with Route::leapLocalized(), producing article.nl / article.en.
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

    public array $translatable = ['title', 'slug', 'html_title'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
