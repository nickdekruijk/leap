<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Laravel\Passkeys\Passkeys;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Login;
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

    private function registerPasskey(): void
    {
        $this->createUser()->passkeys()->create([
            'name' => 'Test device',
            'credential_id' => 'fake-credential-id-'.uniqid(),
            'credential' => ['type' => 'public-key'],
        ]);
    }

    public function test_login_hides_passkey_button_until_a_passkey_exists(): void
    {
        Livewire::test(Login::class)->assertDontSee(__('leap::auth.passkey_login'));
    }

    public function test_login_shows_passkey_button_once_any_passkey_exists(): void
    {
        $this->registerPasskey();

        Livewire::test(Login::class)->assertSee(__('leap::auth.passkey_login'));
    }

    public function test_login_hides_passkey_button_when_passkeys_are_disabled(): void
    {
        $this->registerPasskey();
        config(['leap.auth_passkeys.enabled' => false]);

        Livewire::test(Login::class)->assertDontSee(__('leap::auth.passkey_login'));
    }

    public function test_passkey_buttons_keep_an_apostrophe_in_a_translation_inside_the_js_string(): void
    {
        app('translator')->addLines([
            'auth.passkey_delete_confirm' => "Supprimer l'accès ?",
            'auth.passkey_add_prompt' => "Nom de l'appareil :",
        ], app()->getLocale(), 'leap');

        $user = $this->createUser();
        $user->passkeys()->create([
            'name' => 'Test device',
            'credential_id' => 'fake-credential-id',
            'credential' => ['type' => 'public-key'],
        ]);
        $this->actingAs($user);
        $this->grantProfilePermissions();
        session(['auth.password_confirmed_at' => time()]);

        // The browser decodes the attribute before the script runs, so check
        // what the script sees: the apostrophe as a JSON escape (backslash
        // u0027), never a bare quote that ends the JS string.
        $html = html_entity_decode(Livewire::test(Profile::class)->html(), ENT_QUOTES);
        $bs = chr(92);

        $this->assertStringContainsString("confirm('Supprimer l{$bs}u0027acc", $html);
        $this->assertStringContainsString("prompt('Nom de l{$bs}u0027appareil :')", $html);
        $this->assertStringNotContainsString("l'accès", $html);
        $this->assertStringNotContainsString("l'appareil", $html);
    }
}
