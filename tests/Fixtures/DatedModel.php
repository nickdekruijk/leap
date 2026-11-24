<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with a date and a datetime cast, for the index columns that show them.
 */
class DatedModel extends Model
{
    protected $table = 'dated_models';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'starts_on' => 'date',
        'received_at' => 'datetime',
    ];
}
