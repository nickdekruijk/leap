<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Leap</title>
        <link rel="stylesheet" href="{{ route('leap.css') }}">
    </head>
    <body>
        @auth(config('leap.guard'))
            <nav class="nav">
                @include('leap::logo')
                <ul>
                    @foreach(Leap::modules() as $module)
                        @if ($module->priority === 1001)
                            <li class="bottom-divider"></li>
                        @endif
                        <li class="{{ $currentModule == $module ? 'active' : '' }}">
                            <a wire:navigate href="{{ route('leap.module', $module->slug) }}">
                                @svg($module->icon, 'nav-icon')@lang($module->title)
                            </a>
                        </li>
                    @endforeach
                    <li class="logout">
                        <form method="post" action="{{ route('leap.logout') }}" onclick="this.submit()">
                            @csrf
                            @svg('fas-sign-out-alt', 'nav-icon')@lang('logout')
                        </form>
                    </li>
                </ul>
            </nav>
        @endif
        <div class="slot">
            @isset($slot)
                {{ $slot }}
            @else
                @livewire($currentModule->component)
            @endif
        </div>
    </body>
</html>
