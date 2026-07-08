@aware(['attribute', 'name', 'label'])
<label class="leap-label">
    @if ($attribute->label ?? ($label ?? $name))
        <span class="leap-label">
            {!! $attribute->label ?? ($label ?? $name) !!}
            @if (($attribute->translatable ?? false) && config('leap.locales'))
                <span class="leap-translatable leap-hint" tabindex="0" role="note" aria-label="{{ __('leap::resource.translatable') }}">
                    {{ implode('/', array_map('strtoupper', array_keys(config('leap.locales') ?? []))) }}
                    <span class="leap-hint-tooltip">{{ __('leap::resource.translatable') }}</span>
                </span>
            @endif
            @if ($attribute->hint ?? false)
                <span class="leap-hint" tabindex="0" role="note" aria-label="{{ strip_tags($attribute->hint) }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor" aria-hidden="true">
                        <path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2.75A1.25 1.25 0 118 6.25 1.25 1.25 0 018 3.75zM9.5 12h-3a.5.5 0 010-1H7V8h-.5a.5.5 0 010-1H8a.5.5 0 01.5.5V11h1a.5.5 0 010 1z" />
                    </svg>
                    <span class="leap-hint-tooltip">{!! $attribute->hint !!}</span>
                </span>
            @endif
        </span>
    @endif
    @error($attribute->dataName ?? $name)
        <span class="leap-error">{{ $message }}</span>
    @enderror
    {{ $slot }}
</label>
