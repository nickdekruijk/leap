@aware(['attribute', 'name', 'label'])
<label class="leap-label">
    @if ($attribute->label ?? ($label ?? $name))
        <span class="leap-label">
            {!! $attribute->label ?? ($label ?? $name) !!}
            @if (($attribute->translatable ?? false) && config('leap.locales'))
                <span class="leap-translatable" title="{{ __('leap::resource.translatable') }}" aria-label="{{ __('leap::resource.translatable') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="13" height="13" fill="currentColor" aria-hidden="true">
                        <path d="M8 0a8 8 0 100 16A8 8 0 008 0zM6.9 14.4A6.5 6.5 0 011.6 9h2.5c.1 1.9.6 3.7 1.3 5.1zm-.5-6.9H3.9c.1-1.3.4-2.5.8-3.6a6.5 6.5 0 002.3.7c-.3 1-.5 2-.6 2.9zM8 14.5c-.9 0-1.9-1.9-2.1-5H10c-.2 3.1-1.2 5-2 5zm0-12.9c.8 0 1.7 1.6 2 4.4H6c.3-2.8 1.2-4.4 2-4.4zm1.6 5.9c-.1-1-.3-1.9-.5-2.7.8-.1 1.5-.4 2.2-.7.4 1 .7 2.2.8 3.4H9.6zm.6-4.6a5 5 0 01-1.4.4c-.2-.9-.5-1.6-.8-2.2 1 .3 1.7 1 2.2 1.8zM6 1.5c-.3.6-.6 1.3-.8 2.2a5 5 0 01-1.4-.4c.5-.8 1.2-1.5 2.2-1.8zM3 4.2c.7.3 1.4.6 2.2.7-.2.8-.4 1.7-.5 2.6H2.2c.1-1.2.4-2.3.8-3.3zm-.8 4.8h2.5c.1 1 .3 1.9.5 2.8-.8.2-1.5.4-2.2.7A6.5 6.5 0 012.2 9zm7.9 5.4c.7-1.4 1.2-3.2 1.3-5.1h2.5a6.5 6.5 0 01-3.8 5.1zM11.8 7.5c-.1-.9-.3-1.8-.5-2.6.8-.1 1.5-.4 2.2-.7.4 1 .7 2.1.8 3.3h-2.5z" />
                    </svg>
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
