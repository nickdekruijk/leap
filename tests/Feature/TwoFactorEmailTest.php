<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Livewire;
use NickDeKruijk\Leap\Actions\SendTwoFactorEmailCode;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Auth2FA;
use NickDeKruijk\Leap\Livewire\Profile;
use NickDeKruijk\Leap\Mail\TwoFactorCodeMail;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class TwoFactorEmailTest extends TestCase
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
    private function grantTwoFactorPermissions(): void
    {
        Context::addHidden('leap.permissions', [
            Profile::class => ['read' => true, 'update' => true],
        ]);
    }

    private function pendingCode(User $user): ?string
    {
        return Cache::get(SendTwoFactorEmailCode::cacheKey($user));
    }

    public function test_enable_email_sends_code(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactorEmail');

        $user->refresh();
        Mail::assertSent(TwoFactorCodeMail::class, 1);
        $this->assertNull($user->two_factor_email_confirmed_at);
        $this->assertNotNull($this->pendingCode($user));
    }

    public function test_confirm_email_with_valid_code_activates_two_factor(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactorEmail');
        $code = $this->pendingCode($user);
        $user->refresh();

        Livewire::test(Profile::class)
            ->set('confirmCode', $code)
            ->call('confirmTwoFactorEmail')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->two_factor_email_confirmed_at);
        $this->assertSame('email', Leap::twoFactorMethod($user));
    }

    public function test_confirm_email_with_invalid_code_shows_error(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactorEmail');
        $user->refresh();

        Livewire::test(Profile::class)
            ->set('confirmCode', '000000')
            ->call('confirmTwoFactorEmail')
            ->assertHasErrors('confirmCode');

        $this->assertNull($user->fresh()->two_factor_email_confirmed_at);
    }

    public function test_confirm_email_with_expired_code_is_rejected(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactorEmail');
        $code = $this->pendingCode($user);
        $user->refresh();

        Cache::forget(SendTwoFactorEmailCode::cacheKey($user));

        Livewire::test(Profile::class)
            ->set('confirmCode', $code)
            ->call('confirmTwoFactorEmail')
            ->assertHasErrors('confirmCode');

        $this->assertNull($user->fresh()->two_factor_email_confirmed_at);
    }

    public function test_enabling_email_disables_existing_totp(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        (new EnableTwoFactorAuthentication(app(TwoFactorAuthenticationProvider::class)))($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        Livewire::test(Profile::class)->call('enableTwoFactorEmail');

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_enabling_totp_disables_existing_email_method(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactorEmail');
        $code = $this->pendingCode($user);
        $user->refresh();
        Livewire::test(Profile::class)->set('confirmCode', $code)->call('confirmTwoFactorEmail');
        $this->assertNotNull($user->fresh()->two_factor_email_confirmed_at);
        $user->refresh();

        Livewire::test(Profile::class)->call('enableTwoFactor');

        $this->assertNull($user->fresh()->two_factor_email_confirmed_at);
    }

    public function test_disable_email_clears_confirmed_at_and_pending_code(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactorEmail');
        $code = $this->pendingCode($user);
        $user->refresh();
        Livewire::test(Profile::class)->set('confirmCode', $code)->call('confirmTwoFactorEmail');
        $this->assertNotNull($user->fresh()->two_factor_email_confirmed_at);
        $user->refresh();

        Livewire::test(Profile::class)->call('disableTwoFactorEmail');

        $user->refresh();
        $this->assertNull($user->two_factor_email_confirmed_at);
        $this->assertNull($this->pendingCode($user));
    }

    public function test_resend_email_code_in_profile_is_rate_limited(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantTwoFactorPermissions();

        Livewire::test(Profile::class)->call('enableTwoFactorEmail');
        $user->refresh();
        Livewire::test(Profile::class)->call('resendTwoFactorEmail')->assertHasNoErrors();
        $user->refresh();
        Livewire::test(Profile::class)->call('resendTwoFactorEmail')->assertHasErrors('confirmCode');

        Mail::assertSent(TwoFactorCodeMail::class, 2);
    }

    public function test_user_with_confirmed_email_two_factor_is_challenged(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $user->forceFill(['two_factor_email_confirmed_at' => now()])->save();

        $response = $this->actingAs($user)->get(route('leap.home'));

        $response->assertRedirect(route('leap.auth_2fa'));
    }

    public function test_login_challenge_sends_initial_email_code_once(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $user->forceFill(['two_factor_email_confirmed_at' => now()])->save();
        $this->actingAs($user);

        Livewire::test(Auth2FA::class);
        Livewire::test(Auth2FA::class);

        Mail::assertSent(TwoFactorCodeMail::class, 1);
    }

    public function test_valid_emailed_code_passes_the_challenge(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $user->forceFill(['two_factor_email_confirmed_at' => now()])->save();
        $this->actingAs($user);

        Livewire::test(Auth2FA::class)
            ->set('code', $this->pendingCode($user))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('leap.home'));

        $this->assertTrue(session('leap.auth_2fa.validated'));
    }

    public function test_email_method_has_no_recovery_codes(): void
    {
        // The email method has no recovery codes of its own (resending a
        // fresh code covers the same "lost my code" case). An unrelated,
        // wrong code must be rejected without crashing on the null column.
        Mail::fake();

        $user = $this->createUser();
        $user->forceFill(['two_factor_email_confirmed_at' => now()])->save();
        $this->actingAs($user);

        $this->assertNull($user->two_factor_recovery_codes);

        Livewire::test(Auth2FA::class)
            ->set('code', '000000')
            ->call('submit')
            ->assertHasErrors('code');

        $this->assertNull(session('leap.auth_2fa.validated'));
    }

    public function test_resend_button_on_login_challenge_is_rate_limited(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $user->forceFill(['two_factor_email_confirmed_at' => now()])->save();
        $this->actingAs($user);

        Livewire::test(Auth2FA::class)->call('resend')->assertHasNoErrors();
        Livewire::test(Auth2FA::class)->call('resend')->assertHasErrors('code');
    }
}
