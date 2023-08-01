<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Leap</title>
        <link rel="stylesheet" href="{{ route('leap.css') }}">
    </head>
    <body>
            <nav class="nav-main">
        @auth(config('leap.guard'))
                @include('leap::logo')
                <ul>
                    @foreach($modules ?? [] as $item)
                        <li class="{{ $item->slug === $current_module->slug ? 'active' : '' }} pr-2">
                            <a class="block pr-2 py-2" href="{{ route('admin.module', $item->slug) }}">
                                {!! $item->icon() !!}@lang($item->title)
                            </a>
                        </li>
                    @endforeach
                    <li class="pr-2">
                        <form method="post" action="{{ route('leap.logout') }}" class="pr-2 py-2" onclick="this.submit()">
                            @csrf
                            <i class="icon fa-solid fa-right-from-bracket"></i>@lang('Logout')
                        </form>
                    </li>
                </ul>
            </nav>
        @endif
        <div class="slot">
            {{ $slot }}
        </div>
    </body>
</html>
