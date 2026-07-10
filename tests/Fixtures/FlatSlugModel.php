<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Traits\HasSlug;
use Spatie\Translatable\HasTranslations;

/**
 * A flat model (no "parent" column) for testing HasSlug's global slug uniqueness.
 */
class FlatSlugModel extends Model
{
    use HasSlug;
    use HasTranslations;

    protected $table = 'flat_slugs';

    protected $guarded = [];

    public array $translatable = ['title', 'slug'];
}
