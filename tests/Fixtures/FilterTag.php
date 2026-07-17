<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * The related model behind the pivot attribute of FilterResource, attached through the
 * polymorphic filter_taggables table.
 */
class FilterTag extends Model
{
    protected $table = 'filter_tags';

    protected $guarded = [];

    public $timestamps = false;
}
