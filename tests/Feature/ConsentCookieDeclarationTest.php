<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Str;
use NickDeKruijk\Leap\Classes\Consent;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The registry is a claim about reality, so something has to hold it to reality.
 *
 * This is the half a test suite without a browser can see: the cookies the server itself
 * puts in the response — the session cookie and XSRF-TOKEN. Everything a script sets
 * after the page loads (Matomo's _pk_*, its mtm_cookie_consent) only exists in a real
 * browser, and is checked in the browser suite of a site that uses them.
 */
class ConsentCookieDeclarationTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        // The web group is the point: session and CSRF are what set the cookies under
        // test, so a bare route would prove nothing.
        $router->middleware('web')->get('/consent-cookie-probe', fn (): string => 'ok');
    }

    public function test_every_cookie_the_server_sets_is_declared_in_the_registry(): void
    {
        $declared = Consent::cookies()->pluck('name')->all();

        $cookies = $this->get('/consent-cookie-probe')->assertOk()->headers->getCookies();

        $this->assertNotEmpty($cookies, 'The probe route set no cookies at all, so this test proves nothing.');

        foreach ($cookies as $cookie) {
            $this->assertTrue(
                Str::is($declared, $cookie->getName()),
                "Cookie [{$cookie->getName()}] is set but not declared in config('leap.consent'), so a privacy page rendered from the registry would not mention it.",
            );
        }
    }

    public function test_the_session_cookie_is_declared_by_the_name_the_browser_shows(): void
    {
        // It used to be declared as the pattern '*-session', which matches but reads as a
        // typo on a privacy page: a visitor checking their browser finds
        // "<app-name>-session" and no row that says so. The registry declares ':session'
        // and Consent fills in the name this application really uses.
        $declared = Consent::cookies()->pluck('name')->all();

        $this->assertContains(config('session.cookie'), $declared);
        $this->assertNotContains('*-session', $declared);
        $this->assertNotContains(':session', $declared, 'The placeholder itself must never reach the table.');

        // A project that renames its session cookie gets the new name, not a stale copy.
        config()->set('session.cookie', 'renamed_session');

        $this->assertContains('renamed_session', Consent::cookies()->pluck('name')->all());

        // And a site still on the old published config, which says '*-session', gets the
        // same treatment instead of keeping the pattern on its privacy page forever.
        config()->set('leap.consent.categories.necessary.services', [
            ['name' => 'Website', 'cookies' => [['name' => '*-session', 'retention' => '2 hours']]],
        ]);

        $this->assertSame(['renamed_session'], Consent::cookieNames('necessary'));
    }

    public function test_spelling_the_session_cookie_differently_does_not_ask_the_visitor_again(): void
    {
        // The fingerprint expires consent when the registry changes, which is right when a
        // service is added and wrong here: the same cookie under a different notation is
        // not something a visitor has anything to say about. Upgrading a site from
        // '*-session' to ':session' must leave every stored choice standing.
        config()->set('leap.consent.categories.necessary.services', [
            ['name' => 'Website', 'cookies' => [['name' => '*-session', 'retention' => '2 hours']]],
        ]);

        $before = Consent::version();

        config()->set('leap.consent.categories.necessary.services', [
            ['name' => 'Website', 'cookies' => [['name' => ':session', 'retention' => '2 hours']]],
        ]);

        $this->assertSame($before, Consent::version());

        // Writing the resolved name out by hand is the same registry too.
        config()->set('leap.consent.categories.necessary.services', [
            ['name' => 'Website', 'cookies' => [['name' => config('session.cookie'), 'retention' => '2 hours']]],
        ]);

        $this->assertSame($before, Consent::version());

        // What the fingerprint is actually for still works.
        config()->set('leap.consent.categories.necessary.services', [
            ['name' => 'Website', 'cookies' => [['name' => ':session', 'retention' => '2 hours'], ['name' => 'brand_new', 'retention' => '1 year']]],
        ]);

        $this->assertNotSame($before, Consent::version());
    }
}
