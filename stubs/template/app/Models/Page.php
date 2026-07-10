<?php

namespace App\Models;

use App\Http\Controllers\PageController;
use App\Traits\HasSections;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Contracts\Sitemapable;
use NickDeKruijk\Leap\Traits\HasDocumentMeta;
use NickDeKruijk\Leap\Traits\HasMedia;
use Spatie\Translatable\HasTranslations;

class Page extends Model implements Sitemapable
{
    use HasDocumentMeta;
    use HasFactory;
    use HasMedia;
    use HasSections;
    use HasSlug;
    use HasTranslations;
    use SoftDeletes;

    public $translatable = [
        'title',
        'html_title',
        'slug',
        'description',
        'video_id',
        'body',
        'meta',
    ];

    protected $casts = [
        'active' => 'boolean',
        'published_at' => 'datetime',
        'menuitem' => 'boolean',
        'sections' => 'array',
        'meta' => 'array',
    ];

    /**
     * Flush the cached page tree whenever a page changes, so admin edits show up
     * immediately (see config('leap.cache') and PageController::flushPageCache()).
     */
    protected static function booted(): void
    {
        static::saved(fn () => PageController::flushPageCache());
        static::deleted(fn () => PageController::flushPageCache());
        static::restored(fn () => PageController::flushPageCache());
    }

    /**
     * documentTitle() and ogImageUrl() come from NickDeKruijk\Leap\Traits\HasDocumentMeta.
     */

    /**
     * Sitemapable: the page tree's entries. The tree-walking path logic lives in
     * PageController (which also builds the navigation), so this delegates there.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    public static function sitemapEntries(): Collection
    {
        return PageController::sitemapEntries();
    }

    public function scopePublished($query)
    {
        return $query->where('published_at', '<=', now())->orWhereNull('published_at');
    }

    public function scopeMenu($query, $parent = null)
    {
        return $query->active()->where('menuitem', 1)->where('parent', $parent);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)->published()->orderBy('sort');
    }
}
