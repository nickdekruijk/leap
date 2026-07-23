<x-leap::auth-card class="leap-login-2fa">
    @if ($this->method === 'passkey')
        <div class="leap-form">
            <fieldset class="leap-fieldset">
                <small class="leap-hint">{{ __('leap::auth.two_factor_passkey_hint') }}</small>
            </fieldset>
            <fieldset class="leap-fieldset leap-fieldset-buttons">
                <x-leap::button type="button" svg-icon="fas-key" class="primary" label="{{ __('leap::auth.two_factor_passkey_verify') }}" onclick="leapPasskeyConfirm()" />
                <x-leap::button wire:click="logout" svg-icon="fas-sign-out-alt" label="{{ __('leap::auth.logout') }}" />
            </fieldset>
        </div>
    @else
        <form wire:submit="submit" class="leap-form" novalidate>
            @if ($message)
                <div class="form-message">
                    {{ $message }}
                </div>
            @endif

            <fieldset class="leap-fieldset">
                <x-leap::input wire:model.blur="code" label="{{ __('leap::auth.verification_code') }}" autofocus autocomplete="one-time-code" inputmode="text" />
                <small class="leap-hint">{{ $this->method === 'email' ? __('leap::auth.two_factor_email_hint') : __('leap::auth.two_factor_hint') }}</small>
            </fieldset>
            <fieldset class="leap-fieldset leap-fieldset-buttons">
                <x-leap::button type="submit" svg-icon="fas-sign-in-alt" class="primary" label="{{ __('leap::auth.login') }}" />
                @if ($this->method === 'email')
                    <x-leap::button type="button" wire:click="resend" svg-icon="fas-rotate" label="{{ __('leap::auth.two_factor_email_resend') }}" />
                @endif
                <x-leap::button wire:click="logout" svg-icon="fas-sign-out-alt" label="{{ __('leap::auth.logout') }}" />
            </fieldset>
        </form>
    @endif
</x-leap::auth-card>
