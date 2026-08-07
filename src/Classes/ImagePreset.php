<?php

namespace NickDeKruijk\Leap\Classes;

/**
 * One entry from leap.images: the size and encoding a resized copy is made at.
 *
 * Also the allowlist. A preset only exists if it is named in leap.images.presets
 * or is a width from leap.images.widths, so a URL cannot ask for an arbitrary
 * size — otherwise /img/9999/... would be an invitation to fill the disk and
 * pin the CPU with one request per pixel value.
 */
class ImagePreset
{
    /**
     * URL suffix of the <picture> fallback: "1200.fallback". Reserved, so a
     * format of this name would never be reached. No image format is called
     * this, and none is likely to be.
     */
    public const FALLBACK = 'fallback';

    /**
     * @param  string  $name  As it appears in the URL
     * @param  array<string, mixed>  $options  Merged over leap.images.defaults
     */
    private function __construct(
        public readonly string $name,
        private readonly array $options,
    ) {}

    /**
     * The preset by name, or null when there is no such preset.
     *
     * A named preset wins over a width: leap.images.presets is where a project
     * says what it means, and a number is only shorthand for "that width, all
     * defaults".
     */
    public static function find(string|int|null $key): ?self
    {
        if ($key === null || $key === '') {
            return null;
        }

        $key = (string) $key;

        // "1200.avif": the same preset, encoded as one of the other formats it
        // offers. Validated against that preset's own list rather than a global
        // one, because 'format' is a per-preset option. A preset that offers
        // only webp has no avif address, and /img/1200.bmp/ is as much a 404 as
        // /img/9999/ is.
        $position = strrpos($key, '.');
        $base = $position === false ? $key : substr($key, 0, $position);
        $suffix = $position === false ? null : strtolower(substr($key, $position + 1));

        $options = self::optionsFor($base);

        if ($options === null) {
            return null;
        }

        if ($suffix === null) {
            return new self($base, $options);
        }

        // Only a list is an offer. formatList() normalises a lone string to one
        // entry, which is right for reading a format but wrong here: one format
        // is one <img>, so there is nothing to address by suffix and nothing for
        // a fallback to be a fallback from.
        $offered = is_array($options['format'] ?? null) ? self::formatList($options['format']) : [];

        // "1200.fallback": the <img> inside that preset's <picture>. Built on
        // the preset rather than beside it, so width, height and fit come along.
        // A square preset whose fallback was a loose 1200 would hand a legacy
        // browser a different shape than every <source> above it, and the
        // layout would jump on exactly the machines least able to cope.
        if ($suffix === self::FALLBACK) {
            return $offered === []
                ? null
                : new self($base.'.'.self::FALLBACK, array_merge($options, self::fallbackOverrides($options)));
        }

        if (! array_key_exists($suffix, $offered)) {
            return null;
        }

        return new self($base.'.'.$suffix, array_merge($options, $offered[$suffix], ['format' => $suffix]));
    }

    /**
     * Every preset there is: names, widths, each one crossed with the formats it
     * offers, and the <picture> fallback of each. Used by the warm and prune
     * commands, which have to know the full set rather than the one a URL
     * happens to ask for. A copy prune does not recognise is one it deletes as
     * an orphan on the next sweep.
     *
     * @return array<string, self>
     */
    public static function all(): array
    {
        $presets = [];

        foreach (config('leap.images.widths', []) as $width) {
            $presets[(string) $width] = self::find($width);
        }

        foreach (array_keys(config('leap.images.presets', [])) as $name) {
            $presets[(string) $name] = self::find($name);
        }

        $presets = array_filter($presets);

        // The bases are taken before the loop: growing the array it is walking
        // would cross the formats with each other and ask for "1200.avif.webp".
        foreach (array_keys($presets) as $name) {
            if ($presets[$name]->formats() === []) {
                continue;
            }

            foreach (array_keys($presets[$name]->formats()) as $format) {
                $presets[$name.'.'.$format] = self::find($name.'.'.$format);
            }

            $presets[$name.'.'.self::FALLBACK] = self::find($name.'.'.self::FALLBACK);
        }

        return array_filter($presets);
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return config('leap.images.defaults', []) + [
            'fit' => 'contain',
            'height' => null,
            'quality' => 80,
            'effort' => null,
            'format' => null,
            'fallback' => [],
            'lossless_from' => [],
            'upscale' => false,
            'blur' => null,
            'grayscale' => false,
        ];
    }

    /**
     * The options of a base preset, meaning a name from leap.images.presets or
     * a width from leap.images.widths. Null when there is no such preset.
     *
     * @return array<string, mixed>|null
     */
    private static function optionsFor(string $key): ?array
    {
        $named = config('leap.images.presets.'.$key);

        if (is_array($named)) {
            return array_merge(self::defaults(), $named);
        }

        if (ctype_digit($key) && in_array((int) $key, config('leap.images.widths', []), true)) {
            return array_merge(self::defaults(), ['width' => (int) $key]);
        }

        return null;
    }

