<div class="leap-editor">
    @if ($editing)
        <div class="leap-buttons" role="group">
            @can('leap::update')
                <x-leap::button svg-icon="far-check-circle" wire:click="save" label="save" wire:loading.delay.shorter.attr="disabled" class="primary" type="submit" />
            @endcan
            @if ($editing > 0)
                @can('leap::create')
                    <x-leap::button svg-icon="far-copy" wire:click="clone" label="save-copy" wire:loading.delay.shorter.attr="disabled" />
                @endcan
                @can('leap::delete')
                    <x-leap::button svg-icon="far-trash-alt" wire:click="delete" wire:confirm="{{ __('delete_confirm') }}" label="delete" wire:loading.delay.shorter.attr="disabled" class="secondary" />
                @endcan
            @endif
            <x-leap::button svg-icon="fas-xmark" x-on:click="selectedRow=false" wire:click="close" label="cancel" />
        </div>
        <form class="leap-form" wire:submit="submit">
            <fieldset class="leap-fieldset">
                @foreach ($this->attributes() as $attribute)
                    <x-leap::input :attribute="$attribute" :placeholder="$placeholder" />
                @endforeach
            </fieldset>
        </form>
    @endif
</div>
