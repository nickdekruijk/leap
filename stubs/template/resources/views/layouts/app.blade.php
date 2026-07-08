<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name') }}</title>
        <style>
            [x-cloak] { display: none !important; }
        </style>
        {!! Minify::stylesheet(['minireset.css', 'template.scss', 'project.scss']) !!}
    </head>

    <body x-data="navigation()" x-on:scroll.window="scrolling = window.pageYOffset > 0" :class="{ 'nav-expanded': navExpanded, 'search-open': searchOpen }">
        <a href="#main" role="button" class="skip-link">@lang('Naar inhoud')</a>

        <nav class="nav" role="navigation" aria-label="@lang('Hoofdmenu')" :class="{ 'scrolling': scrolling }">
            <div class="nav-container main-width">
                <a class="nav-logo" href="/" aria-label="@lang('Naar homepage')">
                    <strong>{{ config('app.name') }}</strong>
                </a>
                <button class="nav-toggle" :aria-expanded="navExpanded.toString()" aria-controls="nav-main" x-on:click="navExpanded = !navExpanded" aria-label="@lang('Menu openen/sluiten')">
                    <span></span><span></span><span></span>
                </button>
                @if (empty($hideNavigation))
                    <div class="nav-main-container">
                        <ul id="nav-main">
                            @foreach (App\Http\Controllers\PageController::getMenu() as $item)
                                @php($children = App\Http\Controllers\PageController::getMenu($item['id'] ?? 0))
                                <li @if ($children) x-data="{ subOpen: false }" x-on:mouseleave="subOpen = false" x-on:click.outside="subOpen = false" @endif>
                                    <a href="{{ $item['url'] }}" aria-current="{{ url($item['url']) == url()->current() ? 'page' : 'false' }}">{{ $item['title'] }}</a>
                                    @if ($children)
                                        <button type="button" class="nav-submenu-caret" :aria-expanded="subOpen.toString()" aria-haspopup="true" aria-controls="submenu-{{ $item['id'] }}" x-on:click="subOpen = !subOpen" x-on:mouseover="subOpen = true" aria-label="{{ __('Submenu :name openen/sluiten', ['name' => $item['title']]) }}"></button>
                                        <ul id="submenu-{{ $item['id'] }}" x-cloak x-show="subOpen" x-transition>
                                            @foreach ($children as $child)
                                                <li><a href="{{ $child['url'] }}" aria-current="{{ url($child['url']) == url()->current() ? 'page' : 'false' }}">{{ $child['title'] }}</a></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                            <li class="nav-search-item">
                                <button class="nav-search-button" x-on:click="searchOpen = !searchOpen" :aria-expanded="searchOpen.toString()" aria-controls="search-overlay" aria-label="@lang('Zoeken')">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="20" height="20">
                                        <circle cx="11" cy="11" r="8" />
                                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                    </svg>
                                </button>
                            </li>
                        </ul>
                    </div>
                @endif
            </div>
        </nav>

        <div id="search-overlay" class="search-overlay" x-cloak x-show="searchOpen" x-transition.opacity.duration.200ms x-on:click.self="searchOpen = false" role="dialog" aria-modal="true" aria-label="@lang('Zoeken')">
            <livewire:search />
        </div>

        @yield('content')

        <footer class="footer">
            <div class="main-width">
                <strong class="footer-name">{{ config('app.name') }}</strong>
                <div class="footer-columns">
                    <div>{!! nl2br(e(setting('footer_contact'))) !!}</div>
                    <div>
                        <strong>@lang('Direct naar')</strong>
                        <ul>
                            @foreach (App\Http\Controllers\PageController::getMenu() as $item)
                                <li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                    @php($socials = array_filter(setting_array('socials') ?: []))
                    @if ($socials)
                        <div>
                            <strong>@lang('Social media')</strong>
                            <ul>
                                @foreach ($socials as $name => $url)
                                    <li><a href="{{ $url }}" target="_blank" rel="noopener">{{ ucfirst($name) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="footer-columns footer-fine">
                    <div>{!! nl2br(e(setting('footer_copyright'))) !!}</div>
                    <div>{!! nl2br(e(setting('footer_links'))) !!}</div>
                </div>
            </div>
        </footer>

        {!! Minify::javascript(['../vendor/nickdekruijk/vanilla-slider/slider.js', '../vendor/nickdekruijk/horizontal-scroller/horizontal-scroller.js', 'scripts.js']) !!}
        @livewireScripts
    </body>

</html>
