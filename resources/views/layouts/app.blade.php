<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Leap</title>
        {!! \NickDeKruijk\Leap\Controllers\AssetController::cssLink() !!}
    </head>
    <body>
        <div class="leap">
            @if (auth(config('leap.guard'))->user() && !NickDeKruijk\Leap\Controllers\Auth2FAController::mustValidate())
                @livewire('leap.navigation')
            @endif
            @livewire('leap.toasts')
            {{ $slot }}
        </div>
    </body>
</html>
