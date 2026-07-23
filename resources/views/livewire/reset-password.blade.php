<x-leap::auth-card class="leap-reset-password">
    <form wire:submit="submit" class="leap-form" novalidate>
        <fieldset class="leap-fieldset">
            <x-leap::input type="email" wire:model.blur="email" name="email" label="{{ __('leap::auth.email') }}" autocomplete="username" />
            <x-leap::input type="password" wire:model.blur="password" name="password" label="{{ __('leap::auth.password_new') }}" autocomplete="new-password" autofocus />
            <x-leap::input type="password" wire:model.blur="password_confirmation" name="password_confirmation" label="{{ __('leap::auth.password_new_confirmation') }}" autocomplete="new-password" />
        </fieldset>
        <fieldset class="leap-fieldset leap-fieldset-buttons">
            <x-leap::button type="submit" svg-icon="fas-key" class="primary" label="{{ __('leap::auth.password_reset_submit') }}" />
            <a href="{{ route('leap.login') }}" wire:navigate>{{ __('leap::auth.back_to_login') }}</a>
        </fieldset>
    </form>
</x-leap::auth-card>
