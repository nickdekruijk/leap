<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A video in a "video" section: YouTube or Vimeo, told apart by its id — Vimeo's are
 * numeric, YouTube's are not.
 *
 * The poster is fetched from the provider once and stored locally rather than
 * hotlinked. Hotlinking would put a request to YouTube or Vimeo on every page view,
 * which is the very thing the click-to-load player avoids: a visitor should only
 * reach a third party once they ask to watch. It lands on the public disk, so
 * asset_resized() can serve it at the width the layout actually needs.
 */
class Video
{
    private const POSTER_DIRECTORY = 'video-posters';

    public function __construct(public readonly string $id) {}

    /**
     * Vimeo ids are numeric, YouTube's are not.
     */
    public function isVimeo(): bool
    {
        return ctype_digit($this->id);
    }

    /**
     * The provider, by name — for the line that tells a visitor whose hands their data
     * is about to land in. Naming it is the whole point: "third parties" consents to
     * nothing in particular.
     */
    public function provider(): string
    {
        return $this->isVimeo() ? 'Vimeo' : 'YouTube';
    }

    /**
     * The player, only ever loaded once a visitor clicks play.
     *
     * autoplay=1 starts the video on that click everywhere except Safari, which
     * refuses to autoplay a cross-origin YouTube frame with sound no matter what:
     * youtube.com instead of youtube-nocookie.com, playsinline, and the IFrame API's
     * playVideo() were all tried and all blocked — only muting works, and a muted
     * start on a video of someone talking is worse than a second click. So in Safari
     * the visitor gets YouTube's own play button. Vimeo does start, on its own terms.
     *
     * playsinline is a separate matter: without it, iPhones take the video fullscreen
     * the moment it plays.
     */
    public function embedUrl(): string
    {
        return $this->isVimeo()
            ? 'https://player.vimeo.com/video/'.$this->id.'?autoplay=1&playsinline=1'
            : 'https://www.youtube-nocookie.com/embed/'.$this->id.'?autoplay=1&rel=0&playsinline=1';
    }

    /**
     * The locally stored poster, as a path on the public disk — or null when the
     * provider has none.
     */
    public function poster(): ?string
    {
        $file = self::POSTER_DIRECTORY.'/'.($this->isVimeo() ? 'vimeo' : 'youtube').'-'.$this->id.'.jpg';

        if (Storage::disk('public')->exists($file)) {
            return $file;
        }

        // A failure is remembered too: without that, a video whose poster cannot be
        // had would send the site knocking on the provider's door on every page view.
        return Cache::remember('video-poster:'.$file, now()->addDay(), function () use ($file): ?string {
            $source = $this->posterSource();

            if (! $source) {
                return null;
            }

            $response = $this->get($source);

            if (! $response?->successful()) {
                return null;
            }

            Storage::disk('public')->put($file, $response->body());

            return $file;
        });
    }

    /**
     * Where the provider keeps the poster.
     */
    private function posterSource(): ?string
    {
        if ($this->isVimeo()) {
            $response = $this->get('https://vimeo.com/api/oembed.json', [
                'url' => 'https://vimeo.com/'.$this->id,
                'width' => 1920,
            ]);

            return $response?->successful() ? $response->json('thumbnail_url') : null;
        }

        // maxresdefault only exists for videos uploaded in HD. hqdefault always does,
        // but at 480x360 it is a fallback rather than a choice.
        foreach (['maxresdefault', 'hqdefault'] as $quality) {
            $url = 'https://img.youtube.com/vi/'.$this->id.'/'.$quality.'.jpg';

            if ($this->get($url)?->successful()) {
                return $url;
            }
        }

        return null;
    }

    /**
     * A provider being slow or unreachable must never take the page down with it.
     *
     * @param  array<string, mixed>  $query
     */
    private function get(string $url, array $query = []): ?Response
    {
        try {
            return Http::timeout(5)->get($url, $query);
        } catch (Throwable) {
            return null;
        }
    }
}
