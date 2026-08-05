<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use NickDeKruijk\Leap\Classes\ImageUrl;
use NickDeKruijk\Leap\Leap;

class Media extends Model
{
    protected $casts = [
        'meta' => 'array',
        'history' => 'array',
    ];

    protected $fillable = [
        'disk',
        'file_name',
        'size',
        'mime_type',
        'uuid',
        'sha256',
        'user_id',
        'history',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('leap.table_prefix').'media');
    }

    /**
     * Get a Media model instance for a file, or create one if file exists
     *
     * @param  string  $file  The file name including path
     * @param  string|null  $disk  The storage disk to use, defaults from leap config
     * @return Media|false Returns Media model instance or false if file does not exist
     */
    public static function forFile(string $file_name, ?string $disk = null): Media|false
    {
        $disk = $disk ?: config('leap.filemanager.disk');
        $storage = Storage::disk($disk);

        $file_name = ltrim($file_name, '/');

        if ($storage->exists($file_name)) {
            /**
             * @disregard P1013 Undefined method 'mimeType'
             */
            return static::firstOrCreate([
                'file_name' => $file_name,
                'disk' => $disk,
            ], [
                'size' => $storage->size($file_name),
                'mime_type' => $storage->mimeType($file_name),
                'sha256' => hash('sha256', $storage->get($file_name)),
                'uuid' => Str::uuid(),
                'user_id' => Auth::user()?->id,
                'history' => [now().' Added by '.Auth::user()?->name.' #'.Auth::user()?->id],
            ]);
        }

        return false;
    }

    /**
     * An Intervention ImageManager, built directly rather than through the
     * Intervention\Image\Laravel facade. Laravel 13 ships its own `Illuminate\Image`
     * feature bound to the same `image` container key, so that facade now resolves to
     * Laravel's manager (whose Intervention bridge calls a method the installed
     * intervention/image version does not have). Resolving the manager here avoids the
     * collision. Uses the configured Intervention driver when set, else GD.
     */
    public static function imageManager(): ImageManager
    {
        $driver = config('image.driver');

        return is_string($driver) && is_a($driver, DriverInterface::class, true)
            ? ImageManager::withDriver($driver)
            : ImageManager::gd();
    }

    /**
     * Intrinsic pixel size of a bitmap image, computed once and cached in meta.
     * Lets the frontend set <img width height> to reserve the aspect-ratio box
     * (no layout shift) without cropping. Self-healing: legacy media is filled on
     * first access. Returns null for non-images or files that can't be decoded.
     *
     * @return array{width: int, height: int}|null
     */
    public function dimensions(): ?array
    {
        if (isset($this->meta['width'], $this->meta['height'])) {
            return ['width' => $this->meta['width'], 'height' => $this->meta['height']];
        }

        if (! str_starts_with((string) $this->mime_type, 'image/') || $this->mime_type === 'image/svg+xml') {
            return null;
        }

        try {
            $storage = Storage::disk($this->disk ?: config('leap.filemanager.disk'));
            $image = static::imageManager()->read($storage->get($this->file_name));

            // A photo straight off a phone is stored the way the sensor read it
            // with an EXIF tag saying which way is up. Intervention keeps that
            // tag but does not act on it, so an unrotated portrait measures as a
            // landscape here -- while every browser shows it upright. Measure
            // what will be on screen.
            $image->orient();

            $dimensions = ['width' => $image->width(), 'height' => $image->height()];

            $this->meta = array_merge($this->meta ?? [], $dimensions);
            $this->save();

            return $dimensions;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Re-read what the file on disk actually is: size, mime type and content
     * hash, dropping the cached dimensions so the next dimensions() call
     * measures the file that is there now.
     *
     * Anything that writes over an existing file has to call this. The URL of
     * every resized copy is built from the hash, so a row left unsynced keeps
     * handing out the address of the picture that used to be there — and the
     * web server, serving that address straight off disk, never gets the chance
     * to notice.
     *
     * @param  string|null  $note  Added to history when something changed
     * @return bool Whether anything changed
     */
    public function syncFromDisk(?string $note = null): bool
    {
        $storage = Storage::disk($this->disk ?: config('leap.filemanager.disk'));

        if (! $storage->exists($this->file_name)) {
            return false;
        }

        $sha256 = hash('sha256', (string) $storage->get($this->file_name));

        if ($sha256 === $this->sha256) {
            return false;
        }

        $this->size = $storage->size($this->file_name);
        /**
         * @disregard P1013 Undefined method 'mimeType'
         */
        $this->mime_type = $storage->mimeType($this->file_name);
        $this->sha256 = $sha256;

        $meta = $this->meta ?? [];
        unset($meta['width'], $meta['height']);
        $this->meta = $meta;

        $history = $this->history ?? [];
        $history[] = now().' '.($note ?: 'Replaced on disk').(Auth::user() ? ' by '.Auth::user()->name.' #'.Auth::user()->id : '');
        $this->history = $history;

        $this->save();

        return true;
    }

    /**
     * The URL of this image at the given preset, or of the file itself when
     * there is nothing to resize (no preset given, an SVG, a video, the feature
     * turned off).
     */
    public function url(string|int|null $preset = null): ?string
    {
        return ImageUrl::for($this, $preset);
    }

    /**
     * A srcset value for this image, one entry per width.
     *
     * @param  array<int>|null  $widths  Defaults to leap.images.component_widths
     */
    public function srcset(?array $widths = null): string
    {
        return ImageUrl::srcset($this, $widths);
    }

    /**
     * $media->url and $media->srcset, without the parentheses.
     *
     * Not a convenience: Eloquent resolves a property it has no attribute for by
     * looking for a method of that name and insisting it return a relationship,
     * so without these two accessors reading $media->url would throw where it
     * used to be null. They also happen to be the right answers — the file
     * itself, and the default ladder.
     */
    public function getUrlAttribute(): ?string
    {
        return ImageUrl::original($this);
    }

    public function getSrcsetAttribute(): string
    {
        return ImageUrl::srcset($this);
    }

    public static function findFile(string $file_name, ?string $disk = null): ?Media
    {
        $disk = $disk ?: config('leap.filemanager.disk');
        $storage = Storage::disk($disk);

        return static::where('file_name', $file_name)->where('disk', $disk)->first();
    }

    public function getDownloadUrlAttribute(): string
    {
        // This should only be used by Leap editor so needs to move to Leap class somehow...
        return route(
            'leap.module.filemanager.download',
            [
                'name' => str_replace('%2F', '/', rawurlencode($this->file_name)),
            ]
        );
    }

    /**
     * The single source for what counts as which media type. The filemanager
     * checks file extensions, this model checks stored MIME types; adding a
     * format means adding it to both lists of its type here and nowhere else.
     */
    public const TYPES = [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
        ],
        'bitmap' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        ],
        'audio' => [
            'extensions' => ['flac', 'mp3', 'wav', 'aac'],
            'mimes' => ['audio/flac', 'audio/mp3', 'audio/wav', 'audio/aac'],
        ],
        'video' => [
            'extensions' => ['mp4', 'm4v', 'mov', 'avi', 'wmv'],
            'mimes' => ['video/mp4', 'video/m4v', 'video/mov', 'video/avi', 'video/wmv'],
        ],
    ];

    public function isImage(): bool
    {
        return Str::contains($this->mime_type, self::TYPES['image']['mimes']);
    }

    public function alt(?string $locale = null): string
    {
        return (string) (Leap::localize($this->meta['alt'] ?? null, $locale) ?? '');
    }

    /**
     * The crop focus point set in the file manager, as CSS-ready percentages
     * (0-100), or null when none is set. Pair with object-fit: cover and
     * object-position: {x}% {y}% to keep the focus point visible when the image
     * is cropped by its container's aspect ratio.
     */
    public function focusPosition(): ?array
    {
        $focus = $this->meta['image_focus'] ?? null;
        if (! is_array($focus) || ! isset($focus['x'], $focus['y'])) {
            return null;
        }

        return ['x' => (float) $focus['x'], 'y' => (float) $focus['y']];
    }

    public function isAudio(): bool
    {
        return Str::contains($this->mime_type, self::TYPES['audio']['mimes']);
    }

    public function isVideo(): bool
    {
        return Str::contains($this->mime_type, self::TYPES['video']['mimes']);
    }

    public function isBitmap(): bool
    {
        return Str::contains($this->mime_type, self::TYPES['bitmap']['mimes']);
    }

    public function mediables(): HasMany
    {
        return $this->hasMany(Mediable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Leap::userModel()::class);
    }
}
