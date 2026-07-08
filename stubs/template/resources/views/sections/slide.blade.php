@if ($section->_first)
    <section class="slider" aria-label="{{ __('Slider') }}" id="slider-{{ $loop->iteration }}">
@endif
        <div class="slide" role="group" aria-roledescription="{{ __('slide') }}" aria-label="{{ __('Slide :n', ['n' => $loop->iteration]) }}">
            @isset($section->image)
                @php($file = $section->image->first()?->file_name)
                @if ($file && str_ends_with($file, '.mp4'))
                    <video src="{{ asset('storage/' . $file) }}" loop muted playsinline autoplay aria-hidden="true"></video>
                @elseif ($file)
                    <img src="{{ asset('storage/' . $file) }}" alt="">
                @endif
            @endisset
            <div class="main-width">
                <div class="slide-content article @isset($section->white_text) white @endisset">
                    @isset($section->head)
                        @if ($section->_first)
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
