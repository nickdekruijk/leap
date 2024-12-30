<div class="leap-editor"
    x-on:scroll=" $el.querySelectorAll('.tox-tinymce--toolbar-sticky-on .tox-editor-header').forEach(function(el) {
        window.requestAnimationFrame(function() { 
            el.style.left = 'auto';
            el.style.top = ($el.scrollTop + $el.querySelector('.leap-buttons').offsetHeight) + 'px';
        })
    })">
    @if ($editing)
        <div class="leap-buttons" role="group" x-on:keydown.escape.window="if (!document.querySelector('.leap-filebrowser')) selectedRow=null">
            @can('leap::update')
                <x-leap::button svg-icon="far-check-circle" wire:click="save" label="leap::resource.save" wire:loading.delay.shorter.attr="disabled" class="primary" type="submit" />
            @endcan
            @if ($editing > 0)
                @can('leap::create')
                    <x-leap::button svg-icon="far-copy" wire:click="clone" label="leap::resource.save_copy" wire:loading.delay.shorter.attr="disabled" />
                @endcan
                @can('leap::delete')
                    <x-leap::button svg-icon="far-trash-alt" wire:click="delete" wire:confirm="{{ __('leap::resource.delete_confirm') }}" label="leap::resource.delete" wire:loading.delay.shorter.attr="disabled" class="secondary" />
                @endcan
            @endif
            <x-leap::button svg-icon="fas-xmark" x-on:click="selectedRow=null" wire:click="close" label="leap::resource.cancel" />
            <span class="leap-editing-id">#{{ $editing }}</span>
        </div>
        <form class="leap-form" wire:submit="submit">
            <fieldset class="leap-fieldset">
                @foreach ($this->attributes() as $attribute)
                    <x-dynamic-component :component="'leap::' . $attribute->input" :attribute="$attribute" :placeholder="$placeholder" />
                    @if ($attribute->confirmed)
                        <x-dynamic-component :component="'leap::' . $attribute->input" :attribute="$attribute->confirmedAttribute()" :placeholder="$placeholder" />
                    @endif
                @endforeach
            </fieldset>
        </form>
    @endif
</div>
