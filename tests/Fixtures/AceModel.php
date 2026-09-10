<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class AceModel extends Model
{
    protected $table = 'ace_models';

    protected $guarded = [];

    public $timestamps = false;
}
