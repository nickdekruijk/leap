<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Navigation;
use NickDeKruijk\Leap\Livewire\Toasts;
use NickDeKruijk\Leap\Models\Log;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The three components every panel page carries: the landing module, the module
 * menu and the toast stack. They hold little logic, which is exactly why nothing
 * caught it when a view they render was renamed or a permission default moved.
 */
class PanelScreensTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app['config'];
        $config->set('leap.logging.enabled', true);
        $config->set('leap.logging.skip_actions', []);
        $config->set('leap.logging.skip_modules', []);
    }

    private function actingAsPanelUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        return $user;
    }

    public function test_the_dashboard_renders_for_a_user_with_a_role(): void
    {
        $this->actingAsPanelUser();
        Leap::context()->setPermissions([Dashboard::class => ['read' => true]]);

        Livewire::test(Dashboard::class)->assertOk();
    }

    /**
     * The dashboard is the panel's landing page, so its read is the first entry
     * of a session — the audit trail starts there or not at all.
     */
    public function test_rendering_the_dashboard_logs_a_read(): void
    {
        $user = $this->actingAsPanelUser();
        Leap::context()->setModule(Dashboard::class);
        Leap::context()->setPermissions([Dashboard::class => ['read' => true]]);

        Livewire::test(Dashboard::class)->assertOk();

        $this->assertTrue(
            Log::where('action', 'read')->where('user_id', $user->id)->exists(),
            'Opening the dashboard must be recorded.'
        );
    }

    /**
     * Dashboard grants read by default, so a fresh role that was never given an
     * explicit permission still has somewhere to land after logging in.
     */
    public function test_the_dashboard_grants_read_by_default(): void
    {
        $defaults = (new \ReflectionProperty(Dashboard::class, 'default_permissions'))
            ->getValue(new Dashboard);

        $this->assertSame(['read' => true], $defaults);
    }

    public function test_the_navigation_records_the_current_url_on_mount(): void
    {
        $this->actingAsPanelUser();

        $component = Livewire::test(Navigation::class);

        $component->assertOk();
        $this->assertSame(url()->current(), $component->get('currentUrl'));
    }

    /**
     * The menu only lists what the role may open. A user whose permissions do not
     * include the file manager must not be shown a link to it.
     */
    public function test_the_navigation_hides_modules_the_role_cannot_read(): void
    {
        $this->actingAsPanelUser();
        Leap::context()->setPermissions([Dashboard::class => ['read' => true]]);

        Livewire::test(Navigation::class)
            ->assertOk()
            ->assertDontSee(route('leap.module.filemanager'));
    }

    public function test_a_toast_is_added_by_its_event(): void
    {
        $component = Livewire::test(Toasts::class)
            ->dispatch('toast-error', message: 'Something broke');

        $toasts = $component->get('toasts');

        $this->assertCount(1, $toasts);
        $this->assertSame('Something broke', $toasts[0]['message']);
        $this->assertSame('error', $toasts[0]['type']);
    }

    /**
     * Toasts expire on their own; opening or closing the editor sweeps the stale
     * ones so a message from three screens ago does not reappear.
     */
    public function test_expired_toasts_are_swept_and_current_ones_kept(): void
    {
        $component = Livewire::test(Toasts::class);

        $component->instance()->add('stale');
        $component->instance()->toasts[0]['expires'] = time() - 1;
        $component->instance()->add('fresh');

        $component->instance()->clearExpired();

        $messages = array_column($component->instance()->toasts, 'message');
        $this->assertSame(['fresh'], array_values($messages));
    }

    public function test_closing_a_toast_removes_only_that_one(): void
    {
        $component = Livewire::test(Toasts::class);

        $component->instance()->add('first');
        $component->instance()->add('second');
        $component->instance()->close(0);

        $messages = array_column($component->instance()->toasts, 'message');
        $this->assertSame(['second'], array_values($messages));
    }
}
