<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Facade;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\User as UserModule;
use NickDeKruijk\Leap\Module;
use NickDeKruijk\Leap\Navigation\Logout;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Every entry in the panel menu derives its label, url and position from the
 * NavigationItem trait. The defaults are all implicit — a module with no title
 * is named after its class, one with no slug gets that title slugified — so a
 * rename silently moves a url unless these are pinned down.
 */
class NavigationItemTest extends TestCase
{
    public function test_a_module_without_a_title_is_named_after_its_class(): void
    {
        $module = new class extends Module
        {
            public $icon = 'fas-box';
        };

        // Anonymous classes have a generated basename, so assert on the rule rather
        // than a literal: the title is the pluralised class basename.
        $this->assertSame(
            Str::plural(class_basename($module::class)),
            $module->getTitle()
        );
    }

    public function test_a_leap_translation_key_is_resolved_as_the_title(): void
    {
        $module = new class extends Module
        {
            public $title = 'leap::auth.logout';
        };

        $this->assertSame(__('leap::auth.logout'), $module->getTitle());
    }

    /**
     * A title given per locale picks the current one, and falls back to the
     * pluralised class name for a locale that was never translated.
     */
    public function test_a_localised_title_follows_the_active_locale(): void
    {
        app()->setLocale('nl');

        $this->assertSame('Gebruikers', (new UserModule)->getTitle());
    }

    public function test_a_localised_title_falls_back_when_the_locale_is_missing(): void
    {
        app()->setLocale('fr');

        $this->assertSame('Users', (new UserModule)->getTitle());
    }

    public function test_the_slug_defaults_to_the_slugified_title(): void
    {
        $this->assertSame('dashboard', (new Dashboard)->getSlug());
    }

    /**
     * Logout is a form, not a link, so it declares $slug = false and must not get
     * a route. getSlug() is typed ?string, which coerces that false to an empty
     * string — still falsy, which is what routes/web.php tests, so what matters is
     * that no route is registered for it.
     */
    public function test_a_module_can_refuse_a_slug(): void
    {
        $this->assertEmpty((new Logout)->getSlug());
        $this->assertNull(Route::getRoutes()->getByName('leap.module.logout'));
    }

    public function test_the_priority_defaults_to_one(): void
    {
        $module = new class extends Module
        {
            public $icon = 'fas-box';
        };

        $this->assertSame(1, $module->getPriority());
    }

    public function test_a_declared_priority_is_used(): void
    {
        $this->assertSame(-100, (new Dashboard)->getPriority());
        $this->assertSame(1999, (new Logout)->getPriority());
    }

    /**
     * Most modules render as a plain link and return no custom html; Logout is
     * the exception, and its form must carry a CSRF token or logging out fails
     * with a 419 the moment the session is stale.
     */
    public function test_only_logout_renders_its_own_markup(): void
    {
        $this->assertNull((new Dashboard)->getOutput());

        $output = (new Logout)->getOutput();

        $this->assertStringContainsString('method="post"', $output);
        $this->assertStringContainsString(route('leap.logout'), $output);
        $this->assertStringContainsString('name="_token"', $output);
    }

    public function test_logout_is_available_to_every_role_by_default(): void
    {
        $defaults = (new \ReflectionProperty(Logout::class, 'default_permissions'))
            ->getValue(new Logout);

        $this->assertSame(['read' => true, 'update' => true], $defaults);
    }

    /**
     * The Leap facade is the package's public entry point; it must resolve to the
     * same instance the container holds, or state set through one is invisible to
     * the other.
     */
    public function test_the_facade_resolves_the_container_binding(): void
    {
        $this->assertSame('leap', (new \ReflectionMethod(Facade::class, 'getFacadeAccessor'))->invoke(null));
        $this->assertInstanceOf(Leap::class, app('leap'));
        $this->assertSame(app('leap'), \Leap::getFacadeRoot());
    }
}
