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
        $named = config('leap.images.presets.'.$key);

        if (is_array($named)) {
            return new self($key, array_merge(self::defaults(), $named));
        }

        if (ctype_digit($key) && in_array((int) $key, config('leap.images.widths', []), true)) {
            return new self($key, array_merge(self::defaults(), ['width' => (int) $key]));
        }

        return null;
    }

    /**
     * Every preset there is, named ones and widths alike. Used by the warm and
     * prune commands, which have to know the full set rather than the one a URL
     * happens to ask for.
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
            'lossless_from' => [],
            'upscale' => false,
            'blur' => null,
            'grayscale' => false,
        ];
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
