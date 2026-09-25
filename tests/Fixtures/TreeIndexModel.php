<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A plain tree model (has a "parent" column) for testing the treeview index.
 */
class TreeIndexModel extends Model
{
    protected $table = 'tree_index';

    protected $guarded = [];

    public $timestamps = false;
}
