<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ Leap::htmlTitle() }}</title>
        {!! \NickDeKruijk\Leap\Controllers\AssetController::cssLink() !!}
        <script src="https://cdn.jsdelivr.net/npm/@marcreichel/alpine-autosize@latest/dist/alpine-autosize.min.js" defer></script>

    </head>

    <body>
        <div class="leap">
            @auth(config('leap.guard'))
                @if (!NickDeKruijk\Leap\Controllers\Auth2FAController::mustValidate())
                    @livewire('leap.navigation')
                @endif
            @endauth
            @livewire('leap.toasts')
            {{ $slot }}
        </div>
    </body>

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>

    <script>
        Livewire.hook('request', ({
            fail
        }) => {
            fail(({
                status,
                preventDefault
            }) => {
                if (status === 419) {
                    preventDefault();
                    if (confirm('@lang('leap::auth.page_expired')')) {
                        window.location.reload();
                    }
                }
            })
        })
    </script>

</html>
