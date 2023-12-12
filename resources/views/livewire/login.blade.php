<main class="container login">
    <article class="grid">
        <div>
            @include('leap::logo')

            <form wire:submit="submit" class="form" novalidate>
                @foreach (config('leap.credentials') as $column)
                    <label>
                        @lang($column)
                        @error($column)
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                        <input size="30"
                            @error($column) aria-errormessage="{{ $message }}" aria-invalid="true" @elseif ($column != 'password' && $$column) aria-invalid="false" @enderror
                            aria-label="@lang($column)"
                            type="{{ $column == 'password' ? 'password' : ($column == 'email' ? 'email' : 'text') }}"
                            wire:model.live="{{ $column }}"{{ $loop->iteration == 1 ? ' autofocus autocomplete=username' : '' }}{{ $column == 'password' ? ' autocomplete=current-password' : '' }}>
                    </label>
                @endforeach

                <fieldset>
                    <label>
                        <input type="checkbox" role="switch" wire:model="remember" aria-label="@lang('remember_me')">@lang('remember_me')
                    </label>
                </fieldset>
                <button type="submit">@svg('fas-sign-in-alt', 'button-svg') @lang('login')</button>
            </form>
        </div>
        @if (config('leap.login_image'))
            <div class="login-image"><img src="{{ config('leap.login_image') }}" alt=""></div>
        @endif
    </article>
</main>
