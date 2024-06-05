@aware(['attribute'])
<label class="leap-label">
    @if ($attribute->label)
        <span class="leap-label">@lang($attribute->label)</span>
    @endif
    @error($attribute->dataName)
        <span class="leap-error">{{ $message }}</span>
    @enderror
    {{ $slot }}
</label>
