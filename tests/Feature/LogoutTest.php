<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use NickDeKruijk\Leap\Models\Log;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class LogoutTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app['config'];
        $config->set('leap.logging.enabled', true);
        $config->set('leap.logging.skip_actions', []);
        $config->set('leap.logging.skip_modules', []);
    }

    private function createUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->roles()->attach(Role::find(1));

        return $user;
    }

    public function test_logout_ends_the_session_and_redirects_home(): void
    {
        $this->actingAs($this->createUser());

        $this->post(route('leap.logout'))->assertRedirect(route('leap.home'));

        $this->assertGuest(config('leap.guard'));
    }

    /**
     * The session is invalidated and its CSRF token regenerated, so a token
     * captured before logging out cannot be replayed against the next session.
     */
    public function test_logout_regenerates_the_csrf_token(): void
    {
        $this->actingAs($this->createUser());

        $this->get(route('leap.home'));
        $before = session()->token();

        $this->post(route('leap.logout'));

        $this->assertNotSame($before, session()->token());
    }

    public function test_logout_is_recorded_in_the_audit_log(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post(route('leap.logout'));

        $log = Log::where('action', 'logout')->latest('id')->first();

        $this->assertNotNull($log, 'Logging out must leave an audit trail.');
        $this->assertSame($user->id, $log->user_id);
    }

    /**
     * The route sits outside the auth middleware group, so a guest can reach it.
     * It must not blow up on a missing user — it just bounces back to the panel,
     * which sends them to the login screen.
     */
    public function test_a_guest_posting_logout_is_handled_without_error(): void
    {
        $this->assertGuest(config('leap.guard'));

        $this->post(route('leap.logout'))->assertRedirect(route('leap.home'));
    }

    /**
     * Logging out must only touch the panel guard; a session on another guard
     * (a frontend login, say) is left alone.
     */
    public function test_logout_leaves_another_guard_alone(): void
    {
        config()->set('auth.guards.other', ['driver' => 'session', 'provider' => 'users']);

        $panelUser = $this->createUser();
        $otherUser = $this->createUser();

        $this->actingAs($panelUser);
        Auth::guard('other')->login($otherUser);

        $this->post(route('leap.logout'));

        $this->assertTrue(Auth::guard('other')->check());
    }
}
