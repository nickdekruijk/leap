<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Tests\TestCase;

class LocaleHelpersTest extends TestCase
{
    public function test_locale_default_is_null_when_monolingual(): void
    {
        config(['leap.locales' => null]);

        $this->assertNull(Leap::localeDefault());
    }

    public function test_locale_default_is_the_first_configured_locale(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $this->assertSame('nl', Leap::localeDefault());
    }

    public function test_locale_prefix_is_empty_when_monolingual(): void
    {
        config(['leap.locales' => null]);

        $this->assertSame('', Leap::localePrefix('en'));
        $this->assertSame('', Leap::localePrefix());
    }

    public function test_locale_prefix_is_empty_for_the_default_and_slashed_for_others(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $this->assertSame('', Leap::localePrefix('nl'));
        $this->assertSame('/en', Leap::localePrefix('en'));
    }

    public function test_locale_prefix_uses_the_active_locale_when_none_given(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $this->app->setLocale('en');
        $this->assertSame('/en', Leap::localePrefix());

        $this->app->setLocale('nl');
        $this->assertSame('', Leap::localePrefix());
    }

    public function test_detect_locale_is_a_noop_when_monolingual(): void
    {
        config(['leap.locales' => null]);
        $this->app->setLocale('nl');

        $segments = ['en', 'about'];
        Leap::detectLocale($segments);

        // Nothing stripped, locale unchanged.
        $this->assertSame(['en', 'about'], $segments);
        $this->assertSame('nl', $this->app->getLocale());
    }

    public function test_detect_locale_strips_and_applies_a_non_default_locale_segment(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $this->app->setLocale('nl');

        $segments = ['en', 'about'];
        Leap::detectLocale($segments);

        $this->assertSame(['about'], $segments);
        $this->assertSame('en', $this->app->getLocale());
    }

    public function test_detect_locale_leaves_the_default_locale_segment_untouched(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $this->app->setLocale('nl');

        // "nl" is the default (unprefixed) locale, so a literal /nl is a real page path, not a prefix.
        $segments = ['nl', 'about'];
        Leap::detectLocale($segments);

        $this->assertSame(['nl', 'about'], $segments);
        $this->assertSame('nl', $this->app->getLocale());
    }

    public function test_detect_locale_ignores_an_unknown_leading_segment(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $this->app->setLocale('nl');

        $segments = ['contact'];
        Leap::detectLocale($segments);

        $this->assertSame(['contact'], $segments);
        $this->assertSame('nl', $this->app->getLocale());
    }
}
