@props(['attribute', 'placeholder', 'name', 'value'])

<x-leap::label>
    @if (is_array($value))
        @foreach ($value as $key => $value)
            <label class="leap-label">
                <span class="leap-label">{{ ucfirst(strtolower(Str::headline($key))) }}</span>
                <table class="leap-json-readonly leap-textarea">
                    @include('leap::components.json-value')
                </table>
            </label>
        @endforeach
    @else
        {{-- Escape before nl2br: JSON columns hold user-submitted data (e.g. form submissions) --}}
        <div disabled class="leap-textarea">{!! nl2br(e($value)) !!}</div>
    @endif
</x-leap::label>
