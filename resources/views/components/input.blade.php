@props(['name', 'wire', 'label'])
<label>
    {{ $label ?? $name }}
    @error($name)
        <span class="form-error">{{ $message }}</span>
    @enderror
    <input size="30"
        @error($name) aria-errormessage="{{ $message }}" aria-invalid="true" @elseif (isset($$name) && $name != 'password') aria-invalid="false" @enderror
        aria-label="{{ $label ?? $name }}"
        wire:model{{ isset($wire) ? '.' . $wire : '' }}="{{ $name }}"
        {{ $attributes }}>
</label>
