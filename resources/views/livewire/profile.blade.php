<div>
    <header><h2>{{ $this->getTitle() }}</h2></header>
    <article>
        <div class="buttons">
            <button class="button-primary" wire:click="submit">@svg('far-save', 'button-svg')@lang('save')</button>
            @if ($errors->any())
                <span class="has-errors">@lang('has-errors')</span>
            @endif
        </div>
        <form class="form" wire:submit="submit">
            {{-- @if ($errors->any())
                <div class="form-errors">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif --}}
            <label>
                @lang('name')
                @error('data.name')<span class="error">{{ $message }}</span>@enderror
                <input type="text" wire:model.blur="data.name" autocomplete="name">
                {{-- The hidden input below is to prevent bitwarden (and maybe other password managers) from replacing the name with emailaddress --}}
                <input type="text" style="position:absolute;opacity:0;transform:translateY(-100%);margin:0;width:10px;right:0" name="fakeusernameremembered">
            </label>
            <label>
                @lang('email')
                @error('data.email')<span class="error">{{ $message }}</span>@enderror
                <input type="email" wire:model.blur="data.email" autocomplete="username" disabled>
            </label>
            <label>
                @lang('password_current')
                @error('data.password_current')<span class="error">{{ $message }}</span>@enderror
                <input type="password" wire:model.blur="data.password_current" autocomplete="current-password">
            </label>
            <label>
                @lang('password_new')
                @error('data.password_new')<span class="error">{{ $message }}</span>@enderror
                <input type="password" wire:model.blur="data.password_new" autocomplete="new-password">
            </label>
            <label>
                @lang('password_new_confirmation')
                @error('data.password_new_confirmation')<span class="error">{{ $message }}</span>@enderror
                <input type="password" wire:model.blur="data.password_new_confirmation" autocomplete="new-password">
            </label>
        </form>
    </article>
</div>
