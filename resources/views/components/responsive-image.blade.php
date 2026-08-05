{{--
    Consolidates the srcset/sizes/alt/dimensions/focus-point boilerplate an image
    on a page needs. `sizes` has no universal default -- pass the CSS `sizes`
    value for how this image is actually laid out (e.g. "100vw" for a full-bleed
    background, "(max-width: 550px) 100vw, 50vw" for a half-width content image,
    a fixed px value for a small thumbnail).

    Vector formats (SVG) and anything leap does not resize are served as they
    are, without srcset/sizes/dimensions/focus-point: an SVG already scales, and
    rasterising it would only make it worse.
--}}
@props([
    'media',
    'sizes' => null,
    'widths' => null,
    'fallback' => null,
    'eager' => false,
    'decorative' => false,
])
@php
    $widths ??= config('leap.images.component_widths', []);
    $srcset = $media?->srcset($widths);
@endphp
@if (! $media)
@elseif (! $srcset)
    <img
        {{ $attributes }}
        src="{{ $media->url() }}"
        alt="{{ $decorative ? '' : $media->alt() }}"
        @if ($eager) fetchpriority="high" @else loading="lazy" @endif
    >
@else
    @php
        $fallback ??= $widths[intdiv(count($widths), 2)];
        $dimensions = $media->dimensions();
        $focus = $media->focusPosition();
    @endphp
    <img
        {{ $attributes->except('style') }}
        srcset="{{ $srcset }}"
        @if ($sizes) sizes="{{ $sizes }}" @endif
        src="{{ $media->url($fallback) }}"
        alt="{{ $decorative ? '' : $media->alt() }}"
        @if ($dimensions) width="{{ $dimensions['width'] }}" height="{{ $dimensions['height'] }}" @endif
        @if ($eager) fetchpriority="high" @else loading="lazy" @endif
        decoding="async"
        @if ($focus) style="object-position: {{ $focus['x'] }}% {{ $focus['y'] }}%; {{ $attributes->get('style') }}" @endif
    >
@endif
