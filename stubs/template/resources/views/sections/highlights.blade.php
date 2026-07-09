@if ($section->_first)
    @php($bg = ($section['background'] ?? null)?->first()?->file_name)
    <section
        class="items highlights items-horizontal @if ($bg) has-background @endif @if (! empty($section['dark_background'])) dark @endif">
        @if ($bg)
            <img class="section-bg" src="{{ asset_resized('1600', $bg) }}" srcset="{{ asset_resized('900', $bg) }} 900w, {{ asset_resized('1200', $bg) }} 1200w, {{ asset_resized('1600', $bg) }} 1600w, {{ asset_resized('1920', $bg) }} 1920w, {{ asset_resized('2560', $bg) }} 2560w" sizes="100vw" alt="" loading="lazy" decoding="async">
        @endif
        @if ($bg && ! empty($section['dark_background']))
            <div class="section-overlay" aria-hidden="true"></div>
        @endif
        <div class="items-scroller main-width">
            <ul class="items-container" tabindex="0" role="group" aria-label="{{ __('Highlights') }}">
@endif

            <li class="item article">
                @php($file = ($section['image'] ?? null)?->first()?->file_name)
                <div class="item-thumbnail">
                    @if ($file)
                        <img
                            srcset="{{ asset_resized('600', $file) }} 600w, {{ asset_resized('900', $file) }} 900w, {{ asset_resized('1200', $file) }} 1200w"
                            sizes="(max-width: 550px) 80vw, 360px"
                            src="{{ asset_resized('600', $file) }}"
                            alt="{{ $section->image->first()?->alt() }}"
                            loading="lazy" decoding="async" draggable="false">
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
