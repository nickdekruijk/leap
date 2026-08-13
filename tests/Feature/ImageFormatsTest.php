<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use NickDeKruijk\Leap\Classes\ImagePreset;
use NickDeKruijk\Leap\Classes\ImageResizer;
use NickDeKruijk\Leap\Classes\ImageUrl;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

/**
 * leap.images.formats: the extra encodings a <picture> offers ahead of the
 * default one, addressed as "{preset}.{format}".
 */
class ImageFormatsTest extends ImageTestCase
{
    private function media(string $path = 'photos/office.jpg'): Media
    {
        $this->fakeDisks();
        Storage::disk('public')->put($path, $this->jpegBytes(2000, 1000));

        return Media::forFile($path);
    }

    private function withFormats(array $formats = ['avif' => ['quality' => 55], 'webp' => []]): void
    {
        config([
            'leap.images.widths' => [600, 1200],
            'leap.images.defaults.format' => $formats,
        ]);
    }

    public function test_a_width_can_be_asked_for_in_a_configured_format(): void
    {
        $this->withFormats();

        $preset = ImagePreset::find('1200.avif');

        $this->assertSame('1200.avif', $preset->name);
        $this->assertSame(1200, $preset->width());
        $this->assertSame('avif', $preset->format());
    }

    public function test_a_format_carries_its_own_options(): void
    {
        $this->withFormats();

        // Per format on purpose: avif reaches the same picture at a lower
        // number, and webp's quality carried over would make the avif copy the
        // larger of the two.
        $this->assertSame(55, ImagePreset::find('1200.avif')->quality());
        $this->assertSame(80, ImagePreset::find('1200.webp')->quality());
    }

    public function test_a_format_that_is_not_configured_is_not_a_preset(): void
    {
        $this->withFormats();

        // The same allowlist that stops /img/9999/: a format has to be one this
        // preset offers, or the URL does not exist.
        $this->assertNull(ImagePreset::find('1200.jpg'));
        $this->assertNull(ImagePreset::find('1200.bmp'));
        $this->assertNull(ImagePreset::find('9999.avif'));
    }

    public function test_a_named_preset_can_be_asked_for_in_a_format_too(): void
    {
        config([
            'leap.images.widths' => [600],
            'leap.images.presets' => ['og' => ['width' => 1200, 'height' => 630, 'fit' => 'cover']],
            'leap.images.defaults.format' => ['avif' => [], 'webp' => []],
        ]);

        $preset = ImagePreset::find('og.avif');

        $this->assertSame('og.avif', $preset->name);
        $this->assertSame(630, $preset->height());
        $this->assertSame('cover', $preset->fit());
        $this->assertSame('avif', $preset->format());
    }

    public function test_a_format_copy_lands_beside_the_default_one_not_on_top_of_it(): void
    {
        $this->withFormats();

        // Different name, so a different directory: the two encodings of the
        // same width are separate files and neither overwrites the other.
        $default = ImageResizer::targetPath('photos/office.jpg', ImagePreset::find('1200'), 'a1b2c3d4');
        $avif = ImageResizer::targetPath('photos/office.jpg', ImagePreset::find('1200.avif'), 'a1b2c3d4');

        $this->assertSame('1200/photos/office-a1b2c3d4.jpg.webp', $default);
        $this->assertSame('1200.avif/photos/office-a1b2c3d4.jpg.avif', $avif);
    }

    public function test_all_crosses_every_base_with_every_format_without_crossing_the_formats(): void
    {
        $this->withFormats(['avif' => [], 'webp' => []]);

        $names = array_keys(ImagePreset::all());

        $this->assertContains('600.avif', $names);
        $this->assertContains('1200.webp', $names);

        // Growing the array while walking it would ask for this, and prune
        // would then keep a directory nothing can ever be served from.
        $this->assertNotContains('600.avif.webp', $names);
    }

    public function test_prune_knows_the_fallback_so_it_does_not_sweep_it(): void
    {
        $this->withFormats();

        $this->assertContains('1200.'.ImagePreset::FALLBACK, array_keys(ImagePreset::all()));
    }

    public function test_the_fallback_keeps_the_source_format(): void
    {
        $this->withFormats();

        // Forcing jpg here would flatten every transparent png onto black. Avif
        // and webp both carry alpha, so this last step is the only one that
        // could lose it.
        $this->assertNull(ImagePreset::find('1200.'.ImagePreset::FALLBACK)->format());
        $this->assertSame(1200, ImagePreset::find('1200.'.ImagePreset::FALLBACK)->width());
    }

