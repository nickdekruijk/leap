<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ Leap::htmlTitle() }}</title>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=open-sans:300,300i,400,400i,500,500i,600,600i,700,700i,800,800i">
        {!! \NickDeKruijk\Leap\Controllers\AssetController::cssLink() !!}
        {!! \NickDeKruijk\Leap\Controllers\AssetController::tinymceContentCssLink() !!}
        @if (config('leap.auth_passkeys.enabled'))
            <script src="{{ route('leap.js') }}?{{ \NickDeKruijk\Leap\Controllers\AssetController::jsFilemtime() }}" defer></script>
        @endif
        <script src="https://cdn.jsdelivr.net/npm/@marcreichel/alpine-autosize@latest/dist/alpine-autosize.min.js" defer></script>

    </head>

    <body>
        <div class="leap">
            @auth(config('leap.guard'))
                @if (!NickDeKruijk\Leap\Leap::mustValidateTwoFactor())
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
