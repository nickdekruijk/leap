<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use NickDeKruijk\Leap\Traits\HasMedia;

/**
 * What nearly every content model in a project looks like: soft deleting, so a
 * deleted record can come back, and with it the gallery it was deleted with.
 */
class SoftDeleteMediaModel extends Model
{
    use HasMedia;
    use SoftDeletes;

    protected $table = 'soft_delete_media_models';

    protected $guarded = [];

    public $timestamps = false;
}
