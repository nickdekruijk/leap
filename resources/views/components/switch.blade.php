@props(['name', 'wire', 'label', 'class' => ''])
<label class="leap-label">
    @error($name)
        <span class="leap-error">{{ $message }}</span>
    @enderror
    <input class="leap-input {{ $class }}" type="checkbox" role="switch" 
        @error($name) aria-errormessage="{{ $message }}" aria-invalid="true" @elseif (isset($$name) && $name != 'password') aria-invalid="false" @enderror
        id="{{ $name }}"
        @auth(config('leap.guard'))
            @if (Gate::denies('leap::create') && Gate::denies('leap::update')) disabled @endif
        @endauth
        wire:model{{ isset($wire) ? '.' . $wire : '' }}="{{ $name }}"
        aria-label="@lang($label)"
        {{ $attributes }}><span class="leap-label">@lang($label)</span>
</label>
