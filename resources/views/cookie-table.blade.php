{{--
    The cookie registry, rendered for a privacy page.

    Everything here comes from config('leap.consent'). Purpose and retention cannot be
    scanned — no tool can tell you what a cookie is *for* — so they are declared there
    and a browser test holds the declaration to the truth: a cookie that turns up
    without being listed fails the build.
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

    @if (Consent::enabled())
        <p>
            <button type="button" class="button cookie-table-change" x-on:click="window.consent.open()">
                @lang('leap::consent.change')
            </button>
        </p>
    @endif
</div>
