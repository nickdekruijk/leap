<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $this->setTable(config('leap.table_prefix') . 'media');
    }

    /**
     * Get a Media model instance for a file, or create one if file exists
     *
     * @param string $file The file name including path
     * @param string|null $disk The storage disk to use, defaults from leap config
     * @return Media|false Returns Media model instance or false if file does not exist
     */
    public static function forFile(string $file_name, ?string $disk = null): Media|false
    {
        $disk = $disk ?: config('leap.filemanager.disk');
        $storage = Storage::disk($disk);

        $file_name = ltrim($file_name, '/');

        if ($storage->exists($file_name)) {
            return static::firstOrCreate([
                'file_name' => $file_name,
                'disk' => $disk,
            ], [
                'size' => $storage->size($file_name),
                'mime_type' => $storage->mimeType($file_name),
                'sha256' => hash('sha256', $storage->get($file_name)),
                'uuid' => Str::uuid(),
                'user_id' => Auth::user()?->id,
                'history' => [now() . ' Added by ' . Auth::user()?->name . ' #' . Auth::user()?->id],
            ]);
        }

        return false;
    }

    public static function findFile(string $file_name, ?string $disk = null): ?Media
    {
        $disk = $disk ?: config('leap.filemanager.disk');
        $storage = Storage::disk($disk);

        return static::where('file_name', $file_name)->where('disk', $disk)->first();
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('leap.module.filemanager.download', str_replace('%2F', '/', rawurlencode($this->file_name)));
    }

    public function isImage(): bool
    {
        return Str::contains($this->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);
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
