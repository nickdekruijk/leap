<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class ResponsiveImageComponentTest extends ImageTestCase
{
    private function render(Media $media, string $attributes = ''): string
    {
        return Blade::render('<x-leap::responsive-image :media="$media" '.$attributes.' />', ['media' => $media]);
    }

    private function bitmap(): Media
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(2000, 1000));

        return Media::forFile('pic.jpg');
    }

    public function test_it_renders_a_srcset_and_a_fallback(): void
    {
        config(['leap.images.component_widths' => [600, 1200]]);
        $media = $this->bitmap();
        $hash = substr($media->sha256, 0, 8);

        $html = $this->render($media, 'sizes="100vw"');

        $this->assertStringContainsString('srcset="/img/600/pic-'.$hash.'.jpg.webp 600w, /img/1200/pic-'.$hash.'.jpg.webp 1200w"', $html);
        $this->assertStringContainsString('sizes="100vw"', $html);
        $this->assertStringContainsString('src="/img/1200/pic-'.$hash.'.jpg.webp"', $html);
    }

    public function test_it_reserves_the_aspect_ratio_box(): void
    {
        $media = $this->bitmap();

        $html = $this->render($media, 'sizes="100vw"');

        // Printed so the layout does not jump when the picture arrives.
        $this->assertStringContainsString('width="2000"', $html);
        $this->assertStringContainsString('height="1000"', $html);
    }

    public function test_a_focus_point_becomes_an_object_position(): void
    {
        $media = $this->bitmap();
        $media->meta = ['image_focus' => ['x' => 25, 'y' => 75]];
        $media->save();

        $this->assertStringContainsString('object-position: 25% 75%', $this->render($media, 'sizes="100vw"'));
    }

    public function test_loading_is_lazy_unless_the_image_is_marked_eager(): void
    {
        $media = $this->bitmap();

        $this->assertStringContainsString('loading="lazy"', $this->render($media, 'sizes="100vw"'));
        $this->assertStringContainsString('fetchpriority="high"', $this->render($media, 'sizes="100vw" :eager="true"'));
    }

    public function test_an_svg_is_rendered_without_a_srcset(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $media = Media::forFile('logo.svg');

        $html = $this->render($media);

        $this->assertStringNotContainsString('srcset', $html);
        $this->assertStringContainsString('logo.svg', $html);
    }

    public function test_a_decorative_image_gets_an_empty_alt(): void
    {
        $media = $this->bitmap();
        $media->meta = ['alt' => 'A photograph'];
        $media->save();

        $this->assertStringContainsString('alt="A photograph"', $this->render($media, 'sizes="100vw"'));

        // Empty rather than absent: a screen reader skips it instead of reading
        // out the file name.
        $this->assertStringContainsString('alt=""', $this->render($media, 'sizes="100vw" :decorative="true"'));
    }

    public function test_it_falls_back_to_a_plain_image_while_the_feature_is_off(): void
    {
        $media = $this->bitmap();
        config(['leap.images.enabled' => false]);

        $html = $this->render($media, 'sizes="100vw"');

        $this->assertStringNotContainsString('srcset', $html);
        $this->assertStringContainsString('pic.jpg', $html);
    }

    public function test_without_extra_formats_the_markup_is_the_bare_img_it_always_was(): void
    {
        config(['leap.images.component_widths' => [600, 1200], 'leap.images.defaults.format' => 'webp']);

        $html = $this->render($this->bitmap(), 'sizes="100vw"');

        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringNotContainsString('<source', $html);
    }

    public function test_extra_formats_become_sources_ahead_of_the_img(): void
    {
        // webp and jpg rather than avif: which formats a build can encode is a
        // property of the machine, and this package's CI turned out to differ
        // from the machine it was written on in both directions.
        config([
            'leap.images.component_widths' => [600, 1200],
            'leap.images.defaults.format' => ['webp' => [], 'jpg' => []],
        ]);

        $media = $this->bitmap();
        $hash = substr($media->sha256, 0, 8);
        $html = $this->render($media, 'sizes="100vw"');

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('/img/600.webp/pic-'.$hash.'.jpg.webp 600w', $html);

        // The srcset moves onto the <source>; a browser that took one must not
        // also find a ladder on the <img> and pick from that instead. Asserted
        // against the <img> tag alone, because the document as a whole is full of
        // srcsets, so a substring search over all of it proves nothing.
        preg_match('/<img\b[^>]*>/s', $html, $img);
        $this->assertNotEmpty($img, 'The component renders an <img>.');
        $this->assertStringNotContainsString('srcset', $img[0]);

        // Reached only by a browser that matched no <source>, so: one width, and
        // the source's own format, which is the one encoding everything reads.
        $this->assertStringContainsString('src="/img/1200.fallback/pic-'.$hash.'.jpg"', $html);
    }

    public function test_a_format_that_cannot_be_encoded_is_never_offered(): void
    {
        config([
            'leap.images.component_widths' => [600],
            'leap.images.defaults.format' => ['zzz' => [], 'webp' => []],
        ]);

        $html = $this->render($this->bitmap(), 'sizes="100vw"');

        // A <picture> commits to the first type it recognises and never falls
        // back to the <img>, so a source that cannot be produced would be a
        // broken image with no second chance. What can be produced stays: the
        // webp source is still there, and still worth having.
        //
        // A format no build has, rather than a real one: CI turned out to have a
        // GD that writes avif, so naming avif here would assert the runner's
        // configuration instead of this package's behaviour.
        $this->assertStringNotContainsString('image/zzz', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
    }
}
