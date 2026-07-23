<x-leap::auth-card class="leap-forgot-password" :status="$status">
    <form wire:submit="submit" class="leap-form" novalidate>
        <fieldset class="leap-fieldset">
            <p>{{ __('leap::auth.password_reset_intro') }}</p>
            <x-leap::input type="email" wire:model.blur="email" name="email" label="{{ __('leap::auth.email') }}" autocomplete="username" autofocus />
        </fieldset>
        <fieldset class="leap-fieldset leap-fieldset-buttons">
            <x-leap::button type="submit" svg-icon="fas-paper-plane" class="primary" label="{{ __('leap::auth.password_reset_send') }}" />
            <a href="{{ route('leap.login') }}" wire:navigate>{{ __('leap::auth.back_to_login') }}</a>
        </fieldset>
    </form>
</x-leap::auth-card>
