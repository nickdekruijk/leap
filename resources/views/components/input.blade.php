@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label>
    <input class="leap-input"
        @if (($attribute->type ?? false) == 'password') autocomplete="new-password" @endif
        @if ($attribute->isSlug ?? false)
            {{-- Shape a slug while it is typed, so the correction is something you watch
                 happen rather than something that turns up later. On input, not keydown:
                 pasting one out of a mail or off the old site is the ordinary case.
                 The rule in Editor::SLUG_FORMAT is what actually enforces this; anything
                 in the browser is a convenience a paste through devtools walks around. --}}
            x-data="{
                clean(value) {
                    return value === '/' ? value : value.toLowerCase()
                        .replace(/[\s_]+/g, '-')
                        .replace(/[^\p{Ll}\p{N}\/-]/gu, '')
                        .replace(/-{2,}/g, '-');
                },
            }"
            x-on:input="
                const caret = clean($el.value.slice(0, $el.selectionStart)).length;
                const cleaned = clean($el.value);
                if (cleaned !== $el.value) {
                    $el.value = cleaned;
                    $el.setSelectionRange(caret, caret);
                    $el.dispatchEvent(new Event('input'));
                }
            "
            {{-- Trimmed when you leave, not while you type: a hyphen at the end is
                 usually a word you have not finished, and eating it as you type makes
                 "onze-tarieven" impossible to write. A pasted value settles here. --}}
            x-on:blur="
                const trimmed = $el.value.replace(/^-+|-+$/g, '');
                if (trimmed !== $el.value) {
                    $el.value = trimmed;
                    $el.dispatchEvent(new Event('input'));
                }
            "
        @endif
        @error($attribute->dataName ?? ($name ?? '')) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
        @if ($attribute->disabled ?? false) disabled @endif
        @isset($attribute)
            placeholder="{{ $placeholder[$attribute->name] ?? $attribute->placeholder }}"
            wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}"
            {{ $attribute->inputAttributes() }}
        @endisset
        aria-label="@lang($attribute->label ?? ($label ?? ($name ?? '')))"
        {{ $attributes }}>
</x-leap::label>