    /**
     * The whole reason the fallback is a per-preset override rather than a
     * preset of its own: a cropped preset whose fallback was a loose width would
     * hand a legacy browser a different shape than every <source> above it.
     */
    public function test_the_fallback_inherits_the_shape_of_the_preset_it_belongs_to(): void
    {
        config([
            'leap.images.widths' => [600],
            'leap.images.presets' => ['square' => ['width' => 600, 'height' => 600, 'fit' => 'cover']],
            'leap.images.defaults.format' => ['avif' => [], 'webp' => []],
        ]);

        $fallback = ImagePreset::find('square.'.ImagePreset::FALLBACK);

        $this->assertSame(600, $fallback->width());
        $this->assertSame(600, $fallback->height());
        $this->assertSame('cover', $fallback->fit());
        $this->assertNull($fallback->format());
    }

    public function test_a_preset_can_narrow_its_own_fallback(): void
    {
        config([
            'leap.images.widths' => [2560],
            'leap.images.defaults.format' => ['avif' => [], 'webp' => []],
            'leap.images.defaults.fallback' => ['width' => 1200, 'format' => null],
        ]);

        $this->assertSame(1200, ImagePreset::find('2560.'.ImagePreset::FALLBACK)->width());
    }

    public function test_without_formats_there_is_no_fallback_and_nothing_changes(): void
    {
        config(['leap.images.widths' => [600, 1200], 'leap.images.defaults.format' => 'webp']);

        // No extra formats means no <picture>, so the <img> carries the ordinary
        // ladder and there is nothing for a fallback to be a fallback from. A
        // project that configured none generates exactly what it did before.
        $this->assertNull(ImagePreset::find('1200.'.ImagePreset::FALLBACK));
        $this->assertSame([600, 1200], array_keys(ImagePreset::all()));
    }

    public function test_sources_offers_only_what_the_driver_can_encode(): void
    {
        $this->withFormats(['zzz' => [], 'webp' => []]);

        $media = $this->media('photos/office.jpg');
        $types = array_column(ImageUrl::sources($media, [600, 1200]), 'type');

        foreach (['zzz', 'webp'] as $format) {
            // A <picture> commits to the first type it recognises and never
            // falls back to the <img>, so offering a format the driver cannot
            // produce is a broken image with no second chance.
            $this->assertSame(ImageResizer::supports($format), in_array('image/'.$format, $types, true));
        }
    }

    /**
     * A width this project does not have must not cost the whole <picture>.
     *
     * srcset() skips an unknown rung silently, so a ladder of [300, 600, 1200] on a
     * project whose ladder starts at 600 still produces a usable srcset. sources() read
     * the offered formats from the first rung only, and an unresolvable one meant "no
     * formats at all": no <source>, no <picture>, and the component quietly fell back to
     * a bare <img> in the default encoding. Nothing said so, and the page looked right.
     *
     * The two halves have to agree on being tolerant, or a single stray number in a
     * template turns AVIF off for that element and nowhere else.
     */
    public function test_an_unknown_width_does_not_cost_the_other_formats(): void
    {
        $this->withFormats();

        $media = $this->media();

        $types = array_column(ImageUrl::sources($media, [300, 600, 1200]), 'type');

        $this->assertContains('image/webp', $types);
        $this->assertSame(
            array_column(ImageUrl::sources($media, [600, 1200]), 'type'),
            $types,
            'An unknown rung in front should leave the offered formats exactly as they were.',
        );
    }

    /**
     * And a ladder with nothing recognisable in it offers nothing, rather than guessing.
     * There is no preset to read the formats from, so there is no honest answer.
     */
    public function test_a_ladder_of_nothing_but_unknown_widths_offers_no_sources(): void
    {
        $this->withFormats();

        $this->assertSame([], ImageUrl::sources($this->media(), [300, 450]));
    }

    public function test_a_source_srcset_points_at_the_format_it_declares(): void
    {
        $this->withFormats();

        $srcset = ImageUrl::srcset($this->media(), [600, 1200], 'avif');

        // The URL is composed from the preset, not from anything the encoder
        // did, so it reads the same on a build that cannot produce the bytes.
        $this->assertStringContainsString('/600.avif/', $srcset);
        $this->assertStringContainsString('.avif 600w', $srcset);
    }

