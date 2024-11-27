<main class="leap-login">
    <dialog class="leap-login-dialog" open>
        <div>
            @include('leap::logo')

            <form wire:submit="submit" class="leap-form" novalidate>
                <fieldset class="leap-fieldset">
                    @foreach (config('leap.credentials') as $column)
                        <x-leap::input
                            :wire:model.blur="$column"
                            :name="$column"
                            :type="$column == 'password' ? 'password' : ($column == 'email' ? 'email' : 'text')"
                            :autocomplete="$column == 'password' ? 'current-password' : ($loop->first ? 'username' : '')"
                            :autofocus="$loop->first ? 'true' : 'false'" />
                    @endforeach

                    <x-leap::input type="checkbox" role="switch" name="remember" wire:model.blur="remember" label="remember_me" />
                </fieldset>
                <fieldset class="leap-fieldset">
                    <x-leap::button type="submit" svg-icon="fas-sign-in-alt" class="primary" label="login" />
                </fieldset>
            </form>
        </div>
        @if (config('leap.login_image'))
            <div class="login-image"><img src="{{ config('leap.login_image') }}" alt=""></div>
        @endif
    </dialog>
</main>
