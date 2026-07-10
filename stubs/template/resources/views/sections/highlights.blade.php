@if ($section->_first)
    @php($bg = ($section['background'] ?? null)?->first())
    <section
        class="items highlights items-horizontal @if (! empty($section['dark_background'])) dark @endif">
        @if ($bg)
            <x-responsive-image class="section-bg" :media="$bg" sizes="100vw" :widths="[900, 1200, 1600, 1920, 2560]" fallback="1600" decorative />
        @endif
        @if ($bg && ! empty($section['dark_background']))
            <div class="section-overlay" aria-hidden="true"></div>
        @endif
        <div class="items-scroller main-width">
            <ul class="items-container" tabindex="0" role="group" aria-label="{{ __('Highlights') }}">
@endif

            <li class="item article">
                @php($image = ($section['image'] ?? null)?->first())
                <div class="item-thumbnail">
                    @if ($image)
                        <x-responsive-image :media="$image" sizes="(max-width: 550px) 80vw, 360px" :widths="[600, 900, 1200]" fallback="600" draggable="false" />
                    @else
                        <span class="image-placeholder" aria-hidden="true"></span>
                    @endif
                </div>
                @isset($section->head)
                    <h3>{{ $section->head }}</h3>
                @endisset
                {!! $section->body ?? '' !!}
                @isset($section->button, $section->button_link)
                    <p><a class="button" href="{{ $section->button_link }}">{{ $section->button }}</a></p>
                @endisset
            </li>

@if ($section->_last)
            </ul>
        </div>
    </section>
@endif
