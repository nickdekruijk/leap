<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Leap</title>
        <link rel="stylesheet" href="{{ route('leap.css') }}">
    </head>
    <body>
        @livewire('leap.navigation', ['currentModule' => $currentModule ?? null])
        @livewire('leap.toasts')
        <div class="slot">
            @isset($slot)
                {{ $slot }}
            @else
                @livewire($currentModule->component)
            @endif
        </div>
    </body>
</html>
