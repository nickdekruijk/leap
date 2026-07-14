<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ ($page ?? null)?->documentTitle() ?? config('app.name') }}</title>
        @if (($page ?? null)?->description)
            <meta name="description" content="{{ $page->description }}">
        @endif
        <link rel="canonical" href="{{ url()->current() }}">
        @foreach (App\Http\Controllers\PageController::localeUrls($page ?? null) as $hrefLocale => $alt)
            <link rel="alternate" hreflang="{{ $hrefLocale }}" href="{{ url($alt['url']) }}">
        @endforeach
        @php
            // og:image priority: the page's own image/section image, then the og_image site setting
            $ogImage = ($page ?? null)?->ogImageUrl();
            if (! $ogImage && function_exists('setting') && setting('og_image')) {
                $ogImage = str_starts_with(setting('og_image'), 'http') ? setting('og_image') : url(setting('og_image'));
            }
        @endphp
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
        <meta property="og:title" content="{{ ($page ?? null)?->documentTitle() ?? config('app.name') }}">
        <meta property="og:url" content="{{ url()->current() }}">
        @if (($page ?? null)?->description)
            <meta property="og:description" content="{{ $page->description }}">
        @endif
        @if ($ogImage)
            <meta property="og:image" content="{{ $ogImage }}">
        @endif
        <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ ($page ?? null)?->documentTitle() ?? config('app.name') }}">
        @if (($page ?? null)?->description)
            <meta name="twitter:description" content="{{ $page->description }}">
        @endif
        @if ($ogImage)
            <meta name="twitter:image" content="{{ $ogImage }}">
        @endif
        <style>
            [x-cloak] { display: none !important; }
        </style>
        {{-- Vendor assets by absolute path: a relative one only resolves when the working
             directory is public/, which is true for a web request and false for anything
             run through artisan — so the stylesheet would not compile under test. --}}
        {!! Minify::stylesheet(['minireset.css', base_path('vendor/nickdekruijk/leap/resources/css/consent.css'), 'template.scss', 'project.scss']) !!}

        {{--
            Matomo, when leap.consent.matomo is configured. requireCookieConsent means it
            measures every visitor but sets no cookie, so the cookie law is never
            triggered and the people who refuse are still counted; consent.js switches
            its cookies on once the analytics category is granted.

            Anything that cannot run cookieless — GA4, Meta, Hotjar — belongs in the
            "scripts_<category>" setting below, not here.
        --}}
        @if (config('leap.consent.matomo.url') && config('leap.consent.matomo.site_id'))
            <script>
                var _paq = window._paq = window._paq || [];
                _paq.push(['requireCookieConsent']);
                _paq.push(['enableHeartBeatTimer']);
                _paq.push(['trackPageView']);
                _paq.push(['enableLinkTracking']);
                (function () {
                    var u = @json(rtrim(config('leap.consent.matomo.url'), '/') . '/');
                    _paq.push(['setTrackerUrl', u + 'matomo.php']);
                    _paq.push(['setSiteId', @json((string) config('leap.consent.matomo.site_id'))]);
                    var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
                    g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);
                })();

                // Consent only turns Matomo's cookies on or off — it keeps measuring
                // either way. The hook lives here rather than in consent.js, so that
                // stays free of any knowledge of a particular vendor.
                document.addEventListener('consent:change', function (e) {
                    _paq.push([e.detail.analytics ? 'rememberCookieConsentGiven' : 'forgetCookieConsentGiven']);
                });

                document.addEventListener('DOMContentLoaded', function () {
                    if (window.consent && window.consent.has('analytics')) {
                        _paq.push(['rememberCookieConsentGiven']);
                    }
                });
            </script>
        @endif

        {{-- Code that needs NO consent. Trackers belong in scripts_<category>, below. --}}
        @if (function_exists('setting') && setting('html_head'))
            {!! setting('html_head') !!}
        @endif
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
                                @php $children = App\Http\Controllers\PageController::getMenu($item['id'] ?? 0); @endphp
                                {{-- Submenus fold open on desktop; inside the hamburger panel they are just listed under their parent --}}
                                <li @if ($children) x-data="{ subOpen: false }" x-on:mouseover="!isMobile && (subOpen = true)" x-on:mouseleave="subOpen = false" x-on:click.outside="subOpen = false" @endif>
                                    <a href="{{ $item['url'] }}" aria-current="{{ url($item['url']) == url()->current() ? 'page' : 'false' }}">{{ $item['title'] }}</a>
                                    @if ($children)
                                        <button type="button" class="nav-submenu-caret" x-show="!isMobile" :aria-expanded="subOpen.toString()" aria-haspopup="true" aria-controls="submenu-{{ $item['id'] }}" x-on:click="subOpen = !subOpen" x-on:mouseover="subOpen = true" aria-label="{{ __('Submenu :name openen/sluiten', ['name' => $item['title']]) }}"></button>
                                        <ul id="submenu-{{ $item['id'] }}" x-cloak x-show="subOpen || isMobile" x-transition>
                                            @foreach ($children as $child)
                                                <li><a href="{{ $child['url'] }}" aria-current="{{ url($child['url']) == url()->current() ? 'page' : 'false' }}">{{ $child['title'] }}</a></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                            @php $localeUrls = App\Http\Controllers\PageController::localeUrls($page ?? null); @endphp
                            @if (count($localeUrls) > 1)
                                <li class="nav-locale-switch">
                                    @foreach ($localeUrls as $code => $l)
                                        <a href="{{ $l['url'] }}" @class(['active' => $l['active']]) aria-current="{{ $l['active'] ? 'true' : 'false' }}">{{ strtoupper($code) }}</a>
                                    @endforeach
                                </li>
                            @endif
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
            @php
                // The frontend template suggests nickdekruijk/settings; degrade gracefully when it is absent
                $hasSettings = function_exists('setting');
                $footerContact = $hasSettings ? setting('footer_contact') : null;
                $socials = $hasSettings ? array_filter(setting_array('socials') ?: []) : [];
                $footerCopyright = $hasSettings ? setting('footer_copyright') : null;
                $footerLinks = $hasSettings ? array_filter(setting_array('footer_links') ?: []) : [];
            @endphp
            <div class="main-width">
                <strong class="footer-name">{{ config('app.name') }}</strong>
                <div class="footer-columns">
                    @if ($footerContact)
                        <div>{!! preg_replace('/([\w.+-]+@[\w-]+\.[\w.-]+)/', '<a href="mailto:$1">$1</a>', nl2br(e($footerContact))) !!}</div>
                    @endif
                    <div>
                        <strong>@lang('Direct naar')</strong>
                        <ul>
                            @foreach (App\Http\Controllers\PageController::getMenu() as $item)
                                <li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                    @if ($socials)
                        <div>
                            <strong>@lang('Social media')</strong>
                            <ul class="footer-socials">
                                @foreach ($socials as $name => $url)
                                    <li>
                                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($name) }}">
                                            <x-dynamic-component :component="'fab-' . \Illuminate\Support\Str::slug($name)" class="social-icon" aria-hidden="true" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                @if ($footerCopyright || $footerLinks)
                    <div class="footer-columns footer-fine">
                        <div>{!! nl2br(e($footerCopyright)) !!}</div>
                        @if ($footerLinks)
                            <ul class="footer-links">
                                @foreach ($footerLinks as $label => $url)
                                    <li><a href="{{ $url }}">{{ $label }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        </footer>

        {{--
            Consent-gated scripts, one slot per optional category. An editor pastes the
            vendor's own snippet into the "scripts_analytics" (or _marketing, …) setting;
            it sits in a <template>, which the browser parses but never runs — no script
            executes, no request goes out, not even for an external src. consent.js clones
            it into the page only once that category is granted.

            This is why the template needs to know nothing about GA4, Meta or Hotjar: any
            vendor snippet works, unchanged.
        --}}
        @foreach (NickDeKruijk\Leap\Classes\Consent::optionalCategories() as $consentCategory => $consentDetails)
            @if (function_exists('setting') && setting('scripts_'.$consentCategory))
                <template data-consent="{{ $consentCategory }}">{!! setting('scripts_'.$consentCategory) !!}</template>
            @endif
        @endforeach

        @include('leap::consent-banner')

        {!! Minify::javascript([
            base_path('vendor/nickdekruijk/leap/resources/js/consent.js'),
            base_path('vendor/nickdekruijk/vanilla-slider/slider.js'),
            base_path('vendor/nickdekruijk/horizontal-scroller/horizontal-scroller.js'),
            'scripts.js',
        ]) !!}
        @livewireScripts
    </body>

</html>
