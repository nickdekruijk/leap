<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Tests\TestCase;

class LocaleRoutingTest extends TestCase
{
    /**
     * Register a localized route pair that echoes "<locale>:<slug>" so the request
     * can assert which locale SetLeapLocale applied.
     */
    private function registerEchoRoutes(): void
    {
        Route::leapLocalized(['nl' => 'diensten', 'en' => 'services'], function (string $locale, string $segment): void {
            Route::get($segment.'/{slug}', fn (string $slug): string => app()->getLocale().':'.$slug)
                ->name('svc.'.$locale);
        });
    }

    public function test_the_default_locale_route_is_unprefixed_and_sets_its_locale(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $this->app->setLocale('en'); // start from a different locale to prove the middleware changes it
        $this->registerEchoRoutes();

        $this->get('/diensten/foo')->assertOk()->assertSee('nl:foo');
    }

    public function test_a_secondary_locale_route_is_prefixed_and_sets_its_locale(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $this->app->setLocale('nl');
        $this->registerEchoRoutes();

        $this->get('/en/services/bar')->assertOk()->assertSee('en:bar');
    }

    public function test_the_secondary_segment_is_not_reachable_without_its_prefix(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $this->registerEchoRoutes();

        // The en segment only exists under /en; the nl segment holds the unprefixed slot.
        $this->get('/services/bar')->assertNotFound();
        $this->get('/en/diensten/foo')->assertNotFound();
    }

    public function test_monolingual_registers_a_single_unprefixed_group_for_the_app_locale(): void
    {
        config(['leap.locales' => null]);
        $this->app->setLocale('nl');
        $this->registerEchoRoutes();

        // Only the nl segment is registered, unprefixed and without a locale prefix option.
        $this->get('/diensten/foo')->assertOk()->assertSee('nl:foo');
        $this->get('/en/services/bar')->assertNotFound();
    }
}
