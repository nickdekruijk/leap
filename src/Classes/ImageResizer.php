<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use NickDeKruijk\Leap\Models\Media;
use Throwable;

/**
 * Makes the resized copies: decodes an original, applies a preset, encodes the
 * result and writes it to the images disk.
 *
 * Everything here can decline. An original that would need more memory than is
 * sane to spend, or an animated GIF that GD would flatten to a single frame,
 * comes back as null — the caller then serves the original untouched, which is
 * always correct, only bigger.
 */
class ImageResizer
{
    /**
     * Extensions whose encoder takes a quality. Passing quality: to PngEncoder
     * or GifEncoder is a fatal unknown named parameter, not a no-op.
     */
    private const LOSSY_EXTENSIONS = ['jpg', 'jpeg', 'webp', 'avif', 'heic', 'jp2'];

    /**
     * Where a resized copy lives on the images disk: the source tree mirrored
     * once per preset, with the hash on the file rather than around it.
     *
     *     1200/photos/office-a1b2c3d4.jpg.webp
     *
     * A directory per hash would be a directory per file per preset — three for
     * every copy once the source tree is rebuilt inside it, and an empty one
     * left behind whenever a copy goes.
     */
    public static function targetPath(string $sourcePath, ImagePreset $preset, string $hash): string
    {
        $sourcePath = ltrim($sourcePath, '/');
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $directory = trim(pathinfo($sourcePath, PATHINFO_DIRNAME), '.');

        return $preset->name.'/'
            .($directory ? trim($directory, '/').'/' : '')
            .pathinfo($sourcePath, PATHINFO_FILENAME).'-'.$hash
            .($extension ? '.'.$extension : '')
            .$preset->pathSuffix($extension);
    }

    /**
     * Read a copy's path back: which original it was made from, and at which
     * version of it. The inverse of targetPath(), for the request that has to
     * find the original and for the prune that has to judge whether anything
     * still points at the copy.
     *
     * @param  string  $path  Below the preset directory
     * @return array{path: string, hash: string}|null Null when it is not a path this package would have written
     */
    public static function parseTargetPath(string $path, ImagePreset $preset): ?array
    {
        $path = ltrim($path, '/');
        $format = $preset->format();

        // The output format is appended rather than substituted, so it comes
        // off first and the original's own extension is underneath. Unless
        // there is nothing underneath: a webp encoded as webp gets nothing
        // appended, and taking that .webp off would leave a file with no
        // extension at all.
        if ($format && str_ends_with(strtolower($path), '.'.$format)) {
            $stripped = substr($path, 0, -(strlen($format) + 1));

            if (pathinfo($stripped, PATHINFO_EXTENSION) !== '') {
                $path = $stripped;
            }
        }

        $directory = trim(pathinfo($path, PATHINFO_DIRNAME), '.');
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Greedy on purpose: an original called office-deadbeef.jpg keeps its
        // name, and only the hash this package added comes off.
        if (! preg_match('/^(.+)-([a-f0-9]{4,64})$/', pathinfo($path, PATHINFO_FILENAME), $matches)) {
            return null;
        }

        return [
            'path' => ($directory ? trim($directory, '/').'/' : '').$matches[1].($extension ? '.'.$extension : ''),
            'hash' => $matches[2],
        ];
    }

