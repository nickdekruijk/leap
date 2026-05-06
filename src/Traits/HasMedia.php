<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Models\Media;

trait HasMedia
{
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', config('leap.table_prefix').'mediables')->withPivot('mediable_attribute')->orderBy('mediable_attribute');
    }

    /**
     * Return the asset for the first media for the given attribute
     */
    public function mediaAsset(string $attribute): ?string
    {
        $media = $this->mediaFor($attribute)->first();

        return $media ? asset(($this->mediaAssetPrefix ?? 'storage/').$media->file_name) : null;
    }

    /**
     * Return the filename for the first media for the given attribute
     */
    public function mediaFile(string $attribute): ?string
    {
        $media = $this->mediaFor($attribute)->first();

        return $media ? $media->file_name : null;
    }

    /**
     * Return the media for the given attribute
     */
    public function mediaFor(string $attribute): Collection
    {
        return $this->media->where('pivot.mediable_attribute', $attribute);
    }

    /**
     * Return the alt text for the first media for the given attribute, locale-aware
     */
    public function mediaAlt(string $attribute, ?string $locale = null): string
    {
        return $this->mediaFor($attribute)->first()?->alt($locale) ?? '';
    }
}
