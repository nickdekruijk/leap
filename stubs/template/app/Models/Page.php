<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use NickDeKruijk\Leap\Models\Mediable;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasFactory;
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

    public function sections($attribute = 'sections')
    {
        $sections = $this->$attribute;
        foreach (Mediable::with('media')->where('model_type', self::class)->where('model_id', $this->id)->get() as $media) {
            $modelAttribute = explode('.', $media->mediable_attribute);
            if ($modelAttribute[0] == $attribute) {
                $sections[$modelAttribute[1]][$modelAttribute[2]] = $media;
            }
            // dd($media->model_attribute, $sections);
        }

        $sections = collect($sections)->sortBy('_sort') ?? [];
        // $t = $mediables[0]->media->getDownloadUrlAttribute();
        // foreach ($sections as $id => $section) {
        //     $media = $mediables->where('model_attribute', $section['_name'])->first();
        //     dd($section, $mediables, $media);
        // }
        return $sections;
    }
}
