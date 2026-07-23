<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * LeapAuth is the panel's authentication gate. It differs from Laravel's own
 * Authenticate middleware in two ways that are easy to break silently: it always
 * authenticates against config('leap.guard') rather than the default guard, and
 * it redirects to leap.login instead of the application's own login route.
 */
class LeapAuthMiddlewareTest extends TestCase
{
    private function createUser(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_a_guest_is_redirected_to_the_leap_login_route(): void
    {
        $this->get(route('leap.home'))->assertRedirect(route('leap.login'));
    }

    /**
     * redirectTo() returns null for a JSON request, which makes the parent throw
     * an AuthenticationException that renders as a 401 instead of a redirect to
     * an HTML login page a JSON client cannot use.
     */
    public function test_a_json_request_gets_a_401_instead_of_a_redirect(): void
    {
        $this->getJson(route('leap.home'))->assertUnauthorized();
    }

    /**
     * The panel authenticates against leap.guard, not the default guard. A user
     * logged in on some other guard must not reach the panel — actingAs() on a
     * different guard leaves leap.guard a guest.
     */
    public function test_authentication_on_another_guard_does_not_open_the_panel(): void
    {
        config()->set('auth.guards.other', ['driver' => 'session', 'provider' => 'users']);

        $user = $this->createUser();
        $user->roles()->attach(Role::find(1));

        $this->actingAs($user, 'other')
            ->get(route('leap.home'))
            ->assertRedirect(route('leap.login'));
    }

    public function test_an_authenticated_user_passes_the_middleware(): void
    {
        $user = $this->createUser();
        $user->roles()->attach(Role::find(1));

        // Past LeapAuth the request reaches RequireRole and the home redirect;
        // anything other than the login redirect proves the gate let it through.
        $this->actingAs($user)
            ->get(route('leap.home'))
            ->assertRedirect(route('leap.module.dashboard'));
    }

    /**
     * The login screen itself sits outside the middleware, otherwise a guest
     * would be redirected to it in a loop.
     */
    public function test_the_login_route_stays_reachable_for_guests(): void
    {
        $this->get(route('leap.login'))->assertOk();
    }
}
