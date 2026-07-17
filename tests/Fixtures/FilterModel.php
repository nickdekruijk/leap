<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A model with a polymorphic pivot and a foreign key, for exercising the index filters
 * of FilterResource. The pivot table is shared with FilterOtherModel, the way a site
 * tags several content types from one vocabulary.
 */
class FilterModel extends Model
{
    protected $table = 'filter_models';

    protected $guarded = [];

    public $timestamps = false;

    public function tags(): MorphToMany
    {
        return $this->morphToMany(FilterTag::class, 'filter_taggable', 'filter_taggables', relatedPivotKey: 'filter_tag_id');
    }
}
