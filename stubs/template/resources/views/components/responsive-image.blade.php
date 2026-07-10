{{--
    Consolidates the srcset/sizes/alt/dimensions/focus-point boilerplate repeated
    across the section views. `sizes` has no universal default -- pass the CSS
    `sizes` value for how this image is actually laid out (e.g. "100vw" for a
    full-bleed background, "(max-width: 550px) 100vw, 50vw" for a half-width
    content image, a fixed px value for a small thumbnail).

    Vector formats (SVG) are already infinitely scalable and asset_resized() has
    no crop/resize path for them (ResizeController only decodes bitmap formats) --
    serve the file as-is, no srcset/sizes/dimensions/focus-point.
--}}
@props([
    'media',
    'sizes',
    'widths' => [600, 900, 1200, 1600],
    'fallback' => null,
    'eager' => false,
    'decorative' => false,
])
@if (! $media->isBitmap())
    <img
        {{ $attributes }}
        src="{{ asset('storage/'.$media->file_name) }}"
        alt="{{ $decorative ? '' : $media->alt() }}"
        @if ($eager) fetchpriority="high" @else loading="lazy" @endif
    >
@else
    @php
        $fallback ??= $widths[intdiv(count($widths), 2)];
        $dim = $media->dimensions();
        $focus = $media->focusPosition();
    @endphp
    <img
        {{ $attributes->except('style') }}
        srcset="{{ collect($widths)->map(fn ($w) => asset_resized($w, $media->file_name).' '.$w.'w')->implode(', ') }}"
        sizes="{{ $sizes }}"
        src="{{ asset_resized($fallback, $media->file_name) }}"
        alt="{{ $decorative ? '' : $media->alt() }}"
        @if ($dim) width="{{ $dim['width'] }}" height="{{ $dim['height'] }}" @endif
        @if ($eager) fetchpriority="high" @else loading="lazy" @endif
        decoding="async"
        @if ($focus) style="object-position: {{ $focus['x'] }}% {{ $focus['y'] }}%; {{ $attributes->get('style') }}" @endif
    >
@endif
