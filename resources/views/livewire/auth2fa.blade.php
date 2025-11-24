<main class="leap-login leap-login-2fa">
    <dialog class="leap-login-dialog" open>
        <div>
            @include('leap::logo')

            <form wire:submit="submit" class="leap-form" novalidate>
                @if ($message)
                    <div class="form-message">
                        {!! $message !!}
                    </div>
                @endif

                <fieldset class="leap-fieldset">
                    <x-leap::input wire:model.blur="code" label="{{ __('leap::auth.verification_code') }}" autofocus autocomplete="one-time-code" size="{{ config('leap.auth_2fa.mail.code.length') }}" />
                </fieldset>
                <fieldset class="leap-fieldset leap-fieldset-buttons">
                    <x-leap::button type="submit" svg-icon="fas-sign-in-alt" class="primary" label="{{ __('leap::auth.login') }}" />
                    <x-leap::button wire:click="logout" svg-icon="fas-sign-out-alt" label="{{ __('leap::auth.logout') }}" />
                </fieldset>
            </form>
        </div>
    </dialog>
</main>