    /**
     * A 'format' option normalised to an ordered map of extension => options.
     *
     * A string is one entry and an array is several, best first. Accepts a bare
     * list as well as a map, so ['avif', 'webp'] and ['avif' => [...]] both
     * work. Null means "keep the source format", which has nothing to offer and
     * yields an empty list.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function formatList(mixed $format): array
    {
        if ($format === null || $format === '') {
            return [];
        }

        $list = [];

        foreach ((array) $format as $key => $value) {
            $list[strtolower((string) (is_int($key) ? $value : $key))] = is_array($value) ? $value : [];
        }

        return $list;
    }

    /**
     * The formats this preset offers a <picture>, best first. Empty unless
     * 'format' was given as an array. One format is one <img>, as before.
     *
     * @return array<string, array<string, mixed>>
     */
    public function formats(): array
    {
        return is_array($this->options['format'] ?? null) ? self::formatList($this->options['format']) : [];
    }

    /**
     * What a preset's 'fallback' option overrides on top of the preset itself.
     *
     * Absent 'format' means "keep the source's own", which is the point of this
     * preset. But array_merge only overrides a key the override array has, and
     * every preset carries a format already.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function fallbackOverrides(array $options): array
    {
        $overrides = (array) ($options['fallback'] ?? []);

        if (! array_key_exists('format', $overrides)) {
            $overrides['format'] = null;
        }

        return $overrides;
    }

    public function width(): ?int
    {
        return ($this->options['width'] ?? null) ? (int) $this->options['width'] : null;
    }

    public function height(): ?int
    {
        return ($this->options['height'] ?? null) ? (int) $this->options['height'] : null;
    }

    /**
     * 'cover' crops to exactly width x height; anything else fits within them.
     */
    public function fit(): string
    {
        return (string) ($this->options['fit'] ?? 'contain');
    }

    public function quality(): int
    {
        return (int) ($this->options['quality'] ?? 80);
    }

    /**
     * How hard the webp encoder should work, 0 to 6, or null for its default.
     * libwebp's "method": the same picture at the same quality, in fewer bytes,
     * for more time spent encoding it.
     */
    public function effort(): ?int
    {
        $effort = $this->options['effort'] ?? null;

        return $effort === null ? null : max(0, min(6, (int) $effort));
    }

    public function upscale(): bool
    {
        return (bool) ($this->options['upscale'] ?? false);
    }

    public function blur(): ?int
    {
        return ($this->options['blur'] ?? null) ? (int) $this->options['blur'] : null;
    }

    public function grayscale(): bool
    {
        return (bool) ($this->options['grayscale'] ?? false);
    }

    /**
     * The extension the copy is encoded as, or null when the source format is
     * kept.
     */
    public function format(): ?string
    {
        $format = $this->options['format'] ?? null;

        // A lone URL cannot negotiate: og:image, a video poster, anything not
        // going through <picture>. The list is best first, so the last entry is
        // the most compatible one, and that is what a single address gets --
        // handing a scraper avif because it happened to be listed first is how
        // a social preview turns into a blank box.
        //
        // Only what this driver can actually encode counts. Without that, a list
        // of nothing but avif on GD would put .avif in every plain srcset and
        // every og:image, addresses no copy can ever be written for. Nothing
        // encodable left means the source's own format, which always works.
        if (is_array($format)) {
            $supported = array_filter(
                array_keys(self::formatList($format)),
                fn (string $extension): bool => ImageResizer::supports($extension)
            );

            $format = $supported ? end($supported) : null;
        }

        return $format ? strtolower((string) $format) : null;
    }

    /**
     * Whether a source of this extension is encoded losslessly. Lossy webp
     * turns the text in a screenshot to mush, and a screenshot is exactly the
     * kind of PNG a site stores.
     */
    public function isLossless(string $sourceExtension): bool
    {
        return in_array(strtolower($sourceExtension), array_map('strtolower', (array) ($this->options['lossless_from'] ?? [])), true);
    }

    /**
     * The extension of the resized copy for a source of the given extension.
     */
    public function extension(string $sourceExtension): string
    {
        return $this->format() ?: strtolower($sourceExtension);
    }

    /**
     * What gets appended to the source path to make the copy's path: an empty
     * string when the format is kept, '.webp' when it is not.
     *
     * Appended rather than substituted so the source is a single strip away
     * ('photo.jpg.webp' -> 'photo.jpg') and the web server still recognises the
     * type it is serving from the last extension.
     */
    public function pathSuffix(string $sourceExtension): string
    {
        $extension = $this->extension($sourceExtension);

        return $extension === strtolower($sourceExtension) ? '' : '.'.$extension;
    }
}
