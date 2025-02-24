<?php

namespace App\Models;

use App\Traits\HasSections;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasFactory;
    use HasSections;
    use HasTranslations;
    use SoftDeletes;

    public $translatable = [
        'title',
        'head',
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
        'home' => 'integer',
        'sections' => 'array',
        'meta' => 'array',
    ];

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
