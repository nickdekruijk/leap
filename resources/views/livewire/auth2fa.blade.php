<main class="container login login-2fa">
    <article class="grid">
        <div>
            @include('leap::logo')

            <form wire:submit="submit" class="form" novalidate>
                @if ($message)
                    <div class="form-message">
                        {!! $message !!}
                    </div>
                @endif

                <label>
                    @lang('verification_code')
                    @error('code')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <input size="{{ config('leap.auth_2fa.mail.code.length') }}" 
                        @error('code') aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
                        aria-label="@lang('verification_code')"
                        type="text" name="code" wire:model.live="code" autofocus autocomplete="one-time-code">
                </label>

                <button type="submit">@svg('fas-sign-in-alt', 'button-svg') @lang('login')</button>
                <button wire:click="logout">@svg('fas-sign-out-alt', 'button-svg') @lang('logout')</button>
            </form>
        </div>
    </article>
</main>
