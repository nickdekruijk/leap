<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\Consent;
use NickDeKruijk\Leap\Tests\TestCase;

class ConsentTest extends TestCase
{
    public function test_it_separates_the_categories_a_visitor_can_refuse_from_the_ones_they_cannot(): void
    {
        $this->assertSame(['analytics', 'embeds'], Consent::optionalCategories()->keys()->all());
        $this->assertNotContains('necessary', Consent::optionalCategories()->keys()->all());
    }

    public function test_it_flattens_the_registry_into_the_cookies_a_privacy_page_has_to_list(): void
    {
        $cookies = Consent::cookies();

        $this->assertContains('XSRF-TOKEN', $cookies->pluck('name')->all());
        $this->assertContains('_pk_id*', $cookies->pluck('name')->all());

        // Every entry carries what a privacy statement must state, and what no scanner
        // can ever tell you: which category it belongs to, and how long it is kept.
        $matomo = $cookies->firstWhere('name', '_pk_id*');

        $this->assertSame('analytics', $matomo['category']);
        $this->assertSame('Matomo', $matomo['service']);
        $this->assertNotEmpty($matomo['retention']);
    }

    public function test_a_service_can_need_consent_without_setting_a_cookie(): void
    {
        // An embedded video sets nothing on this site, but the moment it loads it sends
        // the visitor's IP to the provider. That is the thing being consented to — so it
        // belongs in the registry even with an empty cookie list.
        $this->assertSame([], Consent::cookieNames('embeds'));
        $this->assertArrayHasKey('embeds', Consent::optionalCategories()->all());
    }

    public function test_adding_a_service_expires_the_consent_already_given(): void
    {
        // Consent covers what was on the table when it was given. Add a service and it no
        // longer does: the fingerprint changes, the visitor's stored choice stops
        // matching it, and the banner asks again. Without this a site could quietly start
        // setting cookies nobody ever agreed to.
        $before = Consent::version();

        config()->set('leap.consent.categories.marketing', [
            'services' => [
                ['name' => 'Something new', 'cookies' => [['name' => 'brand_new', 'retention' => '1 year']]],
            ],
        ]);

        $this->assertNotSame($before, Consent::version());
    }

    public function test_it_can_be_switched_off_entirely(): void
    {
        // A site with no trackers has nothing to ask about, and one that knowingly skips
        // the question should not have to fake a banner. Either way has() keeps
        // answering, so nothing that depends on consent needs to know it is gone.
        config()->set('leap.consent.enabled', false);
        config()->set('leap.consent.default', 'granted');

        $this->assertFalse(Consent::enabled());
        $this->assertTrue(Consent::defaultState());

        config()->set('leap.consent.default', 'denied');

        $this->assertFalse(Consent::defaultState());
    }

    public function test_it_hands_the_browser_everything_it_needs_in_one_blob(): void
    {
        $blob = Consent::toArray();

        $this->assertSame(Consent::version(), $blob['version']);
        $this->assertSame(['analytics', 'embeds'], $blob['categories']);
        $this->assertArrayHasKey('granular', $blob);
        $this->assertArrayHasKey('default', $blob);
    }

    /**
     * The banner names a component; it does not carry its behaviour.
     *
     * It used to be an inline x-data object calling window.consent from inside the
     * markup. Alpine's CSP build — the one a site needs to keep 'unsafe-eval' out of its
     * Content-Security-Policy — has no method shorthand in its grammar and refuses every
     * value on globalThis, so there the banner threw on load and never appeared. A banner
     * that never appears is a banner that never asks.
     */
    public function test_the_banner_carries_no_behaviour_of_its_own(): void
    {
        $html = view('leap::consent-banner')->render();

        $this->assertStringContainsString('x-data="leapConsent"', $html);

        // The two the CSP build refuses outright.
        $this->assertStringNotContainsString('window.consent', $html);
        $this->assertStringNotContainsString('init()', $html);
    }

