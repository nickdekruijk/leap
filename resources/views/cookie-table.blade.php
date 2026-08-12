{{--
    The cookie registry, rendered for a privacy page.

    Everything here comes from config('leap.consent'). Purpose and retention cannot be
    scanned — no tool can tell you what a cookie is *for* — so they are declared there,
    and ConsentCookieDeclarationTest holds the declaration to the truth for every cookie
    the server sets. The ones a script sets afterwards need a browser to be seen at all.
--}}
@php
    use NickDeKruijk\Leap\Classes\Consent;
@endphp

<div class="cookie-table">
    @foreach (Consent::categories() as $key => $category)
        <h3 class="cookie-table-category">@lang('leap::consent.'.$key)</h3>
        <p>@lang('leap::consent.'.$key.'_body')</p>

        <table>
            <thead>
                <tr>
                    <th>@lang('leap::consent.table_cookie')</th>
                    <th>@lang('leap::consent.table_service')</th>
                    <th>@lang('leap::consent.table_provider')</th>
                    <th>@lang('leap::consent.table_retention')</th>
                </tr>
            </thead>
            <tbody>
                @forelse (Consent::cookies()->where('category', $key) as $cookie)
                    <tr>
                        <td><code>{{ $cookie['name'] }}</code></td>
                        <td>{{ $cookie['service'] }}</td>
                        <td>{{ $cookie['provider'] ?: __('leap::consent.first_party') }}</td>
                        {{-- Run through the translator so a site can render "13 months"
                             in its own language (lang/nl.json). An untranslated string
                             passes through unchanged, so plain English keeps working. --}}
                        <td>{{ __($cookie['retention'] ?? '') }}</td>
                    </tr>
                @empty
                    {{-- A service can need consent without setting a cookie: an embedded
                         video sets none here, but sends the visitor's data to the provider
                         the moment it loads. That is the thing being consented to. --}}
                    <tr>
                        <td colspan="4">
                            @lang('leap::consent.no_cookies', [
                                'services' => collect($category['services'] ?? [])->pluck('name')->join(', ', ' ' . __('leap::consent.and') . ' '),
                            ])
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    {{-- Only when there is something to explain. A name like _pk_id* ends in a part that
         differs per site, so the asterisk is honest — but on its own it reads as a typo to
         anyone who has not written a glob before. A site whose cookies all have fixed
         names gets no sentence about asterisks. --}}
    @if (Consent::cookies()->contains(fn ($cookie) => str_contains($cookie['name'], '*')))
        <p class="cookie-table-note">@lang('leap::consent.wildcard_note')</p>
    @endif

    @if (Consent::enabled())
        <p>
            {{-- Its own scope, from consent.js. It used to call window.consent.open()
                 through whatever x-data the host layout happened to put on <body> —
                 which the CSP build refuses on both counts, and which meant this button
                 silently did nothing on a page that had no such wrapper. --}}
            <button type="button" class="button cookie-table-change" x-data="leapConsentReopen" x-on:click="reopen()">
                @lang('leap::consent.change')
            </button>
        </p>
    @endif
</div>
