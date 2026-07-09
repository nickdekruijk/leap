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

    /**
     * The document <title>: a custom html_title is used verbatim; a plain page title
     * gets the site name appended. config('app.name') is only added when there is no
     * html_title.
     */
    public function documentTitle(): string
    {
        return $this->html_title
            ?: trim(($this->title ? $this->title.' — ' : '').config('app.name'));
    }

    /**
     * OG/Twitter image URL from the page's own image, then its first section image or
     * background. Null when the page has none (the layout falls back to the og_image
     * site setting).
     */
    public function ogImageUrl(): ?string
    {
        $file = $this->mediaFor('images')->first()?->file_name;
        if (! $file) {
            foreach ($this->sections() as $section) {
                $file = ($section['image'] ?? null)?->first()?->file_name
                    ?? ($section['background'] ?? null)?->first()?->file_name;
                if ($file) {
                    break;
                }
            }
        }

        return $file ? url('storage/'.$file) : null;
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
