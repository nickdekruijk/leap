@aware(['attribute', 'name', 'label'])
@props(['tag' => 'label'])
{{-- showIf() hides the field itself, not a wrapper around it: the fieldset lays out its
     own children, so anything in between put a hidden field's row back in the flow. --}}
{{-- tag="div" for a field whose slot holds only action buttons and no form control.
     A <label> without a for attribute adopts its first labelable descendant — and a
     <button> is labelable — so hovering anywhere on the label, including a second
     button beside it, lights up the first one as if the pointer were on it. --}}
<{{ $tag }} class="leap-label" @if ($attribute->showIfExpression ?? false) x-show="{{ $attribute->showIfExpression }}" @endif>
    @if ($attribute->label ?? ($label ?? $name))
        <span class="leap-label">
            {!! $attribute->label ?? ($label ?? $name) !!}
            @if (($attribute->translatable ?? false) && config('leap.locales'))
                @php($otherLocales = array_diff_key(config('leap.locales'), [$attribute->currentLocale => true]))
                @if ($this->aiTranslateEnabled() && $otherLocales)
                    <span class="leap-translatable leap-hint leap-translate" x-data="{ open: false }" x-on:click.outside="open = false" role="button" tabindex="0"
                        x-on:click.stop.prevent="open = !open" wire:loading.class="leap-translating" wire:target="translateField">
                        {{ strtoupper($attribute->currentLocale ?? array_key_first(config('leap.locales') ?? [])) }}
                        <span class="leap-translate-menu" x-show="open" x-transition x-cloak x-on:click.stop>
                            <span class="leap-translate-menu-title">@lang('leap::resource.translate_from')</span>
                            @foreach ($otherLocales as $code => $name)
                                <button type="button" wire:click="translateField('{{ $attribute->dataName }}', '{{ $code }}')" wire:loading.attr="disabled" wire:target="translateField" x-on:click="open = false">{{ $name }}</button>
                            @endforeach
                        </span>
                    </span>
                @else
                    <span class="leap-translatable leap-hint" tabindex="0" role="note" aria-label="{{ __('leap::resource.translatable') }}">
                        {{ strtoupper($attribute->currentLocale ?? array_key_first(config('leap.locales') ?? [])) }}
                        <span class="leap-hint-tooltip">{{ __('leap::resource.translatable') }}</span>
                    </span>
                @endif
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
</{{ $tag }}>
