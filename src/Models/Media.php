<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
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
            $image = Image::read($storage->get($this->file_name));
            $dimensions = ['width' => $image->width(), 'height' => $image->height()];

            $this->meta = array_merge($this->meta ?? [], $dimensions);
            $this->save();

            return $dimensions;
        } catch (\Throwable $e) {
            return null;
        }
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

    public function isImage(): bool
    {
        return Str::contains($this->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);
    }

    public function alt(?string $locale = null): string
    {
        $value = $this->meta['alt'] ?? null;
        if (is_array($value)) {
            $locale = $locale ?? app()->getLocale();

            return $value[$locale] ?? reset($value) ?: '';
        }

        return (string) ($value ?? '');
    }

    public function isAudio(): bool
    {
        return Str::contains($this->mime_type, ['audio/flac', 'audio/mp3', 'audio/wav', 'audio/aac']);
    }

    public function isVideo(): bool
    {
        return Str::contains($this->mime_type, ['video/mp4', 'video/m4v', 'video/mov', 'video/avi', 'video/wmv']);
    }

    public function isBitmap(): bool
    {
        return Str::contains($this->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
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
