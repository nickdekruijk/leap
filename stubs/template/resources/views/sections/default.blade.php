@php($bg = ($section['background'] ?? null)?->first())
<section
    class="default {{ $section['image_position'] ?? 'right' }} {{ $section->_name }} @if (! empty($section['dark_background'])) dark @endif">
    @if ($bg)
        <x-responsive-image class="section-bg" :media="$bg" sizes="100vw" :widths="[900, 1200, 1600, 1920, 2560]" fallback="1600" decorative />
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
                    <x-responsive-image :media="$image" sizes="(max-width: 550px) 100vw, 50vw" :widths="[600, 900, 1200, 1600]" fallback="900" />
                @endforeach
            </div>
        @elseif (isset($section['image_position']))
            <div class="images">
                <span class="image-placeholder" aria-hidden="true"></span>
            </div>
        @endisset
    </div>
</section>
