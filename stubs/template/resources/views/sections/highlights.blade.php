@if ($section->_first)
    @php($bg = ($section['background'] ?? null)?->first()?->file_name)
    <section
        class="items highlights items-horizontal @if ($bg) has-background @endif @if (! empty($section['dark_background'])) dark @endif"
        @if ($bg) style="background-image: url('{{ asset_resized('1920', $bg) }}')" @endif>
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
                        <img src="{{ asset_resized('600', $file) }}" alt="{{ $section->image->first()?->meta?->alt }}" loading="lazy" draggable="false">
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
