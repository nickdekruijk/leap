@props(['name', 'wire', 'label' => null, 'class' => ''])
<label class="leap-label">
    @if ($label)
        <span class="leap-label">@lang($label)</span>
    @endif
    @error($name)
        <span class="leap-error">{{ $message }}</span>
    @enderror
    <input size="30" class="leap-input {{ $class }}"
        @error($name) aria-errormessage="{{ $message }}" aria-invalid="true" @elseif (isset($$name) && $name != 'password') aria-invalid="false" @enderror
        id="{{ $name }}"
        @auth(config('leap.guard'))
            @if (Gate::denies('leap::create') && Gate::denies('leap::update')) disabled @endif
        @endauth
        aria-label="@lang($label ?: $placeholder ?? '')"
        wire:model{{ isset($wire) ? '.' . $wire : '' }}="{{ $name }}"
        {{ $attributes }}>
</label>
