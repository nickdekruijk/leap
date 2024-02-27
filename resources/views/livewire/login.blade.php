<main class="leap-login">
    <dialog class="leap-login-dialog" open>
        <div>
            @include('leap::logo')

            <form wire:submit="submit" class="leap-form" novalidate>
                <fieldset class="leap-fieldset">
                    @foreach (config('leap.credentials') as $column)
                        <x-leap::input name="{{ $column }}" wire="blur" label="{{ $column }}"
                            type="{{ $column == 'password' ? 'password' : ($column == 'email' ? 'email' : 'text') }}"
                            autofocus="{{ $loop->first ? 'true' : 'false' }}"
                            autocomplete="{{ $column == 'password' ? 'current-password' : ($loop->first ? 'username' : '') }}" />
                    @endforeach

                    <x-leap::switch name="remember" wire="lazy" label="remember_me" />
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
