<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Passkeys\Events\PasskeyVerified;
use Livewire\Livewire;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Auth2FA;
use NickDeKruijk\Leap\Livewire\Profile;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class TwoFactorEnrollmentTest extends TestCase
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

    private function confirmTwoFactorFor(User $user): void
    {
        (new EnableTwoFactorAuthentication(app(TwoFactorAuthenticationProvider::class)))($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    }

    public function test_not_required_by_default_no_change_in_behavior(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('leap.home'));

        $response->assertRedirect(route('leap.module.dashboard'));
    }

    public function test_required_and_no_method_redirects_other_modules_to_profile(): void
    {
        config(['leap.auth_2fa.required' => true]);
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('leap.home'));

        $response->assertRedirect(route('leap.module.profile'));

        $response = $this->actingAs($user)->get(route('leap.module.dashboard'));

        $response->assertRedirect(route('leap.module.profile'));
    }

    public function test_required_and_no_method_filemanager_download_route_is_blocked(): void
    {
        config(['leap.auth_2fa.required' => true]);
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('leap.module.filemanager.download', ['name' => 'test.txt']));

        $response->assertRedirect(route('leap.module.profile'));
    }

    public function test_required_and_no_method_profile_remains_reachable_and_functional(): void
    {
        config(['leap.auth_2fa.required' => true]);
        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantProfilePermissions();

        Livewire::test(Profile::class)
            ->assertOk()
            ->call('enableTwoFactor');

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
    }

    public function test_required_and_no_method_profile_shows_notice(): void
    {
        config(['leap.auth_2fa.required' => true]);
        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantProfilePermissions();

        Livewire::test(Profile::class)
            ->assertSee(__('leap::auth.two_factor_required_notice'));
    }

    public function test_required_and_method_configured_profile_shows_no_notice(): void
    {
        config(['leap.auth_2fa.required' => true]);
        $user = $this->createUser();
        $this->confirmTwoFactorFor($user);
        $this->actingAs($user);
        $this->grantProfilePermissions();

        Livewire::test(Profile::class)
            ->assertDontSee(__('leap::auth.two_factor_required_notice'));
    }

    public function test_required_and_method_configured_allows_normal_access(): void
    {
        config(['leap.auth_2fa.required' => true]);
        $user = $this->createUser();
        $this->confirmTwoFactorFor($user);
        $this->actingAs($user);
        session(['leap.auth_2fa.validated' => true]);

        $response = $this->get(route('leap.home'));

        $response->assertRedirect(route('leap.module.dashboard'));
    }

    public function test_passkey_does_not_satisfy_requirement_when_disabled(): void
    {
        config([
            'leap.auth_2fa.required' => true,
            'leap.auth_passkeys.satisfies_2fa_requirement' => false,
        ]);
        $user = $this->createUser();
        $user->passkeys()->create([
            'name' => 'Test device',
            'credential_id' => 'fake-credential-id',
            'credential' => ['type' => 'public-key'],
        ]);

        $response = $this->actingAs($user)->get(route('leap.home'));

        $response->assertRedirect(route('leap.module.profile'));
    }

    public function test_required_and_no_method_navigation_only_shows_profile_and_logout(): void
    {
        config(['leap.auth_2fa.required' => true]);
        $user = $this->createUser();
        $this->actingAs($user);
        Leap::context()->setPermissions(collect(ModuleController::getAllModules())
            ->mapWithKeys(fn ($module) => [$module::class => ['read' => true, 'all_permissions' => true]])
            ->all());

        $slugs = Leap::modules()->map(fn ($module) => $module->getSlug())->values()->all();

        // Only Profile + Logout. Logout's $slug = false coerces to '' via getSlug()'s
        // ?string return type. ->values() reindexes (the modules keep their original
        // keys); PHPUnit 12's canonicalizing compare is strict on keys and values (11 == was not).
        $this->assertEqualsCanonicalizing(['profile', ''], $slugs);
    }

    public function test_passkey_satisfies_requirement_but_still_needs_validating_this_session(): void
    {
        config([
            'leap.auth_2fa.required' => true,
            'leap.auth_passkeys.satisfies_2fa_requirement' => true,
        ]);
        $user = $this->createUser();
        $user->passkeys()->create([
            'name' => 'Test device',
            'credential_id' => 'fake-credential-id',
            'credential' => ['type' => 'public-key'],
        ]);

        // Not enrollment (they have a method), but not yet validated this session either
        $this->assertSame('passkey', Leap::twoFactorMethod($user));

        $response = $this->actingAs($user)->get(route('leap.home'));

        $response->assertRedirect(route('leap.auth_2fa'));
    }

    public function test_passkey_verified_event_validates_the_session_when_satisfies_requirement_enabled(): void
    {
        config([
            'leap.auth_2fa.required' => true,
            'leap.auth_passkeys.satisfies_2fa_requirement' => true,
        ]);
        $user = $this->createUser();
        $passkey = $user->passkeys()->create([
            'name' => 'Test device',
            'credential_id' => 'fake-credential-id',
            'credential' => ['type' => 'public-key'],
        ]);
        $this->actingAs($user);

        $this->assertNull(session('leap.auth_2fa.validated'));

        PasskeyVerified::dispatch($user, $passkey);

        $this->assertTrue(session('leap.auth_2fa.validated'));

        $response = $this->get(route('leap.home'));
        $response->assertRedirect(route('leap.module.dashboard'));
    }

    public function test_passkey_verified_event_does_not_validate_session_when_satisfies_requirement_disabled(): void
    {
        config(['leap.auth_passkeys.satisfies_2fa_requirement' => false]);
        $user = $this->createUser();
        $passkey = $user->passkeys()->create([
            'name' => 'Test device',
            'credential_id' => 'fake-credential-id',
            'credential' => ['type' => 'public-key'],
        ]);
        $this->actingAs($user);

        PasskeyVerified::dispatch($user, $passkey);

        $this->assertNull(session('leap.auth_2fa.validated'));
    }

    public function test_auth2fa_challenge_reports_passkey_as_the_method(): void
    {
        config([
            'leap.auth_2fa.required' => true,
            'leap.auth_passkeys.satisfies_2fa_requirement' => true,
        ]);
        $user = $this->createUser();
        $user->passkeys()->create([
            'name' => 'Test device',
            'credential_id' => 'fake-credential-id',
            'credential' => ['type' => 'public-key'],
        ]);
        $this->actingAs($user);

        $method = Livewire::test(Auth2FA::class)
            ->assertOk()
            ->instance()
            ->method;

        $this->assertSame('passkey', $method);
    }

    public function test_auth2fa_challenge_seeds_intended_url_to_home_when_none_captured(): void
    {
        $this->confirmTwoFactorFor($user = $this->createUser());
        $this->actingAs($user);

        // Landing on the challenge page directly (no prior redirect from a
        // protected route) leaves 'url.intended' unset; laravel/passkeys'
        // confirm response falls back to '/' unless we seed a sane default.
        $this->assertFalse(session()->has('url.intended'));

        Livewire::test(Auth2FA::class)->assertOk();

        $this->assertSame(route('leap.home'), session('url.intended'));
    }

    public function test_auth2fa_challenge_does_not_override_a_genuinely_captured_intended_url(): void
    {
        $this->confirmTwoFactorFor($user = $this->createUser());
        $this->actingAs($user);
        session(['url.intended' => route('leap.module.dashboard')]);

        Livewire::test(Auth2FA::class)->assertOk();

        $this->assertSame(route('leap.module.dashboard'), session('url.intended'));
    }
}
