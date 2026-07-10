<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Traits\HasSlug;
use Spatie\Translatable\HasTranslations;

/**
 * A tree model (has a "parent" column) for testing HasSlug's sibling-scoped slug
 * uniqueness — the same slug may repeat under different parents.
 */
class TreeSlugModel extends Model
{
    use HasSlug;
    use HasTranslations;

    protected $table = 'tree_slugs';

    protected $guarded = [];

    public array $translatable = ['title', 'slug'];
}
