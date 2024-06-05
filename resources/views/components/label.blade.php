@aware(['attribute'])
<label class="leap-label">
    <span class="leap-label">@lang($attribute->label)</span>
    @error($attribute->dataName)
        <span class="leap-error">{{ $message }}</span>
    @enderror
    {{ $slot }}
</label>
