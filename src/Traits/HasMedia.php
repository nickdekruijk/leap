<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Classes\ImageUrl;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;

trait HasMedia
{
    /**
     * Take the media links along when the model itself goes.
     *
     * Hooked on deleting rather than deleted so the cleanup runs inside whatever
     * transaction the delete is in, before the row it points at is gone. The
     * listener returns nothing, so it can never halt the delete it rides on.
     *
     * A soft delete is not the end of a record: it can be restored, and it has to
     * come back with the gallery it had. So only a force delete counts, or a plain
     * delete on a model that has no soft deletes to be restored from.
     * isForceDeleting() is true for the whole of delete() during a forceDelete(),
     * including for a record that was already in the bin, because SoftDeletes sets
     * the flag before calling delete() and only clears it once delete() returned.
     *
     * Model events do not fire on a mass delete (Model::where(...)->delete(), a
     * truncate, a raw query). Delete through model instances instead, with
     * Model::where(...)->cursor()->each->delete() or destroy(), or call
     * detachAllMedia() yourself. leap:media --prune cleans up after the rest.
     */
    public static function bootHasMedia(): void
    {
        static::deleting(function ($model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->detachAllMedia();
        });
    }

    /**
     * Delete every mediables row pointing at this model, and return how many went.
     *
     * The Media rows themselves are left standing on purpose: the file is still on
     * disk, and a media row with no links left is exactly what the file manager
     * needs to be allowed to delete it. Left behind, those links keep the file
     * "in use" forever, and once ids restart after a migrate:fresh they hand the
     * old record's photos to whatever new record takes its number.
     *
     * Not $this->media()->detach(): that only matches getMorphClass(), while the
     * editor writes the class name itself. The two are the same string until a
     * project adds a morph map, at which point older rows carry the class name and
     * newer ones the alias, and both have to go.
     *
     * Public because the cases model events do not reach have to be able to call
     * it: a mass delete, a truncate, an importer that renumbers.
     */
    public function detachAllMedia(): int
    {
        return Mediable::query()
            ->whereIn('mediable_type', array_unique([$this->getMorphClass(), static::class]))
            ->where('mediable_id', $this->getKey())
            ->delete();
    }

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
     *
     * The path is encoded, so a file whose name holds a space or a comma still
     * produces a URL that reads back as one. The prefix is left alone: it comes
     * from the project, not from a file name.
     */
    public function mediaAsset(string $attribute): ?string
    {
        $media = $this->mediaFor($attribute)->first();

        return $media ? asset(($this->mediaAssetPrefix ?? 'storage/').ImageUrl::encodePath($media->file_name)) : null;
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
