<main class="leap-main leap-profile">
    <header class="leap-header">
        <h2>{{ $title }}</h2>
    </header>
    <div class="leap-editor">
        <div class="leap-buttons" role="group">
            @can('leap::update')
                <x-leap::button svg-icon="far-save" wire:click="submit" label="save" wire:loading.delay.shorter.attr="disabled" class="primary" type="submit" />
            @endcan
            <x-leap::button svg-icon="fas-xmark" href="{{ route('leap.home', Context::get('leap.organization')?->slug) }}" label="cancel" />
        </div>
        <form class="leap-form" wire:submit="submit">
            <fieldset class="leap-fieldset">
                <h3>@lang('profile_edit')</h3>
                <x-leap::input wire="blur" name="data.name" label="name" type="text" autocomplete="name" />
                <x-leap::input wire="blur" name="data.email" label="email" type="email" disabled />
            </fieldset>
        </form>
        <form class="leap-form" wire:submit="submit">
            <fieldset class="leap-fieldset">
                <h3>@lang('update_password')</h3>
                <x-leap::input wire="blur" name="data.password_current" label="password_current" type="password" autocomplete="current-password" />
                <x-leap::input wire="blur" name="data.password_new" label="password_new" type="password" autocomplete="new-password" />
                <x-leap::input wire="blur" name="data.password_new_confirmation" label="password_new_confirmation" type="password" autocomplete="new-password" />
            </fieldset>
        </form>
    </div>
</main>
