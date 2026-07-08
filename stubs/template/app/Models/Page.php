<?php

namespace App\Models;

use App\Http\Controllers\PageController;
use App\Traits\HasSections;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use NickDeKruijk\Leap\Traits\HasMedia;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
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
