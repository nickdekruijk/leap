<div>
    @if ($editing)
        <div class="leap-buttons" role="group" x-on:keydown.escape.window="if (!document.querySelector('.leap-filebrowser')) selectedRow=null">
            @can('leap::update')
                <x-leap::button svg-icon="far-check-circle" wire:click="save" label="leap::resource.save" wire:loading.delay.shorter.attr="disabled" class="primary" type="submit" />
            @endcan
            @if ($editing > 0)
                @can('leap::create')
                    <x-leap::button svg-icon="far-copy" wire:click="clone" label="leap::resource.save_copy" wire:loading.delay.shorter.attr="disabled" />
                @endcan
            @endif
            @if ($this->editorLocales())
                @if (count($this->editorLocales()) > 3)
                    <select class="leap-select leap-locale-select" wire:model.live="activeLocale" aria-label="{{ __('leap::resource.language') }}">
                        @foreach ($this->editorLocales() as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @else
                    <div class="leap-locale-tabs" role="tablist" aria-label="{{ __('leap::resource.language') }}">
                        @foreach ($this->editorLocales() as $code => $name)
                            <button type="button" role="tab" aria-selected="{{ $activeLocale === $code ? 'true' : 'false' }}" title="{{ $name }}" @class(['leap-button', 'active' => $activeLocale === $code]) wire:click="$set('activeLocale', '{{ $code }}')">{{ strtoupper($code) }}</button>
                        @endforeach
                    </div>
                @endif
            @endif
            @if ($this->editorLocales() && $this->aiTranslateEnabled())
                @php($translateFrom = collect(array_keys($this->editorLocales()))->first(fn ($c) => $c !== $activeLocale) ?? $this->defaultLocale())
                <div class="leap-translate-all" x-data="{ open: false, from: '{{ $translateFrom }}', scope: 'empty', busy: false }">
                    <x-leap::button svg-icon="fas-language" label="leap::resource.translate"
                        x-on:click="const l = {{ Js::from(array_keys($this->editorLocales())) }}; from = l.find(c => c !== $wire.activeLocale) || l[0]; open = true" />
                    <x-leap::modal show="open" close="open = false" teleport title="{{ __('leap::resource.translate') }}">
                        <div class="leap-modal-field">
                            <label>@lang('leap::resource.translate_from')</label>
                            <select class="leap-select" x-model="from">
                                @foreach ($this->editorLocales() as $code => $name)
                                    <option value="{{ $code }}" x-bind:disabled="'{{ $code }}' === '{{ $activeLocale }}'">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="leap-modal-field">
                            <label class="leap-translate-scope"><input type="radio" value="empty" x-model="scope"> @lang('leap::resource.translate_empty')</label>
                            <label class="leap-translate-scope"><input type="radio" value="all" x-model="scope"> @lang('leap::resource.translate_all')</label>
                        </div>
                        <div class="leap-modal-actions">
                            <button type="button" class="leap-modal-btn leap-modal-save" :class="{ 'leap-alt-generating': busy }" :disabled="busy || from === '{{ $activeLocale }}'"
                                x-on:click="busy = true; $wire.translateAll(from, scope === 'empty').then(() => open = false).finally(() => busy = false)">
                                @svg('fas-language', 'svg-icon') @lang('leap::resource.translate')
                            </button>
                            <button type="button" class="leap-modal-btn" x-on:click="open = false">@lang('leap::resource.cancel')</button>
                        </div>
                    </x-leap::modal>
                </div>
            @endif
            @if ($editing > 0)
                @can('leap::delete')
                    <x-leap::button svg-icon="far-trash-alt" wire:click="delete" wire:confirm="{{ __('leap::resource.delete_confirm') }}" label="leap::resource.delete" wire:loading.delay.shorter.attr="disabled" class="secondary" />
                @endcan
            @endif
            @if ($this->parentModule()->editorButtons() && $editing > 0)
                @foreach ($this->parentModule()->editorButtons() as $button)
                    @isset($button['livewire'])
                        @livewire($button['livewire'], ['button' => $button, 'resource' => $this->parentModule(), 'editing' => $editing], key('editor-button-' . $button['livewire'] . '-' . $editing))
                    @endisset
                @endforeach
            @endif
            <x-leap::button svg-icon="fas-xmark" x-on:click="selectedRow=null" wire:click="close" label="leap::resource.cancel" />
            <span class="leap-editing-id">#{{ $editing }}</span>
        </div>
        <div class="leap-form" wire:key="editor-{{ $editing }}">
            <fieldset class="leap-fieldset">
                @foreach ($this->attributes() as $attribute)
                    @if ($attribute->input)
                        @php($isTranslatable = is_array($data[$attribute->name] ?? null))
                        <x-dynamic-component :component="'leap::' . $attribute->input" :attribute="$attribute" :placeholder="$placeholder" :value="$isTranslatable ? ($data[$attribute->name][$activeLocale] ?? null) : ($data[$attribute->name] ?? null)" wire:key="attr-{{ $attribute->name }}{{ $isTranslatable ? '-' . $activeLocale : '' }}" />
                        @if ($attribute->confirmed)
                            <x-dynamic-component :component="'leap::' . $attribute->input" :attribute="$attribute->confirmedAttribute()" :placeholder="$placeholder" />
                        @endif
                    @endif
                @endforeach
            </fieldset>
        </div>
        @if ($this->aiImageEnabled())
            <x-leap::ai-image scope="editor" />
        @endif
    @endif
</div>
