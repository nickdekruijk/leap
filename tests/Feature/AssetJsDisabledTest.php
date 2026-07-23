<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * With passkeys switched off the script is not merely unused — the route is
 * never registered, so nothing serves it and the layout does not link it. The
 * shipped config disables passkeys by default, which is the state under test.
 */
class AssetJsDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.auth_passkeys.enabled', false);
    }

    public function test_the_js_route_is_not_registered(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('leap.js'));
    }

    public function test_the_login_screen_loads_no_first_party_script(): void
    {
        $html = $this->get(route('leap.login'))->getContent();

        $this->assertStringNotContainsString('leap.js', $html);
    }
}
