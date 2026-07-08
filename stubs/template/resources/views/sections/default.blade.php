@php($bg = ($section['background'] ?? null)?->first()?->file_name)
<section
    class="default {{ $section['image_position'] ?? 'right' }} {{ $section->_name }} @if ($bg) has-background @endif"
    @if ($bg) style="background-image: url('{{ asset_resized('1920', $bg) }}')" @endif>
    @if ($bg)
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
                <h2>{!! $section['head'] !!}</h2>
                {!! $section['body'] ?? '' !!}
            @endif
        </article>
        @isset($section->image)
            <div class="images">
                @foreach ($section->image as $image)
                    <img
                        srcset="{{ asset_resized('600', $image->file_name) }} 600w, {{ asset_resized('900', $image->file_name) }} 900w, {{ asset_resized('1200', $image->file_name) }} 1200w, {{ asset_resized('1600', $image->file_name) }} 1600w"
                        sizes="(max-width: 550px) 100vw, 50vw"
                        src="{{ asset_resized('900', $image->file_name) }}"
                        alt="{{ $image->meta?->alt }}"
                        loading="lazy">
                @endforeach
            </div>
        @endisset
    </div>
</section>
