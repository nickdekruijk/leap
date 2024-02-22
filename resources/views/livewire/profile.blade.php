<div>
    <header><h2>{{ $this->getTitle() }}</h2></header>
    <article>
        <div role="group">
            <x-leap::button svg-icon="far-save"  wire:click="submit" label="{{ __('save') }}" type="submit" />
            <x-leap::button svg-icon="fas-xmark" wire:click="cancel" label="{{ __('cancel') }}" class="secondary" />
        </div>
        <form class="form" wire:submit="submit">
            <x-leap::input wire="blur" name="data.name" label="{{ __('name') }}" type="text" autocomplete="name" />
            <x-leap::input wire="blur" name="data.email" label="{{ __('email') }}" type="email" disabled />
            <x-leap::input wire="blur" name="data.password_current" label="{{ __('password_current') }}" type="password" autocomplete="current-password" />
            <x-leap::input wire="blur" name="data.password_new" label="{{ __('password_new') }}" type="password" autocomplete="new-password" />
            <x-leap::input wire="blur" name="data.password_new_confirmation" label="{{ __('password_new_confirmation') }}" type="password" autocomplete="new-password" />
        </form>
    </article>
</div>
