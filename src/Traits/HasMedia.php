<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Models\Media;

trait HasMedia
{
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', config('leap.table_prefix') . 'mediables')->withPivot('mediable_attribute');
    }

    public function mediaAsset(null|string $attribute = null): string|null
    {
        $media = $this->mediaAssets($attribute)->first();
        if ($media) {
            return asset('storage/' . $media->file_name);
        }
        return null;
    }

    public function mediaAssets(null|string $attribute = null): Collection
    {
        if ($attribute) {
            return $this->media->where('pivot.mediable_attribute', $attribute);
        } else {
            return $this->media;
        }
    }
}
