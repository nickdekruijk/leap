<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Context;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use NickDeKruijk\Leap\Livewire\Auth2FA;
use NickDeKruijk\Leap\Livewire\Profile;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorTest extends TestCase
{
    private function createUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->roles()->attach(Role::find(1));

        return $user;
    }

    /**
     * Give the current request full permissions on the Profile module so the
     * Livewire component's read/update gate passes (normally set by RequireRole).
     */
    private function grantTwoFactorPermissions(): void
    {
        Context::addHidden('leap.permissions', [
            Profile::class => ['read' => true, 'update' => true],
        ]);
    }

    private function currentOtp(User $user): string
    {
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        return (new Google2FA)->getCurrentOtp($secret);
    }

    public function test_enable_generates_secret_and_recovery_codes_without_confirming(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactor');

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
    }

    public function test_confirm_with_valid_code_activates_two_factor(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactor');
        $user->refresh();

        Livewire::test(Profile::class)
            ->set('confirmCode', $this->currentOtp($user))
            ->call('confirmTwoFactor')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
    }

    public function test_confirm_with_invalid_code_shows_error(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactor');

        Livewire::test(Profile::class)
            ->set('confirmCode', '000000')
            ->call('confirmTwoFactor')
            ->assertHasErrors('confirmCode');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_disable_clears_two_factor(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        (new EnableTwoFactorAuthentication(app(\Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class)))($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        Livewire::test(Profile::class)->call('disableTwoFactor');

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_user_with_confirmed_two_factor_is_challenged(): void
    {
        $user = $this->createUser();
        (new EnableTwoFactorAuthentication(app(\Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class)))($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $response = $this->actingAs($user)->get(route('leap.home'));

        $response->assertRedirect(route('leap.auth_2fa'));
    }

    public function test_valid_totp_passes_the_challenge(): void
    {
        $user = $this->createUser();
        (new EnableTwoFactorAuthentication(app(\Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class)))($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user);

        Livewire::test(Auth2FA::class)
            ->set('code', $this->currentOtp($user))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('leap.home'));

        $this->assertTrue(session('leap.auth_2fa.validated'));
    }

    public function test_recovery_code_passes_the_challenge_and_is_consumed(): void
    {
        $user = $this->createUser();
        (new EnableTwoFactorAuthentication(app(\Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class)))($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user);

        $recoveryCode = $user->recoveryCodes()[0];

        Livewire::test(Auth2FA::class)
            ->set('code', $recoveryCode)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('leap.home'));

        $this->assertTrue(session('leap.auth_2fa.validated'));
        // The used recovery code should have been replaced
        $this->assertNotContains($recoveryCode, $user->fresh()->recoveryCodes());
    }

    public function test_invalid_challenge_code_is_rejected(): void
    {
        $user = $this->createUser();
        (new EnableTwoFactorAuthentication(app(\Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class)))($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user);

        Livewire::test(Auth2FA::class)
            ->set('code', '000000')
            ->call('submit')
            ->assertHasErrors('code');

        $this->assertNull(session('leap.auth_2fa.validated'));
    }
}
