<main class="leap-login">
    <dialog class="leap-login-dialog" open>
        <div>
            @include('leap::logo')

            @if (session('leap.status'))
                <div class="form-message">
                    {{ session('leap.status') }}
                </div>
            @endif

            <form wire:submit="submit" class="leap-form" novalidate>
                <fieldset class="leap-fieldset">
                    @foreach (config('leap.credentials') as $column)
                        <x-leap::input
                            :wire:model.blur="$column"
                            :name="$column"
                            :label="__('leap::auth.' . $column)"
                            :type="$column == 'password' ? 'password' : ($column == 'email' ? 'email' : 'text')"
                            :autocomplete="$column == 'password' ? 'current-password' : ($loop->first ? 'username' : '')"
                            :autofocus="$loop->first ? 'true' : 'false'" />
                    @endforeach

                    <x-leap::input id="remember" type="checkbox" role="switch" name="remember" wire:model.blur="remember" label="{{ __('leap::auth.remember_me') }}" />
                </fieldset>
                <fieldset class="leap-fieldset leap-fieldset-buttons">
                    <x-leap::button type="submit" svg-icon="fas-sign-in-alt" class="primary" label="{{ __('leap::auth.login') }}" />
                    @if ($this->offerPasskeyLogin)
                        <x-leap::button type="button" svg-icon="fas-key" onclick="leapPasskeyLogin(document.getElementById('remember').checked)" label="{{ __('leap::auth.passkey_login') }}" />
                    @endif
                    @if (config('leap.auth_routes') && config('leap.password_reset'))
                        <a href="{{ route('leap.password.request') }}" wire:navigate>{{ __('leap::auth.forgot_password') }}</a>
                    @endif
                </fieldset>
            </form>
        </div>
        @if (config('leap.login_image'))
            <div class="login-image"><img src="{{ config('leap.login_image') }}" alt=""></div>
        @endif
    </dialog>
</main>
