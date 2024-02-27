@props(['name', 'wire', 'label'])
<label>
    @lang($label)
    @error($name)
        <span class="form-error">{{ $message }}</span>
    @enderror
    <input size="30"
        @error($name) aria-errormessage="{{ $message }}" aria-invalid="true" @elseif (isset($$name) && $name != 'password') aria-invalid="false" @enderror
        id="{{ $name }}"
        aria-label="@lang($label)"
        wire:model{{ isset($wire) ? '.' . $wire : '' }}="{{ $name }}"
        {{ $attributes }}>
</label>
