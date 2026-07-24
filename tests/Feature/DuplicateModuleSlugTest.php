<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Navigation\Logout;
use NickDeKruijk\Leap\Tests\Fixtures\PackageSettingResource;
use NickDeKruijk\Leap\Tests\Fixtures\ProjectSettingResource;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A module's slug is its identity: it is the route name (leap.module.{slug}) and the
 * navigation entry. Two modules claiming one slug used to yield two navigation items
 * and two Route::get() calls under the same name, which is how a project that keeps
 * its own copy of a module a package now ships ends up with the screen listed twice.
 *
 * The last registration wins. The app/Leap scan runs after leap.default_modules, so a
 * project's own module replaces the one a package self-registered — the override a
 * project means by putting the file there.
 */
class DuplicateModuleSlugTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [
            Dashboard::class,
            PackageSettingResource::class,
            ProjectSettingResource::class,
            Logout::class,
        ]);
    }

    public function test_two_modules_sharing_a_slug_yield_one_module(): void
    {
        $slugs = ModuleController::getAllModules()
            ->map(fn ($module): string => (string) $module->getSlug())
            ->all();

        $this->assertSame(['settings'], array_values(array_filter($slugs, fn ($slug): bool => $slug === 'settings')));
    }

    public function test_the_last_registration_wins(): void
    {
        $module = ModuleController::getAllModules()
            ->first(fn ($module): bool => $module->getSlug() === 'settings');

        $this->assertInstanceOf(ProjectSettingResource::class, $module);
    }

    public function test_the_shared_slug_registers_a_single_route(): void
    {
        $matching = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->getName() === 'leap.module.settings');

        $this->assertCount(1, $matching);
    }

    public function test_slugless_navigation_items_are_not_deduplicated(): void
    {
        $modules = ModuleController::getAllModules();

        // Dashboard, one settings module and Logout — Logout has $slug = false and
        // must survive on its own rather than collapsing into an empty-slug bucket.
        $this->assertCount(3, $modules);
        $this->assertCount(1, $modules->filter(fn ($module): bool => $module instanceof Logout));
    }
}
