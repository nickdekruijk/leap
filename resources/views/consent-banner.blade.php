{{--
    Cookie consent banner.

    Markup and class names are public API: projects style this from their own stylesheet
    (the CSS here is structural only and reads the template's design tokens). Changing a
    class name breaks those overrides, so treat it as breaking and say so in the changelog.

    Publish to replace it wholesale: php artisan vendor:publish --tag=leap-views

    Refusing is one click, exactly like accepting, and nothing is pre-ticked. A banner
    that makes "no" the harder answer does not collect consent — it collects a click.

    And it never blocks the site: no focus trap, no scroll lock, no overlay across the
    page. A visitor who ignores it can read everything, and nothing optional loads until
    they say so. Holding the content hostage until someone chooses is a cookie wall —
    consent given to get rid of a barrier is not freely given, and therefore worthless.
--}}
@php
    use NickDeKruijk\Leap\Classes\Consent;

    $categories = Consent::optionalCategories();
@endphp

@if (Consent::enabled() && $categories->isNotEmpty())
    <script>window.leapConsent = @json(Consent::toArray());</script>

    <div
        class="consent"
        x-data="{
            open: false,
            settings: false,
            choice: {},
            init() {
                this.open = !window.consent.answered();
                document.addEventListener('consent:open', () => { this.open = true; this.settings = {{ Consent::granular() ? 'true' : 'false' }}; });
            },
            accept() { window.consent.acceptAll(); this.open = false; },
            refuse() { window.consent.refuseAll(); this.open = false; },
            save() {
                @foreach ($categories as $key => $category)
                    this.choice['{{ $key }}'] ? window.consent.grant('{{ $key }}') : window.consent.revoke('{{ $key }}');
                @endforeach
                this.open = false;
            },
        }"
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="open = false"
        role="region"
        aria-labelledby="consent-title"
    >
        <div class="consent-dialog">
            <h2 class="consent-title" id="consent-title">@lang('leap::consent.title')</h2>

            <p class="consent-body">@lang('leap::consent.body')</p>

            @if (Consent::granular())
                <div class="consent-categories" x-show="settings" x-cloak>
                    {{-- Always on, and it says so: a switch a visitor cannot move is more
                         honest than one that is pre-ticked and looks like a choice. --}}
                    <label class="consent-category">
                        <input type="checkbox" checked disabled>
                        <span class="consent-switch" aria-hidden="true"></span>
                        <span class="consent-category-text">
                            <strong class="consent-category-name">@lang('leap::consent.necessary')</strong>
                            <span class="consent-category-body">@lang('leap::consent.necessary_body')</span>
                        </span>
                    </label>

                    @foreach ($categories as $key => $category)
                        <label class="consent-category">
                            <input type="checkbox" x-model="choice['{{ $key }}']">
                            <span class="consent-switch" aria-hidden="true"></span>
                            <span class="consent-category-text">
                                <strong class="consent-category-name">@lang('leap::consent.'.$key)</strong>
                                <span class="consent-category-body">@lang('leap::consent.'.$key.'_body')</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="consent-actions">
                <button type="button" class="consent-button consent-accept" x-on:click="accept()">
                    @lang('leap::consent.accept')
                </button>

                <button type="button" class="consent-button consent-refuse" x-show="!settings" x-on:click="refuse()">
                    @lang('leap::consent.refuse')
                </button>

                @if (Consent::granular())
                    <button type="button" class="consent-button consent-settings" x-show="!settings" x-on:click="settings = true">
                        @lang('leap::consent.settings')
                    </button>

                    <button type="button" class="consent-button consent-save" x-show="settings" x-cloak x-on:click="save()">
                        @lang('leap::consent.save')
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
