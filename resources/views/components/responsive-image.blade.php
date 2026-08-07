{{--
    Consolidates the srcset/sizes/alt/dimensions/focus-point boilerplate an image
    on a page needs. `sizes` has no universal default -- pass the CSS `sizes`
    value for how this image is actually laid out (e.g. "100vw" for a full-bleed
    background, "(max-width: 550px) 100vw, 50vw" for a half-width content image,
    a fixed px value for a small thumbnail).

    Vector formats (SVG) and anything leap does not resize are served as they
    are, without srcset/sizes/dimensions/focus-point: an SVG already scales, and
    rasterising it would only make it worse.

    With leap.images.formats set the <img> is wrapped in a <picture> carrying one
    <source> per format, best first. A browser takes the first type it
    recognises, so the order in the config is the order of preference, and the
    <img> is what is left for a browser that recognised none of them. Without
    those formats the markup is the bare <img> it has always been.
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
    // Normalised to preset => width, so a ladder of named presets works the same
    // as a ladder of plain widths: ['hero-900' => 900, 'hero-1200' => 1200].
    $ladder = NickDeKruijk\Leap\Classes\ImageUrl::ladder($widths);
    $srcset = $media?->srcset($ladder);
    $sources = $srcset ? $media->sources($ladder) : [];
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
        // The middle rung by position, which is a preset name and not always a
        // number -- a ladder may be named presets.
        $fallback ??= array_keys($ladder)[intdiv(count($ladder), 2)] ?? null;
        $dimensions = $media->dimensions();
        $focus = $media->focusPosition();

        // Only reached by a browser that matched no <source> at all, so it gets
        // one width rather than a ladder of its own -- and, by default, the
        // source's own format, which is the only encoding every browser reads.
        $last = $sources
            ? ($media->url($fallback.'.'.NickDeKruijk\Leap\Classes\ImagePreset::FALLBACK) ?: $media->url($fallback))
            : $media->url($fallback);
    @endphp
    @if ($sources)<picture>
        @foreach ($sources as $source)
            <source type="{{ $source['type'] }}" srcset="{{ $source['srcset'] }}" @if ($sizes) sizes="{{ $sizes }}" @endif>
        @endforeach
    @endif
    <img
        {{ $attributes->except('style') }}
        @unless ($sources) srcset="{{ $srcset }}" @endunless
        @if ($sizes && ! $sources) sizes="{{ $sizes }}" @endif
        src="{{ $last }}"
        alt="{{ $decorative ? '' : $media->alt() }}"
        @if ($dimensions) width="{{ $dimensions['width'] }}" height="{{ $dimensions['height'] }}" @endif
        @if ($eager) fetchpriority="high" @else loading="lazy" @endif
        decoding="async"
        @if ($focus) style="object-position: {{ $focus['x'] }}% {{ $focus['y'] }}%; {{ $attributes->get('style') }}" @endif
    >
    @if ($sources)</picture>@endif
@endif