    /**
     * The encoder itself, on the only driver that has one. GD has no avif
     * support at all, so without this the format leap now offers would never be
     * produced anywhere in the suite.
     */
    public function test_the_driver_actually_encodes_the_avif_a_source_promises(): void
    {
        config(['image.driver' => Driver::class]);
        $this->withFormats();

        // Gated on the probe rather than on Imagick::queryFormats(), which lists
        // avif on builds that then fail to encode it: the format being known is
        // not the format being writable, and that gap is the whole reason
        // supports() encodes a pixel instead of reading a list.
        if (! ImageResizer::supports('avif')) {
            $this->markTestSkipped('This build cannot encode avif.');
        }

        $encoded = ImageResizer::encode($this->jpegBytes(800, 600), ImagePreset::find('600.avif'), 'jpg');

        $this->assertNotNull($encoded);
        $this->assertSame('image/avif', $encoded->mediaType());
    }

    /**
     * A format nothing can encode, rather than a real one this or that build
     * happens to lack. Which formats a driver has is a property of the machine:
     * this package's CI turned out to run a GD that writes avif and an Imagick
     * that does not, so a test naming either would assert the runner's
     * configuration and not this package's behaviour.
     */
    public function test_a_format_that_cannot_be_encoded_is_never_offered(): void
    {
        $this->withFormats(['zzz' => [], 'webp' => []]);

        $this->assertFalse(ImageResizer::supports('zzz'));

        // Not "no sources at all": webp can be encoded, so that one is still
        // offered and the <picture> still earns its keep. Only the format that
        // cannot be produced is left out.
        $types = array_column(ImageUrl::sources($this->media(), [600]), 'type');

        $this->assertNotContains('image/zzz', $types);
        $this->assertContains('image/webp', $types);
    }

    public function test_supports_answers_for_a_format_no_build_has(): void
    {
        $this->assertFalse(ImageResizer::supports('not-an-image-format'));
    }

    /**
     * A list of nothing this driver can encode must not leave that format in
     * every plain srcset and every og:image, addresses no copy can ever be
     * written for. Nothing encodable left means the source's own format, which
     * works everywhere.
     */
    public function test_a_lone_url_skips_a_format_the_driver_cannot_encode(): void
    {
        $this->withFormats(['zzz' => []]);

        $this->assertFalse(ImageResizer::supports('zzz'));
        $this->assertNull(ImagePreset::find('1200')->format());
        $this->assertStringEndsWith('.jpg', (string) $this->media()->url(1200));
    }

    public function test_a_lone_url_takes_the_last_format_that_can_be_encoded(): void
    {
        $this->withFormats(['webp' => [], 'zzz' => []]);

        // zzz is unreachable everywhere, webp is not, so webp and not the source.
        $this->assertSame('webp', ImagePreset::find('1200')->format());
    }

    /**
     * A ladder of named presets. Without this anything wanting a preset the
     * widths cannot express, a hero at its own quality or a crop, has to build
     * its own srcset, and then its own <picture> too, which is where the
     * heaviest image on the page quietly loses the format it benefits from most.
     */
    public function test_a_ladder_may_name_its_presets(): void
    {
        config([
            'leap.images.presets' => [
                'hero-900' => ['width' => 900, 'quality' => 65],
                'hero-1600' => ['width' => 1600, 'quality' => 65],
            ],
            'leap.images.defaults.format' => ['avif' => [], 'webp' => []],
        ]);

        $srcset = ImageUrl::srcset($this->media(), ['hero-900' => 900, 'hero-1600' => 1600], 'avif');

        $this->assertStringContainsString('/hero-900.avif/', $srcset);
        $this->assertStringContainsString('.avif 900w', $srcset);
        $this->assertStringContainsString('/hero-1600.avif/', $srcset);
        $this->assertStringContainsString('.avif 1600w', $srcset);
    }

    public function test_a_named_ladder_still_offers_the_picture_sources(): void
    {
        config([
            'leap.images.presets' => ['hero-900' => ['width' => 900, 'quality' => 65]],
            'leap.images.defaults.format' => ['avif' => [], 'webp' => []],
        ]);

        $types = array_column(ImageUrl::sources($this->media(), ['hero-900' => 900]), 'type');

        $this->assertContains('image/webp', $types);
    }

    public function test_a_plain_list_still_means_preset_and_width_are_the_same(): void
    {
        $this->withFormats();

        $this->assertSame(['600' => 600, '1200' => 1200], ImageUrl::ladder([600, 1200]));
        $this->assertSame(['hero-900' => 900], ImageUrl::ladder(['hero-900' => 900]));
    }
}
