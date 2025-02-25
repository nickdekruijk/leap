<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use NickDeKruijk\Leap\Models\Media;

trait HasMedia
{
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', config('leap.table_prefix') . 'mediables')->withPivot('mediable_attribute');
    }
}
