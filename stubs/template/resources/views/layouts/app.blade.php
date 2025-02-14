<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- <meta name="color-scheme" content="light dark"> --}}
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- <meta name="description" content=""> --}}
        <title>{{ config('app.name') }}</title>
        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>
        <noscript>
            <style>
                [x-cloak] {
                    display: revert !important;
                }
            </style>
        </noscript>
        {!! Minify::stylesheet(['minireset.css', 'layout.scss', 'navigation.scss', 'app.scss', 'sections.scss']) !!}
    </head>

    <body class="nav-full" x-data="{ navExpanded: false, scrolling: false }" x-on:scroll.window="scrolling = window.pageYOffset > 0" :class="navExpanded ? 'nav-expanded' : ''" x-on:keydown.escape="navExpanded = false">
        <a href="#main" role="button" class="skip-link">Skip to content</a>
        <nav class="nav" role="navigation" aria-label="Main menu" :class="{ 'scrolling': scrolling }">
            <div class="nav-container main-width">
                <a class="nav-logo" href="/" class="contrast" aria-label="Homepage">
                    <strong>Acme Corp</strong>
                </a>
                <button class="nav-toggle" aria-expanded="false" :aria-expanded="navExpanded" :aria-label="navExpanded ? 'Close main menu' : 'Open main menu'" aria-controls="nav-main" x-on:click="navExpanded = !navExpanded">
                    <span></span>
                </button>
                <div class="nav-main-container">
                    <ul id="nav-main">
                        @foreach (App\Http\Controllers\PageController::getMenu() as $page)
                            <li @if (App\Http\Controllers\PageController::getMenu($page['id'])) x-data="{ open: false }" x-id="['submenu']" x-on:keydown.escape="open=false" @endif>
                                <a role="{{ $loop->last ? 'button' : '' }}" aria-current="{{ $page['id'] == request('id') ? 'page' : 'false' }}" href="{{ $page['url'] }}">{{ $page['title'] }}</a>
                                @if (App\Http\Controllers\PageController::getMenu($page['id']))
                                    <button aria-expanded="false" :aria-expanded="open" :aria-controls="$id('submenu')" :aria-label="open ? 'Close {{ $page['title'] }} submenu' : 'Open {{ $page['title'] }} submenu'" x-on:click.stop="open=!open"></button>
                                    <ul x-show="open" x-transition :id="$id('submenu')" x-on:click.outside="open=false">
                                        @foreach (App\Http\Controllers\PageController::getMenu($page['id']) as $subpage)
                                            <li><a aria-current="{{ $subpage['id'] == request('id') ? 'page' : 'false' }}" href="{{ $subpage['url'] }}">{{ $subpage['title'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </nav>
        @yield('content')
        <footer>
            <div class="main-width">
                &copy; {{ date('Y') }} <a href="https://nickdekruijk.nl" target="_blank" rel="noopener">Nick de Kruijk</a>
            </div>
        </footer>
        {{-- {!! Minify::javascript(['scripts.js']) !!} --}}
        @livewireScripts
    </body>

</html>