    /**
     * The accept button may read differently once the panel is open.
     *
     * Closed it is the only way to say yes. Open it stands next to "save choice", and
     * which of the two ignores the switches is the entire question — a label that does
     * not say "all" leaves it to be guessed. Optional, because a project whose accept
     * label already says "all" has nothing to fix.
     */
    public function test_the_accept_button_can_carry_a_second_label_for_the_open_panel(): void
    {
        config()->set('leap.consent.granular', true);
        config()->set('leap.consent.categories.analytics', ['services' => []]);

        // Both keys ship, so out of the box the two states read the same and nothing is
        // asked of a project that never knew this existed.
        $html = view('leap::consent-banner')->render();
        $this->assertStringContainsString('<span x-show="!settings">'.__('leap::consent.accept').'</span>', $html);
        $this->assertStringContainsString('<span x-show="settings" x-cloak>'.__('leap::consent.accept_all').'</span>', $html);

        // A project that shortens only `accept` keeps the shipped label in the panel:
        // an override falls through to the package for the keys it does not define.
        app('translator')->addLines(['consent.accept' => 'Allow'], app()->getLocale(), 'leap');

        $html = view('leap::consent-banner')->render();
        $this->assertStringContainsString('<span x-show="!settings">Allow</span>', $html);
        $this->assertStringNotContainsString('<span x-show="settings" x-cloak>Allow</span>', $html);

        // The label that is right before Alpine boots is the uncloaked one: settings
        // starts false, so a cloak on the closed label would blank the button on load.
        $this->assertStringNotContainsString('<span x-show="!settings" x-cloak>', $html);
    }

    public function test_the_accept_button_stays_a_plain_label_without_the_panel(): void
    {
        // No panel means no second state to tell apart, so the extra spans would be
        // markup that can never differ from itself.
        config()->set('leap.consent.granular', false);
        config()->set('leap.consent.categories.analytics', ['services' => []]);

        $html = view('leap::consent-banner')->render();

        $this->assertStringContainsString('class="consent-button consent-accept"', $html);
        $this->assertStringNotContainsString('x-show="settings" x-cloak>', $html);
    }

    public function test_the_cookie_table_button_brings_its_own_scope(): void
    {
        // It used to borrow whatever x-data the host layout happened to put on <body>,
        // and did nothing at all on a page that had none.
        $html = view('leap::cookie-table')->render();

        $this->assertStringContainsString('x-data="leapConsentReopen"', $html);
        $this->assertStringContainsString('x-on:click="reopen()"', $html);
        $this->assertStringNotContainsString('window.consent', $html);
    }

    public function test_consent_js_registers_the_components_the_markup_names(): void
    {
        $js = file_get_contents(__DIR__.'/../../resources/js/consent.js');

        $this->assertStringContainsString("Alpine.data('leapConsent'", $js);
        $this->assertStringContainsString("Alpine.data('leapConsentReopen'", $js);
    }

    /**
     * Registered whichever way round the two files load.
     *
     * alpine:init is dispatched once, from Alpine's start(), and a listener added after
     * that never hears it. A bundle that puts consent.js second would therefore register
     * nothing at all — and the failure is silent: the banner simply never appears, which
     * looks exactly like a visitor who has already answered.
     *
     * So there are two paths, and this asserts both are still there. Verified in a
     * browser with the scripts in the wrong order: with the fallback the banner opens and
     * "accept" writes the cookie, without it the button does nothing.
     */
    public function test_consent_js_survives_being_bundled_after_alpine(): void
    {
        $js = file_get_contents(__DIR__.'/../../resources/js/consent.js');

        // The ordinary path: Alpine is still to come.
        $this->assertStringContainsString("addEventListener('alpine:init'", $js);

        // The fallback: Alpine is already here, so register now and walk the elements
        // that were initialised while the components were unknown.
        $this->assertStringContainsString('if (window.Alpine)', $js);
        $this->assertStringContainsString('destroyTree', $js);
        $this->assertStringContainsString('initTree', $js);
    }
}
