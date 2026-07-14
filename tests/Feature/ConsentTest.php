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
}
