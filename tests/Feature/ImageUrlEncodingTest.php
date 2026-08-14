<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Classes\ImageUrl;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

/**
 * What leap writes into an attribute has to survive being read as a URL.
 *
 * A storage path went into the markup exactly as it was stored, so a file with
 * a space and a comma in its name produced a srcset that a browser splits into
 * candidates that do not exist. It drops the whole <source>, falls back to the
 * <img>, and the avif and webp ladder is off for that picture with nothing
 * anywhere saying so.
 */
class ImageUrlEncodingTest extends ImageTestCase
{
    /**
     * The whole ugly set: everything RFC 3986 keeps out of a path, the two that
     * cut a URL short where they stand ('#' and '?'), the comma srcset separates
     * on, an accent, and a folder that has its own share of them.
     *
     * @return array<int, string>
     */
    private function names(): array
    {
        return [
            'foto met spatie.jpg',
            'a,b.jpg',
            '100%.jpg',
            'wat?.jpg',
            '#1.jpg',
            'Venetië.jpg',
            'a"b.jpg',
            'map met spatie/en, komma.jpg',
        ];
    }

    private function media(string $path): Media
    {
        $this->fakeDisks();
        Storage::disk('public')->put($path, $this->jpegBytes(2000, 1000));

        return Media::forFile($path);
    }

    private function withFormats(): void
    {
        config([
            'leap.images.widths' => [600, 1200],
            'leap.images.defaults.format' => ['webp' => []],
        ]);
    }

    /**
     * Read back with parse_url, the way anything downstream reads it: a path
     * that still holds the whole file, and nothing that leaked into a query or
     * a fragment. '#' and '?' are what this catches, since either one hands the
     * rest of the name to the wrong part of the URL.
     */
    public function test_a_url_survives_being_parsed(): void
    {
        foreach ($this->names() as $name) {
            $media = $this->media($name);

            foreach ([$media->url(), $media->url(1200)] as $url) {
                $parsed = parse_url($url);

                $this->assertIsArray($parsed);
                $this->assertArrayNotHasKey('query', $parsed, $url);
                $this->assertArrayNotHasKey('fragment', $parsed, $url);
                $this->assertStringContainsString(
                    pathinfo($name, PATHINFO_FILENAME),
                    rawurldecode($parsed['path']),
                    'The path should still hold the whole filename: '.$url,
                );
            }

            // And the original is addressable as itself, character for character.
            $this->assertStringEndsWith('/'.$name, rawurldecode((string) parse_url($media->url(), PHP_URL_PATH)));
        }
    }

    /**
     * The assertion the comma was getting past: a browser splits a srcset on
     * commas first and on whitespace second, so a name carrying either one
     * turns two real candidates into three that are not there.
     */
    public function test_a_srcset_has_exactly_one_candidate_per_rung(): void
    {
        foreach ($this->names() as $name) {
            $srcset = $this->media($name)->srcset([600, 1200]);
            $candidates = explode(',', $srcset);

            $this->assertCount(2, $candidates, $srcset);

            foreach ($candidates as $candidate) {
                $this->assertCount(
                    2,
                    preg_split('/\s+/', trim($candidate)),
                    'A candidate is a URL and a descriptor, nothing else: '.$candidate,
                );
            }
        }
    }

    /**
     * And the same for every <source> in a <picture>, which is where the format
     * ladder lives and so where the silence costs the most.
     */
    public function test_every_source_srcset_holds_up_too(): void
    {
        foreach ($this->names() as $name) {
            $media = $this->media($name);
            $this->withFormats();

            $sources = ImageUrl::sources($media, [600, 1200]);

            $this->assertNotEmpty($sources);

            foreach ($sources as $source) {
                $this->assertCount(2, explode(',', $source['srcset']), $source['srcset']);
            }
        }
    }

    public function test_encoding_a_path_twice_changes_nothing_the_second_time(): void
    {
        foreach ($this->names() as $name) {
            $once = ImageUrl::encodePath($name);

            $this->assertSame($once, ImageUrl::encodePath($once), 'A %20 must not become %2520.');
        }
    }

    public function test_the_separator_and_the_ordinary_characters_are_left_alone(): void
    {
        $this->assertSame('photos/pic-1_2.jpg', ImageUrl::encodePath('photos/pic-1_2.jpg'));
        $this->assertSame('map%20met%20spatie/en%2C%20komma.jpg', ImageUrl::encodePath('map met spatie/en, komma.jpg'));
    }

    /**
     * An accent stays as it is, so the bytes asked for are the bytes on the
     * disk. Encoding them would mean normalising to NFC first, and a file macOS
     * stored decomposed would answer 404 to the composed form.
     */
    public function test_an_accent_is_left_as_it_is(): void
    {
        $this->assertSame('Venetië.jpg', ImageUrl::encodePath('Venetië.jpg'));
        $this->assertStringContainsString('Venetië.jpg', $this->media('Venetië.jpg')->url());
    }

    /**
     * Only the path is rewritten, and only where the driver put it, so what the
     * disk says about where it lives comes through untouched.
     */
    public function test_the_scheme_and_the_host_come_through_untouched(): void
    {
        $this->fakeDisks();
        Storage::fake('leap-images', ['url' => 'https://cdn.example.com/img']);
        Storage::disk('public')->put('foto met spatie.jpg', $this->jpegBytes(2000, 1000));

        $url = Media::forFile('foto met spatie.jpg')->url(1200);

        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('cdn.example.com', parse_url($url, PHP_URL_HOST));
        $this->assertStringStartsWith('https://cdn.example.com/img/1200/foto%20met%20spatie-', $url);
    }

    /**
     * A driver that encodes on its own, S3 being the one that does, gets left
     * alone: its URL no longer holds the raw path, so there is nothing to swap
     * and no second round of escapes.
     */
    public function test_a_url_that_is_already_encoded_is_not_encoded_again(): void
    {
        $this->assertSame('/img/foto%20met%20spatie.jpg', ImageUrl::encodePath('/img/foto%20met%20spatie.jpg'));
    }
}
