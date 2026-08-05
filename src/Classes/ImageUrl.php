<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;

/**
 * Builds the URL of a resized copy.
 *
 * The file's sha256 is part of the path, which is what makes replacing an image
 * work: a different file has a different hash, so it has a different URL, so
 * nothing anywhere is holding on to the old picture — not the web server that
 * serves the copy without asking PHP, not the browser, not a CDN. The price is
 * that copies of files that no longer exist stay on disk until leap:images
 * --prune sweeps them.
 *
 * Every reason not to resize (feature off, no such preset, an SVG, a video)
 * ends the same way: the URL of the original. A caller never has to check
 * first, and a page never breaks over a missing derivative.
 */
class ImageUrl
{
    /**
     * The URL of $file at $preset, or of the original when there is nothing to
     * resize.
     */
    public static function for(Media|string|null $file, string|int|null $preset = null, ?string $disk = null): ?string
    {
        $path = self::path($file);

        if (! $path) {
            return null;
        }

        $resolved = config('leap.images.enabled') ? ImagePreset::find($preset) : null;

        if (! $resolved || ! self::isResizable($file)) {
            return self::original($file, $disk);
        }

        $hash = self::hash($file, $disk);

        if (! $hash) {
            return self::original($file, $disk);
        }

        return ImageResizer::disk()->url(ImageResizer::targetPath($path, $resolved, $hash));
    }

    /**
     * A srcset value: "/img/600/a1b2c3d4/photo.jpg.webp 600w, ...". Empty when
     * there is nothing to resize, so the caller can leave the attribute off.
     *
     * @param  array<int>|null  $widths  Defaults to leap.images.component_widths
     */
    public static function srcset(Media|string|null $file, ?array $widths = null): string
    {
        if (! self::isResizable($file) || ! config('leap.images.enabled')) {
            return '';
        }

        $entries = [];

        foreach ($widths ?? config('leap.images.component_widths', []) as $width) {
            if (! ImagePreset::find($width)) {
                continue;
            }

            $entries[] = self::for($file, $width).' '.((int) $width).'w';
        }

        return implode(', ', $entries);
    }

    /**
     * The URL of the file itself, untouched.
     */
    public static function original(Media|string|null $file, ?string $disk = null): ?string
    {
        $path = self::path($file);

        return $path ? self::disk($file, $disk)->url($path) : null;
    }

    /**
     * The hash that goes in the URL: leap.images.hash_length characters of the
     * file's sha256.
     *
     * A Media row already carries one. A bare path — a video poster, say, which
     * has no row — is hashed once per version of the file: the cache key holds
     * its size and modification time, so a rewritten file misses the entry it
     * would otherwise keep answering from.
     */
    public static function hash(Media|string|null $file, ?string $disk = null): ?string
    {
        $length = (int) config('leap.images.hash_length', 8);

        if ($file instanceof Media) {
            return $file->sha256 ? substr($file->sha256, 0, $length) : null;
        }

        $path = self::path($file);

        if (! $path) {
            return null;
        }

        $storage = self::disk($file, $disk);

        try {
            if (! $storage->exists($path)) {
                return null;
            }

            $key = 'leap.image.hash:'.$storage->lastModified($path).':'.$storage->size($path).':'.$path;

            $sha256 = Cache::rememberForever($key, fn () => hash('sha256', (string) $storage->get($path)));
        } catch (\Throwable) {
            return null;
        }

        return substr($sha256, 0, $length);
    }

    /**
     * Whether this is a bitmap leap can resize. SVG is not — it is vector, it
     * scales by itself, and rasterising it would be a downgrade.
     */
    public static function isResizable(Media|string|null $file): bool
    {
        if ($file instanceof Media) {
            return $file->isBitmap();
        }

        $path = self::path($file);

        return $path && in_array(
            strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            Media::TYPES['bitmap']['extensions'],
            true
        );
    }

    private static function path(Media|string|null $file): ?string
    {
        $path = $file instanceof Media ? $file->file_name : $file;

        return $path ? ltrim($path, '/') : null;
    }

    private static function disk(Media|string|null $file, ?string $disk = null): FilesystemAdapter
    {
        if ($disk) {
            return Storage::disk($disk);
        }

        if ($file instanceof Media && $file->disk) {
            return Storage::disk($file->disk);
        }

        return ImageResizer::sourceDisk();
    }
}
