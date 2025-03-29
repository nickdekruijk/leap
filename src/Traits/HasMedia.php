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

    /**
     * Return the asset for the first media for the given attribute
     *
     * @param string $attribute
     * @return string|null
     */
    public function mediaAsset(string $attribute): string|null
    {
        $media = $this->mediaFor($attribute)->first();
        return $media ? asset(($this->mediaAssetPrefix ?? 'storage/') . $media->file_name) : null;
    }

    /**
     * Return the filename for the first media for the given attribute
     *
     * @param string $attribute
     * @return string|null
     */
    public function mediaFile(string $attribute): string|null
    {
        $media = $this->mediaFor($attribute)->first();
        return $media ? $media->file_name : null;
    }

    /**
     * Return the media for the given attribute
     *
     * @param string $attribute
     * @return Collection
     */
    public function mediaFor(string $attribute): Collection
    {
        return $this->media->where('pivot.mediable_attribute', $attribute);
    }
}
