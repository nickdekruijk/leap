<?php

namespace NickDeKruijk\Leap\Controllers;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use NickDeKruijk\Leap\Classes\ImagePreset;
use NickDeKruijk\Leap\Classes\ImageResizer;
use NickDeKruijk\Leap\Classes\ImageUrl;
use NickDeKruijk\Leap\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves a resized image, and only ever runs once per URL: the copy it writes
 * lands where the web server was already looking, so every later request for
 * that URL is answered from disk without PHP.
 *
 * Which is also why the response carries the bytes rather than a redirect to
 * the file just written — a redirect would spend a second round trip on
 * something the web server takes over from here anyway.
 */
class ImageController extends Controller
{
    public function show(string $preset, string $path): Response|RedirectResponse
    {
        $resolved = ImagePreset::find($preset);

        if (! $resolved) {
            throw new NotFoundHttpException('No such image preset.');
        }

        // Nothing below the source disk's root is off limits, so traversal is
        // the whole attack surface here.
        if (str_contains($path, '..') || str_contains($path, '\\')) {
            return $this->missing();
        }

        $parsed = ImageResizer::parseTargetPath($path, $resolved);

        // An SVG is not resized and a .php is not an image; neither has any
        // business being read and echoed back by this route.
        if (! $parsed || ! $this->isBitmapPath($parsed['path'])) {
            return $this->missing();
        }

        $source = $parsed['path'];
        $hash = $parsed['hash'];

        // Rebuilt rather than taken from the request: what gets written is a
        // path this package composed, never one a visitor typed.
        $target = ImageResizer::targetPath($source, $resolved, $hash);
        $disk = ImageResizer::disk();

        // Answered before the original is so much as looked at: the copy at
        // this path was written by this code, for this hash, and is what the
        // web server would have served had it been able to reach the disk.
        if ($disk->exists($target)) {
            return $this->respond((string) $disk->get($target), (string) $disk->mimeType($target));
        }

        $storage = ImageResizer::sourceDisk();

        if (! $storage->exists($source)) {
            return $this->missing();
        }

        // One decode per URL, however many visitors arrive at once. Without it a
        // gallery of cold images has every request that comes in while the first
        // is still working decode the same original all over again.
        try {
            $lock = Cache::lock('leap.image:'.sha1($target), (int) config('leap.images.lock_seconds', 10));
            $lock->block(5);
        } catch (LockTimeoutException) {
            $lock = null;
        }

        try {
            if ($disk->exists($target)) {
                return $this->respond((string) $disk->get($target), (string) $disk->mimeType($target));
            }

            $contents = (string) $storage->get($source);

            // The file is in hand anyway, so the hash it claims to have is
            // checked against the hash it actually has. A mismatch is either a
            // page rendered before the file was replaced or a Media row left
            // behind by something that wrote to the disk outside leap; both are
            // answered by correcting what needs correcting and sending the
            // visitor to the URL this file has now. No copy is ever written
            // under a hash that is not the file's own.
            $media = Media::findFile($source, config('leap.images.source_disk') ?: config('leap.filemanager.disk'));
            $sha256 = hash('sha256', $contents);

            if ($media && $media->sha256 !== $sha256) {
                $media->syncFromDisk();
            }

            // Compared at exactly the configured length, so a shorter prefix
            // cannot open a second address for the same picture.
            if (substr($sha256, 0, (int) config('leap.images.hash_length', 8)) !== $hash) {
                return redirect(ImageUrl::for($media ?: $source, $preset), 302);
            }

            $encoded = ImageResizer::encode($contents, $resolved, pathinfo($source, PATHINFO_EXTENSION));

            // Nothing to resize, or nothing that should be: an animated GIF, an
            // original too large to decode safely, a file GD cannot read. The
            // visitor gets the real thing rather than a broken image.
            if (! $encoded) {
                return $this->respond($contents, (string) $storage->mimeType($source));
            }

            $disk->put($target, (string) $encoded);

            return $this->respond((string) $encoded, $encoded->mediaType());
        } finally {
            $lock?->release();
        }
    }

    private function isBitmapPath(string $path): bool
    {
        return in_array(
            strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            Media::TYPES['bitmap']['extensions'],
            true
        );
    }

    /**
     * A missing original is answered without caching: unlike a resized copy,
     * whose URL changes with its contents, this same URL becomes valid the
     * moment the file is uploaded.
     */
    private function missing(): Response
    {
        return new Response('', 404, ['Cache-Control' => 'no-store']);
    }

    private function respond(string $contents, string $mimeType): Response
    {
        return new Response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($contents),
            'Cache-Control' => 'public, max-age='.(int) config('leap.images.max_age', 31536000).', immutable',
        ]);
    }
}
