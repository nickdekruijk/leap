<?php

namespace NickDeKruijk\Leap\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use NickDeKruijk\Leap\Classes\ImageResizer;
use NickDeKruijk\Leap\Models\Media;

/**
 * Makes every resized copy of one image up front, for sites that run with
 * leap.images.eager on.
 *
 * That is the only way to serve copies from a disk the web server cannot reach
 * — an s3 bucket has no failed request for PHP to catch and turn into a
 * generated file. On a local disk it is a preference: it moves the cost of the
 * first view off the first visitor, at the price of making sizes that may never
 * be asked for.
 */
class GenerateImageDerivatives implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public int $mediaId, public string $sha256)
    {
        $this->onQueue(config('leap.images.queue'));
    }

    /**
     * One job per version of a file: a second save that does not change the
     * contents has nothing to add, while a real replacement gets its own run
     * because the hash — and so every path it writes — is different.
     */
    public function uniqueId(): string
    {
        return $this->mediaId.':'.$this->sha256;
    }

    public function handle(): void
    {
        $media = Media::find($this->mediaId);

        // Gone, or replaced again while this sat in the queue. Either way the
        // copies this job would write are already the wrong ones.
        if (! $media || $media->sha256 !== $this->sha256) {
            return;
        }

        $media->dimensions();

        ImageResizer::warm($media);
    }
}
