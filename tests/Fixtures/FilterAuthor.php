<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * The related model behind the foreign attribute of FilterResource.
 */
class FilterAuthor extends Model
{
    protected $table = 'filter_authors';

    protected $guarded = [];

    public $timestamps = false;
}
