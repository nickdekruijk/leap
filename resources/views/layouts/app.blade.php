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
                    <li class="{{ empty($currentModule) ? 'active' : '' }}">
                        <a wire:navigate href="{{ route('leap.dashboard') }}">
                            @svg('fas-gauge-high', 'nav-icon')@lang('dashboard')
                        </a>
                    </li>
                    @foreach(Leap::modules() as $module)
                        <li class="{{ $currentModule ?? null == $module ? 'active' : '' }}">
                            <a wire:navigate href="{{ route('leap.module', $module->getSlug()) }}">
                                {{ $module->getNavigationIcon() }}@lang($module->getTitle())
                            </a>
                        </li>
                    @endforeach
                    <li class="bottom-divider"></li>
                    <li>
                        <a wire:navigate href="{{ route('leap.profile') }}">
                            @svg('fas-user-circle', 'nav-icon'){{ auth(config('leap.guard'))->user()->name }}
                        </a>
                    </li>
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
