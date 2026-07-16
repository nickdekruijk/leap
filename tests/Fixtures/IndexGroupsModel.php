<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with one column of every shape an index can be ordered by, for exercising
 * Resource::indexGroupable(). Never queried — the casts are the point, and getCasts()
 * reads them without touching a database.
 *
 * "views" is deliberately an integer column that declares nothing: no cast, and its
 * attribute gets no ->number() either. It is the case the casts cannot catch.
 */
class IndexGroupsModel extends Model
{
    protected $table = 'index_groups_models';

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'active' => 'boolean',
        'meta' => 'array',
    ];
}
