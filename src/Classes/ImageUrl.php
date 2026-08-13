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
    public static function srcset(Media|string|null $file, ?array $widths = null, ?string $format = null): string
    {
        if (! self::isResizable($file) || ! config('leap.images.enabled')) {
            return '';
        }

        $suffix = $format ? '.'.strtolower($format) : '';
        $entries = [];

        foreach (self::ladder($widths) as $preset => $width) {
            if (! ImagePreset::find($preset.$suffix)) {
                continue;
            }

            $entries[] = self::for($file, $preset.$suffix).' '.$width.'w';
        }

        return implode(', ', $entries);
    }

    /**
     * A ladder normalised to preset name => the width it is served at.
     *
     * A list of numbers is the common case, where the two are the same thing:
     * [600, 1200] is the 600 preset at 600w. A map names them separately, for a
     * ladder of named presets. ['hero-900' => 900] is the hero-900 preset,
     * which carries its own quality or crop, described to the browser as 900w.
     *
     * Without that second form anything wanting a preset the widths cannot
     * express has to build its own srcset, and then it also has to build its own
     * <picture>, which is exactly where a hero photograph loses the format it
     * would most benefit from.
     *
     * @param  array<int|string, int|string>|null  $widths
     * @return array<string, int>
     */
    public static function ladder(?array $widths = null): array
    {
        $ladder = [];

        foreach ($widths ?? config('leap.images.component_widths', []) as $key => $value) {
            $ladder[is_string($key) ? $key : (string) $value] = (int) $value;
        }

        return $ladder;
    }

    /**
     * One entry per extra format the active driver can encode: the type for the
     * <source> and the srcset to go with it, best first. Empty when the project
     * configured no extra formats, so the component keeps emitting a bare <img>.
     *
     * @param  array<int>|null  $widths
     * @return array<int, array{type: string, srcset: string}>
     */
    public static function sources(Media|string|null $file, ?array $widths = null): array
    {
        $sources = [];

        // From the first rung that offers any, since 'format' is a per-preset option: a
        // named preset may offer a different set from the widths around it.
        //
        // The first that *resolves*, not simply the first. A caller may pass a width this
        // project does not have, a 300 where the ladder starts at 600, and srcset() already
        // skips those without complaint. Reading the formats from that same rung meant
        // find() returned null, "no formats at all" followed, and the component fell back
        // to a bare <img>: no <picture>, no AVIF, and nothing anywhere saying so. The two
        // halves have to agree on being tolerant.
        //
        // A preset is a config lookup and a ladder is a handful of rungs, so resolving all
        // of them costs nothing worth saving.
        $offered = collect(array_keys(self::ladder($widths)))
            ->map(fn (string|int $rung): array => ImagePreset::find($rung)?->formats() ?? [])
            ->first(fn (array $formats): bool => $formats !== []) ?? [];

        foreach (array_keys($offered) as $format) {
            if (! ImageResizer::supports($format)) {
                continue;
            }

            $srcset = self::srcset($file, $widths, $format);

            if ($srcset === '') {
                continue;
            }

            $sources[] = ['type' => 'image/'.$format, 'srcset' => $srcset];
        }

        return $sources;
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
