@php($bg = ($section['background'] ?? null)?->first()?->file_name)
<section
    class="default {{ $section['image_position'] ?? 'right' }} {{ $section->_name }} @if (! empty($section['dark_background'])) dark @endif">
    @if ($bg)
        <img class="section-bg" src="{{ asset_resized('1600', $bg) }}" srcset="{{ asset_resized('900', $bg) }} 900w, {{ asset_resized('1200', $bg) }} 1200w, {{ asset_resized('1600', $bg) }} 1600w, {{ asset_resized('1920', $bg) }} 1920w, {{ asset_resized('2560', $bg) }} 2560w" sizes="100vw" alt="" loading="lazy" decoding="async">
    @endif
    @if ($bg && ! empty($section['dark_background']))
        <div class="section-overlay" aria-hidden="true"></div>
    @endif
    <div class="main-width">
        <article class="article">
            @if ($section->_name === 'quote')
                <blockquote>&ldquo;{!! $section['head'] ?? '' !!}&rdquo;</blockquote>
                @isset($section['body'])
                    <p class="quote-source">&mdash; {!! $section['body'] !!}</p>
                @endisset
            @else
                @php($level = $loop->first ? 'h1' : 'h2')
                <{{ $level }}>{!! $section['head'] !!}</{{ $level }}>
                {!! $section['body'] ?? '' !!}
            @endif
        </article>
        @isset($section->image)
            <div class="images">
                @foreach ($section->image as $image)
                    @php($dim = $image->dimensions())
                    <img
                        srcset="{{ asset_resized('600', $image->file_name) }} 600w, {{ asset_resized('900', $image->file_name) }} 900w, {{ asset_resized('1200', $image->file_name) }} 1200w, {{ asset_resized('1600', $image->file_name) }} 1600w"
                        sizes="(max-width: 550px) 100vw, 50vw"
                        src="{{ asset_resized('900', $image->file_name) }}"
                        alt="{{ $image->alt() }}"
                        @if ($dim) width="{{ $dim['width'] }}" height="{{ $dim['height'] }}" @endif
                        loading="lazy" decoding="async">
                @endforeach
            </div>
        @elseif (isset($section['image_position']))
            <div class="images">
                <span class="image-placeholder" aria-hidden="true"></span>
            </div>
        @endisset
    </div>
</section>
