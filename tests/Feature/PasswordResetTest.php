<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use NickDeKruijk\Leap\Livewire\ForgotPassword;
use NickDeKruijk\Leap\Livewire\ResetPassword;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class PasswordResetTest extends TestCase
{
    private function createUser(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();
        $user = $this->createUser();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('status', __('leap::auth.password_reset_sent'));

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_for_unknown_email_does_not_leak(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'unknown@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('status', __('leap::auth.password_reset_sent'));

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'NewSecret123!')
            ->set('password_confirmation', 'NewSecret123!')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('leap.login'));

        $this->assertTrue(Hash::check('NewSecret123!', $user->fresh()->password));
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $user = $this->createUser();

        Livewire::test(ResetPassword::class, ['token' => 'invalid-token'])
            ->set('email', $user->email)
            ->set('password', 'NewSecret123!')
            ->set('password_confirmation', 'NewSecret123!')
            ->call('submit')
            ->assertHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
