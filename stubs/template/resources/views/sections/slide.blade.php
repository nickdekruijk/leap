@if ($section->_first)
    <section class="slider" aria-label="{{ __('Slider') }}" id="slider-{{ $loop->iteration }}">
@endif
        @php($file = ($section['image'] ?? null)?->first()?->file_name)
        <div class="slide @if (! $file) slide-placeholder @endif" role="group" aria-roledescription="{{ __('slide') }}" aria-label="{{ __('Slide :n', ['n' => $loop->iteration]) }}">
            @if ($file && str_ends_with($file, '.mp4'))
                <video src="{{ asset('storage/' . $file) }}" loop muted playsinline autoplay aria-hidden="true"></video>
            @elseif ($file)
                <img
                    srcset="{{ asset_resized('900', $file) }} 900w, {{ asset_resized('1200', $file) }} 1200w, {{ asset_resized('1600', $file) }} 1600w, {{ asset_resized('1920', $file) }} 1920w, {{ asset_resized('2560', $file) }} 2560w"
                    sizes="100vw"
                    src="{{ asset_resized('1600', $file) }}"
                    alt=""
                    @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                    decoding="async">
            @endif
            <div class="main-width">
                <div class="slide-content article @isset($section->white_text) white @endisset">
                    @isset($section->head)
                        @if ($loop->first)
                            <h1 class="head">{{ $section->head }}</h1>
                        @else
                            <p class="head">{{ $section->head }}</p>
                        @endif
                    @endisset
                    {!! $section->body ?? '' !!}
                </div>
            </div>
        </div>
@if ($section->_last)
        <span class="slider-dots" role="tablist" aria-label="{{ __('Slides') }}"></span>
    </section>
@endif
