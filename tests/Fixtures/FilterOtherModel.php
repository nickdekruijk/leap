<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * The second model on the shared pivot table of FilterModel: its tags must stay out of
 * the FilterResource index filter.
 */
class FilterOtherModel extends Model
{
    protected $table = 'filter_other_models';

    protected $guarded = [];

    public $timestamps = false;

    public function tags(): MorphToMany
    {
        return $this->morphToMany(FilterTag::class, 'filter_taggable', 'filter_taggables', relatedPivotKey: 'filter_tag_id');
    }
}
