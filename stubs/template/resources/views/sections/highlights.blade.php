@if ($section->_first)
    @php($bg = $section->background?->first()?->file_name ?? null)
    <section
        class="items highlights items-horizontal @if ($bg) has-background @endif"
        @if ($bg) style="background-image: url('{{ asset_resized('1920', $bg) }}')" @endif>
        @if ($bg)
            <div class="section-overlay" aria-hidden="true"></div>
        @endif
        <div class="items-scroller main-width">
            <ul class="items-container" tabindex="0" role="group" aria-label="{{ __('Highlights') }}">
@endif

            <li class="item article">
                @isset($section->image)
                    @php($file = $section->image->first()?->file_name)
                    @if ($file)
                        <div class="item-thumbnail">
                            <img src="{{ asset_resized('600', $file) }}" alt="{{ $section->image->first()?->meta?->alt }}" loading="lazy" draggable="false">
                        </div>
                    @endif
                @endisset
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
