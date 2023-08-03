<div class="login">
    <div class="login-popup">
        @include('leap::logo')
        <form wire:submit="submit" class="form">
            @if ($message)
                <div class="form-message">
                    {!! $message !!}
                </div>
            @endif
            <label>
                @lang('verification_code')
                <input class="input" size="30" type="text" name="code" wire:model="code" autofocus autocomplete="one-time-code">
            </label>
            @if ($errors->any())
                <div class="form-errors">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <button type="submit">@svg('fas-sign-in-alt', 'svg') @lang('login')</button>
            <button wire:click="logout">@svg('fas-sign-out-alt', 'svg')@lang('logout')</button>
        </form>
    </div>
</div>
