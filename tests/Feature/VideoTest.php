<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Classes\Video;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A video takes a YouTube or Vimeo id and fetches its own poster.
 *
 * The poster is stored locally rather than hotlinked: hotlinking would put a request to
 * the provider on every page view, which is exactly what the click-to-load player exists
 * to avoid — a visitor should only reach a third party once they ask to watch.
 */
class VideoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_numeric_id_is_vimeo_and_anything_else_is_youtube(): void
    {
        $this->assertTrue((new Video('1084537'))->isVimeo());
        $this->assertFalse((new Video('dQw4w9WgXcQ'))->isVimeo());

        $this->assertSame('Vimeo', (new Video('1084537'))->provider());
        $this->assertSame('YouTube', (new Video('dQw4w9WgXcQ'))->provider());

        $this->assertStringContainsString('player.vimeo.com/video/1084537', (new Video('1084537'))->embedUrl());
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', (new Video('dQw4w9WgXcQ'))->embedUrl());
    }

    /**
     * Editors paste what the address bar or the share button gives them. Reading the id
     * out of that turns a video that silently would not play into one that does.
     */
    public function test_the_id_is_read_out_of_a_link(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s&list=PL123',
            'youtube.com/watch?v=dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?si=abc123',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/live/dQw4w9WgXcQ?feature=share',
            ' dQw4w9WgXcQ ',
        ] as $input) {
            $video = new Video($input);

            $this->assertSame('dQw4w9WgXcQ', $video->id, $input);
            $this->assertFalse($video->isVimeo(), $input);
        }

        foreach ([
            'https://vimeo.com/76979871',
            'vimeo.com/76979871',
            'https://player.vimeo.com/video/76979871?autoplay=1',
            'https://vimeo.com/channels/staffpicks/76979871',
            'https://vimeo.com/groups/shortfilms/videos/76979871',
            '76979871',
        ] as $input) {
            $video = new Video($input);

            $this->assertSame('76979871', $video->id, $input);
            $this->assertNull($video->hash, $input);
            $this->assertTrue($video->isVimeo(), $input);
        }
    }

    /**
     * An unlisted Vimeo video only plays with the hash from its link, so that goes along
     * to the player, and to oEmbed for the poster.
     */
    public function test_an_unlisted_vimeo_video_keeps_its_hash(): void
    {
        foreach (['https://vimeo.com/76979871/abc456def', 'https://player.vimeo.com/video/76979871?h=abc456def', '76979871/abc456def'] as $input) {
            $video = new Video($input);

            $this->assertSame(['76979871', 'abc456def'], [$video->id, $video->hash], $input);
            $this->assertSame('https://player.vimeo.com/video/76979871?h=abc456def&autoplay=1&playsinline=1&dnt=1', $video->embedUrl(), $input);
        }

        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['thumbnail_url' => 'https://i.vimeocdn.com/video/1.jpg']),
            'i.vimeocdn.com/*' => Http::response('jpeg'),
        ]);

        (new Video('https://vimeo.com/76979871/abc456def'))->poster();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'oembed.json')
            && $request['url'] === 'https://vimeo.com/76979871/abc456def');
    }

    public function test_it_uses_the_privacy_friendly_youtube_domain(): void
    {
        // The player only loads once a visitor clicks, and youtube-nocookie sets no
        // tracking cookies even then. Plain youtube.com buys nothing: Safari refuses to
        // autoplay either of them with sound, so it would cost cookies for nothing.
        $this->assertStringContainsString('youtube-nocookie.com', (new Video('abc'))->embedUrl());
        $this->assertStringNotContainsString('//www.youtube.com', (new Video('abc'))->embedUrl());
    }

    public function test_it_asks_vimeo_not_to_track_the_playback(): void
    {
        // dnt=1 keeps Vimeo's player from setting cookies and collecting session
        // analytics. YouTube has no such parameter and its address stays as it was.
        $this->assertSame(
            'https://player.vimeo.com/video/1084537?autoplay=1&playsinline=1&dnt=1',
            (new Video('1084537'))->embedUrl()
        );
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&playsinline=1',
            (new Video('dQw4w9WgXcQ'))->embedUrl()
        );
    }

    public function test_it_stores_the_youtube_poster_locally(): void
    {
        Http::fake(['img.youtube.com/vi/*/maxresdefault.jpg' => Http::response('jpeg-bytes')]);

        $poster = (new Video('dQw4w9WgXcQ'))->poster();

        $this->assertSame('video-posters/youtube-dQw4w9WgXcQ.jpg', $poster);
        Storage::disk('public')->assertExists($poster);
    }

    public function test_it_falls_back_to_the_smaller_youtube_poster(): void
    {
        // maxresdefault only exists for videos uploaded in HD
        Http::fake([
            'img.youtube.com/vi/*/maxresdefault.jpg' => Http::response('', 404),
            'img.youtube.com/vi/*/hqdefault.jpg' => Http::response('jpeg-bytes'),
        ]);

        $this->assertSame('video-posters/youtube-abc.jpg', (new Video('abc'))->poster());
    }

    public function test_it_asks_vimeo_where_the_poster_is(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['thumbnail_url' => 'https://i.vimeocdn.com/video/1_1920.jpg']),
            'i.vimeocdn.com/*' => Http::response('jpeg-bytes'),
        ]);

        $this->assertSame('video-posters/vimeo-1084537.jpg', (new Video('1084537'))->poster());
        Storage::disk('public')->assertExists('video-posters/vimeo-1084537.jpg');
    }

    public function test_a_provider_that_is_down_does_not_take_the_page_with_it(): void
    {
        Http::fake(fn (Request $request) => Http::response('', 500));

        $this->assertNull((new Video('dQw4w9WgXcQ'))->poster());
    }

    public function test_a_stored_poster_is_not_fetched_again(): void
    {
        Storage::disk('public')->put('video-posters/youtube-dQw4w9WgXcQ.jpg', 'jpeg-bytes');
        Http::fake();

        $this->assertSame('video-posters/youtube-dQw4w9WgXcQ.jpg', (new Video('dQw4w9WgXcQ'))->poster());

        Http::assertNothingSent();
    }
}
