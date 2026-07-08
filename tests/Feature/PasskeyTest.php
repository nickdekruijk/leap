<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Laravel\Passkeys\Passkeys;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Profile;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Covers the leap-side wiring around laravel/passkeys (config, migration,
 * route registration, Profile's passkey list). The actual WebAuthn
 * create/get ceremony is real browser crypto handled entirely by the
 * laravel/passkeys package itself and isn't re-tested here.
 */
class PasskeyTest extends TestCase
{
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

    /**
     * Give the current request full permissions on the Profile module so the
     * Livewire component's read/update gate passes (normally set by RequireRole).
     */
    private function grantProfilePermissions(): void
    {
        Leap::context()->setPermissions([
            Profile::class => ['read' => true, 'update' => true],
        ]);
    }

    public function test_passkeys_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('passkeys'));
    }

    public function test_passkeys_guard_and_user_model_match_leap_config(): void
    {
        $this->assertSame(config('leap.guard'), config('passkeys.guard'));
        $this->assertSame(User::class, Passkeys::userModel());
    }

    public function test_management_routes_require_authentication(): void
    {
        $this->getJson('/user/passkeys/options')->assertUnauthorized();
    }

    public function test_login_options_route_is_reachable_as_guest(): void
    {
        $this->getJson('/passkeys/login/options')->assertOk()->assertJsonStructure(['options']);
    }

    public function test_new_user_has_no_passkeys(): void
    {
        $user = $this->createUser();

        $this->assertCount(0, $user->passkeys);
        $this->assertFalse($user->hasPasskeysEnabled());
    }

    public function test_profile_lists_users_passkeys(): void
    {
        $user = $this->createUser();
        $user->passkeys()->create([
            'name' => 'Test device',
            'credential_id' => 'fake-credential-id',
            'credential' => ['type' => 'public-key'],
        ]);
        $this->actingAs($user);
        $this->grantProfilePermissions();

        $names = Livewire::test(Profile::class)->instance()->passkeys()->pluck('name')->all();

        $this->assertSame(['Test device'], $names);
    }
}
