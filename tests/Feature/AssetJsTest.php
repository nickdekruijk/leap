<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Controllers\AssetController;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * passkeys.js is the panel's only first-party JavaScript file — everything else
 * is inline Alpine, Livewire's own bundle or a CDN. It is served straight from
 * the package rather than published to public/, so the route is the only way it
 * reaches the browser and there is nothing to re-publish after an update.
 */
class AssetJsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.auth_passkeys.enabled', true);
    }

    public function test_the_js_route_serves_the_passkeys_script(): void
    {
        $response = $this->get(route('leap.js'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript');

        // window.consent aside, this file's whole job is to define window.passkeys
        // wiring for the login and profile screens.
        $this->assertStringContainsString('passkey', strtolower($response->getContent()));
    }

    /**
     * The response carries far-future cache headers, and the layout appends the
     * file's mtime as a cache buster, so a browser re-fetches it exactly when the
     * package updates and never in between.
     */
    public function test_the_response_is_cacheable_and_busted_by_filemtime(): void
    {
        $response = $this->get(route('leap.js'));

        $this->assertSame(AssetController::CACHE_DURATION, $response->baseResponse->getMaxAge());
        $this->assertGreaterThan(0, AssetController::jsFilemtime());
    }

    public function test_the_layout_links_the_script_when_passkeys_are_enabled(): void
    {
        $html = $this->get(route('leap.login'))->getContent();

        $this->assertStringContainsString(route('leap.js'), $html);
    }
}
