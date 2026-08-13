<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Models\Media;

trait HasMedia
{
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', config('leap.table_prefix').'mediables')
            ->withPivot('mediable_attribute', 'sort')
            ->orderBy('mediable_attribute')
            // The order the editor dragged them into. Without it the order is whatever
            // the database returns, which is by media id, and that is only the same
            // thing while no two models share a file. They do: media rows are keyed on
            // the file's sha256, so a photo also used by an older model keeps that
            // model's lower id and jumps to the front. The first image is the one every
            // card in the frontend shows, so a gallery that looks merely shuffled puts
            // the wrong picture on the overview.
            ->orderByPivot('sort')
            // Two rows with the same sort still have to come out in a fixed order, or
            // the same page renders differently on two requests.
            ->orderByPivot('media_id');
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
     * Return the URL of a resized copy of the first media for the given
     * attribute, or of the file itself when there is nothing to resize.
     */
    public function mediaImage(string $attribute, string|int|null $preset = null): ?string
    {
        return $this->mediaFor($attribute)->first()?->url($preset);
    }

    /**
     * Return a srcset value for the first media for the given attribute.
     *
     * @param  array<int>|null  $widths  Defaults to leap.images.component_widths
     */
    public function mediaSrcset(string $attribute, ?array $widths = null): string
    {
        return $this->mediaFor($attribute)->first()?->srcset($widths) ?? '';
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
