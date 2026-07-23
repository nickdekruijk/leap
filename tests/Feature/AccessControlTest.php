<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class AccessControlTest extends TestCase
{
    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    public function test_user_without_a_role_is_forbidden(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('leap.home'));

        $response->assertForbidden();
    }

    public function test_user_with_a_role_reaches_the_home_redirect(): void
    {
        $user = $this->createUser();
        // The seeded superuser role (id 1) grants all permissions
        $user->roles()->attach(Role::find(1));

        $response = $this->actingAs($user)->get(route('leap.home'));

        // home() redirects to the first accessible module (the dashboard)
        $response->assertRedirect(route('leap.module.dashboard'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('leap.home'));

        $response->assertRedirect(route('leap.login'));
    }

    public function test_a_permission_grants_only_when_strictly_true(): void
    {
        // Regression: "?? false === true" parsed as "?? (false === true)", so the
        // strict check was dead and any truthy stored value passed the gate.
        $this->actingAs($this->createUser());
        Leap::context()->setModule('TestModule');

        Leap::context()->setPermissions(['TestModule' => ['update' => 1]]);
        $this->assertFalse(Gate::allows('leap::update'));

        Leap::context()->setPermissions(['TestModule' => ['update' => true]]);
        $this->assertTrue(Gate::allows('leap::update'));

        Leap::context()->setPermissions(['TestModule' => ['all_permissions' => 'yes']]);
        $this->assertFalse(Gate::allows('leap::update'));
    }
}
