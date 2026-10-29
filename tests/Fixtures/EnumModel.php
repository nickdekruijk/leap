<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with columns cast to an enum, for EnumResource.
 */
class EnumModel extends Model
{
    protected $table = 'enum_models';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => EnumStatus::class,
            'locked_status' => EnumStatus::class,
            'consented_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
