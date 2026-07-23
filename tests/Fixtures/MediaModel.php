<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Traits\HasMedia;

/**
 * A frontend model with media attached, as a project would write it: the trait
 * plus a table with nothing media-specific in it, since the link lives entirely
 * in the mediables pivot.
 */
class MediaModel extends Model
{
    use HasMedia;

    protected $table = 'media_models';

    protected $guarded = [];

    public $timestamps = false;
}
