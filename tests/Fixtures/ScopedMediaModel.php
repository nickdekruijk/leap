<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Traits\HasMedia;

/**
 * A model a project hides part of behind a global scope: published, tenant,
 * locale. The record is still there, so its media links are still in use, and
 * anything looking for orphans has to ask without the scope.
 */
class ScopedMediaModel extends Model
{
    use HasMedia;

    protected $table = 'scoped_media_models';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $query): void {
            $query->where('active', true);
        });
    }
}
