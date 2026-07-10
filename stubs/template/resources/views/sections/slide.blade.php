@if ($section->_first)
    <section class="slider" aria-label="{{ __('Slider') }}" id="slider-{{ $loop->iteration }}">
@endif
        @php($image = ($section['image'] ?? null)?->first())
        <div class="slide @if (! $image) slide-placeholder @endif" role="group" aria-roledescription="{{ __('slide') }}" aria-label="{{ __('Slide :n', ['n' => $loop->iteration]) }}">
            @if ($image && str_ends_with($image->file_name, '.mp4'))
                <video src="{{ asset('storage/' . $image->file_name) }}" loop muted playsinline autoplay aria-hidden="true"></video>
            @elseif ($image)
                <x-responsive-image :media="$image" sizes="100vw" :widths="[900, 1200, 1600, 1920, 2560]" fallback="1600" :eager="$loop->first" decorative />
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
