<div class="modal">
    <div class="popup">
        @include('leap::logo')
        <form wire:submit="submit" class="form form-dark">
            @foreach(config('leap.credentials') as $column)
                <label>
                    @lang($column)
                    <input class="input" size="30" type="{{ $column == 'password' ? 'password' : ($column == 'email' ? 'email' : 'text') }}" wire:model="{{ $column }}"{{ $loop->iteration == 1 ? ' autofocus autocomplete=username' : '' }}{{ $column == 'password' ? ' autocomplete=current-password' : '' }}>
                </label>
            @endforeach
            @if ($errors->any())
                <div class="form-errors">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <button type="submit">@svg('fas-sign-in-alt', 'button-login-svg') @lang('login')</button>
        </form>
    </div>
</div>
