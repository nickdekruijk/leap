@aware(['attribute', 'name', 'label'])
<label class="leap-label">
    <span class="leap-label">{{ $attribute->label ?? ($label ?? $name) }}</span>
    @error($attribute->dataName ?? $name)
        <span class="leap-error">{{ $message }}</span>
    @enderror
    {{ $slot }}
</label>
