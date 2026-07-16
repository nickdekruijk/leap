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

    /**
     * The URL structure is declared by leap.locales, which is in version control;
     * APP_LOCALE is in .env, which is not, and differs per environment. Left implicit,
     * one line in an untracked file silently rewrote the site: / rendered English while
     * every URL rule still treated / as the Dutch page, so English answered on both /
     * and /en and Dutch on nothing at all.
     */
    public function test_detect_locale_applies_the_default_locale_when_there_is_no_prefix(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        // As if .env carried APP_LOCALE=en on a site whose default locale is nl.
        $this->app->setLocale('en');

        $segments = ['about'];
        Leap::detectLocale($segments);

        $this->assertSame(['about'], $segments, 'Nothing to strip: / is the default locale.');
        $this->assertSame('nl', $this->app->getLocale(), 'The unprefixed URL must render the locale that claims it.');
        $this->assertSame('', Leap::localePrefix('nl'), 'And that is the locale whose prefix is empty.');
    }

    public function test_detect_locale_applies_the_default_locale_on_the_bare_root(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $this->app->setLocale('en');

        $segments = [];
        Leap::detectLocale($segments);

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
