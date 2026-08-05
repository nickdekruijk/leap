<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\ImagePreset;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class ImagePresetTest extends ImageTestCase
{
    public function test_a_configured_width_is_a_preset(): void
    {
        config(['leap.images.widths' => [600, 1200]]);

        $preset = ImagePreset::find(1200);

        $this->assertSame('1200', $preset->name);
        $this->assertSame(1200, $preset->width());
        $this->assertNull($preset->height());
    }

    public function test_a_width_that_is_not_configured_is_not_a_preset(): void
    {
        config(['leap.images.widths' => [600, 1200]]);

        // The allowlist: without it a URL could ask for any size at all, and
        // every distinct number would cost a decode and a file.
        $this->assertNull(ImagePreset::find(1201));
        $this->assertNull(ImagePreset::find('nonsense'));
        $this->assertNull(ImagePreset::find(null));
    }

    public function test_a_named_preset_wins_over_a_width(): void
    {
        config([
            'leap.images.widths' => [600],
            'leap.images.presets' => ['600' => ['width' => 600, 'height' => 600, 'fit' => 'cover']],
        ]);

        $preset = ImagePreset::find(600);

        $this->assertSame('cover', $preset->fit());
        $this->assertSame(600, $preset->height());
    }

    public function test_defaults_fill_in_what_a_preset_leaves_out(): void
    {
        config([
            'leap.images.defaults' => ['quality' => 55, 'format' => 'webp', 'fit' => 'contain'],
            'leap.images.presets' => ['og' => ['width' => 1200, 'height' => 630, 'fit' => 'cover']],
        ]);

        $preset = ImagePreset::find('og');

        $this->assertSame(55, $preset->quality());
        $this->assertSame('webp', $preset->format());
        $this->assertSame('cover', $preset->fit());
    }

    public function test_the_output_format_decides_the_extension_and_suffix(): void
    {
        config(['leap.images.presets' => [
            'webp' => ['width' => 600, 'format' => 'webp'],
            'keep' => ['width' => 600, 'format' => null],
        ]]);

        $this->assertSame('webp', ImagePreset::find('webp')->extension('jpg'));
        $this->assertSame('.webp', ImagePreset::find('webp')->pathSuffix('jpg'));

        // A webp source encoded as webp needs nothing appended.
        $this->assertSame('', ImagePreset::find('webp')->pathSuffix('webp'));

        $this->assertSame('jpg', ImagePreset::find('keep')->extension('jpg'));
        $this->assertSame('', ImagePreset::find('keep')->pathSuffix('jpg'));
    }

    public function test_all_returns_widths_and_named_presets_together(): void
    {
        config([
            'leap.images.widths' => [600, 1200],
            'leap.images.presets' => ['og' => ['width' => 1200]],
        ]);

        // PHP keys a numeric string as an int; the preset's own name stays the
        // string that goes in the URL.
        $this->assertSame([600, 1200, 'og'], array_keys(ImagePreset::all()));
        $this->assertSame(['600', '1200', 'og'], array_map(fn ($p) => $p->name, array_values(ImagePreset::all())));
    }

    public function test_lossless_applies_to_the_configured_source_formats(): void
    {
        config(['leap.images.presets' => [
            'thumb' => ['width' => 600, 'lossless_from' => ['png']],
        ]]);

        $preset = ImagePreset::find('thumb');

        $this->assertTrue($preset->isLossless('PNG'));
        $this->assertFalse($preset->isLossless('jpg'));
    }
}