    /**
     * Apply a preset to the given image, or null when the original should be
     * passed through as it is.
     */
    public static function encode(string $contents, ImagePreset $preset, string $sourceExtension): ?EncodedImageInterface
    {
        if (! self::isResizable($contents, $sourceExtension)) {
            return null;
        }

        try {
            $image = Media::imageManager()->read($contents);

            // Before anything measures it: Intervention reads the EXIF
            // orientation but does not act on it, so an unrotated portrait photo
            // would be resized against its landscape dimensions and come out
            // sideways next to the width/height the frontend prints.
            $image->orient();

            $width = $preset->width();
            $height = $preset->height();

            if ($preset->fit() === 'cover' && $width && $height) {
                $image = $preset->upscale()
                    ? $image->cover($width, $height)
                    : $image->coverDown($width, $height);
            } elseif ($width || $height) {
                $image = $preset->upscale()
                    ? $image->scale($width, $height)
                    : $image->scaleDown($width, $height);
            }

            if ($preset->grayscale()) {
                $image = $image->greyscale();
            }

            if ($preset->blur()) {
                $image = $image->blur($preset->blur());
            }

            $extension = $preset->extension($sourceExtension);

            self::applyEffort($image, $preset, $extension);

            // Both drivers read quality 100 on webp as "encode losslessly",
            // which is what a screenshot full of text needs and a photograph
            // does not.
            $quality = $preset->isLossless($sourceExtension) ? 100 : $preset->quality();

            return in_array($extension, self::LOSSY_EXTENSIONS, true)
                ? $image->encodeByExtension($extension, quality: $quality)
                : $image->encodeByExtension($extension);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ask the webp encoder to try harder, when there is an encoder that can.
     *
     * Set on the underlying Imagick object because Intervention's encoder takes
     * a quality and nothing else. The GD driver has no equivalent to offer, and
     * its native handle is a GdImage that would not know what to do with this.
     */
    private static function applyEffort(ImageInterface $image, ImagePreset $preset, string $extension): void
    {
        $native = $image->core()->native();

        if ($preset->effort() === null || $extension !== 'webp' || ! $native instanceof Imagick) {
            return;
        }

        $native->setOption('webp:method', (string) $preset->effort());
    }

    /**
     * Whether this image should be resized at all.
     */
    public static function isResizable(string $contents, string $sourceExtension): bool
    {
        if (! in_array(strtolower($sourceExtension), Media::TYPES['bitmap']['extensions'], true)) {
            return false;
        }

        if (self::isAnimatedGif($contents, $sourceExtension)) {
            return false;
        }

        return ! self::exceedsPixelLimit($contents);
    }

    /**
     * An animated GIF, which GD flattens to its first frame: a resized copy
     * would silently stop moving, so it is left alone.
     */
    public static function isAnimatedGif(string $contents, string $sourceExtension): bool
    {
        if (strtolower($sourceExtension) !== 'gif') {
            return false;
        }

        // More than one Graphic Control Extension block means more than one
        // frame. Counting them beats decoding the file to find out.
        return substr_count($contents, "\x00\x21\xF9\x04") > 1;
    }

    /**
     * Whether decoding this would cost more memory than leap.images
     * .max_source_pixels allows. GD holds roughly width * height * 4 bytes, so
     * a single 48 megapixel photo is enough to end a request with a fatal
     * memory error — and it does so inside the worker, not in this file.
     *
     * Read from the header rather than by decoding, which would be the very
     * thing this prevents.
     */
    public static function exceedsPixelLimit(string $contents): bool
    {
        $limit = config('leap.images.max_source_pixels');

        if (! $limit) {
            return false;
        }

        $size = @getimagesizefromstring($contents);

        return $size && ($size[0] * $size[1]) > (int) $limit;
    }

    /**
     * Make every copy of $media that is not on disk yet.
     *
     * Cheap to call again: existing copies are skipped and the original is only
     * read once something actually has to be made from it.
     *
     * @param  array<ImagePreset>|null  $presets  Defaults to every configured preset
     * @return array{generated: array<string>, skipped: array<string>}
     */
    public static function warm(Media $media, ?array $presets = null, bool $dryRun = false): array
    {
        $result = ['generated' => [], 'skipped' => []];
        $storage = self::sourceDisk();
        $hash = ImageUrl::hash($media);

        if (! $hash || ! $media->isBitmap() || ! $storage->exists($media->file_name)) {
            return $result;
        }

        $disk = self::disk();
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
        $contents = null;

        foreach ($presets ?? ImagePreset::all() as $preset) {
            $target = self::targetPath($media->file_name, $preset, $hash);

            if ($disk->exists($target)) {
                $result['skipped'][] = $target;

                continue;
            }

            if ($dryRun) {
                $result['generated'][] = $target;

                continue;
            }

            $contents ??= (string) $storage->get($media->file_name);
            $encoded = self::encode($contents, $preset, $extension);

            if (! $encoded) {
                continue;
            }

            $disk->put($target, (string) $encoded);
            $result['generated'][] = $target;
        }

        return $result;
    }

    /**
     * Delete every copy of one file, at one version of its contents.
     *
     * Exact rather than a sweep: a path is unique per disk and the hash pins the
     * version, so these copies belong to that file alone and to nothing else.
     * Called the moment an image is deleted, renamed or replaced, which leaves
     * leap:images --prune for what happens out of leap's sight.
     *
     * @return int How many were deleted
     */
    public static function forget(string $path, ?string $hash): int
    {
        if (! $hash || ! config('leap.images.enabled')) {
            return 0;
        }

        $hash = substr($hash, 0, (int) config('leap.images.hash_length', 8));
        $disk = self::disk();
        $deleted = 0;

        foreach (ImagePreset::all() as $preset) {
            $target = self::targetPath($path, $preset, $hash);

            if ($disk->exists($target) && $disk->delete($target)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * The disk resized copies are written to.
     */
    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(config('leap.images.disk'));
    }

    /**
     * The disk originals are read from.
     */
    public static function sourceDisk(): FilesystemAdapter
    {
        return Storage::disk(config('leap.images.source_disk') ?: config('leap.filemanager.disk'));
    }
}
