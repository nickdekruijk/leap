<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * The record a preview shows, standing in for a host application's Page. Soft deletes so
 * the preview route can prove a deleted record stays out of reach.
 */
class PreviewableModel extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'previewable_models';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public array $translatable = ['title'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
