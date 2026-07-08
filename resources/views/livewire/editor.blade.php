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
                @if (count($this->editorLocales()) > 2)
                    <select class="leap-select leap-locale-select" wire:model.live="activeLocale" aria-label="{{ __('leap::resource.language') }}">
                        @foreach ($this->editorLocales() as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @else
                    <div class="leap-locale-tabs" role="tablist" aria-label="{{ __('leap::resource.language') }}">
                        @foreach ($this->editorLocales() as $code => $name)
                            <button type="button" role="tab" aria-selected="{{ $activeLocale === $code ? 'true' : 'false' }}" @class(['leap-button', 'primary' => $activeLocale === $code]) wire:click="$set('activeLocale', '{{ $code }}')">{{ $name }}</button>
                        @endforeach
                    </div>
                @endif
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
    @endif
</div>
