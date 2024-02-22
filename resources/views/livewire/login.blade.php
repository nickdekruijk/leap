<main class="container login">
    <article class="grid">
        <div>
            @include('leap::logo')

            <form wire:submit="submit" class="form" novalidate>
                <fieldset>
                    @foreach (config('leap.credentials') as $column)
                        <x-leap::input 
                            name="{{ $column }}"
                            wire="blur"
                            label="{{ __($column) }}"
                            type="{{ $column == 'password' ? 'password' : ($column == 'email' ? 'email' : 'text') }}" 
                            autofocus="{{ $loop->first ? 'true' : 'false' }}" 
                            autocomplete="{{ $column=='password' ? 'current-password' : ($loop->first ? 'username' : '') }}"
                        />
                    @endforeach

                    <x-leap::toggle name="remember" wire="lazy" label="{{ __('remember_me') }}" />
                </fieldset>
                <x-leap::button type="submit" svg-icon="fas-sign-in-alt" label="{{ __('login') }}" />
            </form>
        </div>
        @if (config('leap.login_image'))
            <div class="login-image"><img src="{{ config('leap.login_image') }}" alt=""></div>
        @endif
    </article>
</main>
