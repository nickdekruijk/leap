<main>
    <header>
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <div class="buttons" role="group">
        <x-leap::button svg-icon="far-save"  wire:click="submit" label="save" type="submit" />
        <x-leap::button svg-icon="fas-xmark" wire:click="cancel" label="cancel" class="secondary" />
    </div>
    <article>
        <form class="form" wire:submit="submit">
            <x-leap::input wire="blur" name="data.name" label="name" type="text" autocomplete="name" />
            <x-leap::input wire="blur" name="data.email" label="email" type="email" disabled />
            <x-leap::input wire="blur" name="data.password_current" label="password_current" type="password" autocomplete="current-password" />
            <x-leap::input wire="blur" name="data.password_new" label="password_new" type="password" autocomplete="new-password" />
            <x-leap::input wire="blur" name="data.password_new_confirmation" label="password_new_confirmation" type="password" autocomplete="new-password" />
        </form>
    </article>
</main>
