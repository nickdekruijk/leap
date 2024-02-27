@props(['name', 'wire', 'label'])
<label>
    @error($name)
        <span class="form-error">{{ $message }}</span>
    @enderror
    <input type="checkbox" role="switch" 
        @error($name) aria-errormessage="{{ $message }}" aria-invalid="true" @elseif (isset($$name) && $name != 'password') aria-invalid="false" @enderror
        id="{{ $name }}"
        wire:model{{ isset($wire) ? '.' . $wire : '' }}="{{ $name }}"
        aria-label="@lang($label)"
        {{ $attributes }}>@lang($label)
</label>
