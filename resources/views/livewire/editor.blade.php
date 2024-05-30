<div class="leap-editor {{ $editing ? 'leap-editor-open' : 'leap-editor-closed' }}">
    <div class="leap-buttons" role="group">
        @can('leap::update')
            <x-leap::button svg-icon="far-save" wire:click="submit" label="save" wire:loading.delay.shorter.attr="disabled" class="primary" type="submit" />
        @endcan
        @can('leap::create')
            <x-leap::button svg-icon="far-copy" wire:click="clone" label="save-copy" wire:loading.delay.shorter.attr="disabled" />
        @endcan
        @can('leap::delete')
            <x-leap::button svg-icon="far-trash-alt" wire:click="delete" label="delete" wire:loading.delay.shorter.attr="disabled" class="secondary" />
        @endcan
        <x-leap::button svg-icon="fas-xmark" x-on:click="active=false" wire:click="close()" label="cancel" />
    </div>
    <form class="leap-form" wire:submit="submit">
        <fieldset class="leap-fieldset">
            {{ Context::get('leap.module') }}: {{ $editing }}
        </fieldset>
    </form>
</div>
