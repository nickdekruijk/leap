<main class="leap-main leap-profile">
    <header class="leap-header">
        <h2>{{ $title }}</h2>
    </header>
    <div class="leap-editor">
        <div class="leap-buttons" role="group">
            @can('leap::update')
                <x-leap::button svg-icon="far-save" wire:click="submit" label="leap::resource.save" wire:loading.delay.shorter.attr="disabled" class="primary" type="submit" />
            @endcan
            <x-leap::button svg-icon="fas-xmark" href="{{ route('leap.home', Context::getHidden('leap.organization')?->slug) }}" label="leap::resource.cancel" />
        </div>
        <form class="leap-form" wire:submit="submit">
            <fieldset class="leap-fieldset">
                <h3>@lang('leap::auth.profile_edit')</h3>
                <x-leap::input wire:model.blur="data.name" name="data.name" label="leap::auth.name" autocomplete="name" />
                <x-leap::input wire:model.blur="data.email" name="data.email" label="leap::auth.email" type="email" disabled />
            </fieldset>
        </form>
        <form class="leap-form" wire:submit="submit">
            <fieldset class="leap-fieldset">
                <h3>@lang('leap::auth.update_password')</h3>
                <x-leap::input wire:model.blur="data.password_current" name="data.password_current" label="leap::auth.password_current" type="password" autocomplete="current-password" />
                <x-leap::input wire:model.blur="data.password_new" name="data.password_new" label="leap::auth.password_new" type="password" autocomplete="new-password" />
                <x-leap::input wire:model.blur="data.password_new_confirmation" name="data.password_new_confirmation" label="leap::auth.password_new_confirmation" type="password" autocomplete="new-password" />
            </fieldset>
        </form>
    </div>
</main>
