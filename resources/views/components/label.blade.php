@aware(['attribute', 'name', 'label'])
<label class="leap-label">
    @if ($attribute->label ?? ($label ?? $name))
        <span class="leap-label">{!! $attribute->label ?? ($label ?? $name) !!}</span>
    @endif
    @error($attribute->dataName ?? $name)
        <span class="leap-error">{{ $message }}</span>
    @enderror
    {{ $slot }}
</label>
