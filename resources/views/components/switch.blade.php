@props(['name', 'wire', 'label', 'class' => ''])
<label class="leap-label">
    @error($name)
        <span class="leap-error">{{ $message }}</span>
    @enderror
    <input class="leap-input {{ $class }}" type="checkbox" role="switch" 
        @error($name) aria-errormessage="{{ $message }}" aria-invalid="true" @elseif (isset($$name) && $name != 'password') aria-invalid="false" @enderror
        id="{{ $name }}"
        wire:model{{ isset($wire) ? '.' . $wire : '' }}="{{ $name }}"
        aria-label="@lang($label)"
        {{ $attributes }}>@lang($label)
</label>
