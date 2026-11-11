<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword as LaravelResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use NickDeKruijk\Leap\Livewire\ForgotPassword;
use NickDeKruijk\Leap\Livewire\ResetPassword;
use NickDeKruijk\Leap\Notifications\ResetPassword as ResetPasswordNotification;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class PasswordResetTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The forgot and reset routes are only registered with the flow switched on.
        $app['config']->set('leap.password_reset', true);
    }

    protected function tearDown(): void
    {
        LaravelResetPassword::$createUrlCallback = null;
        ResetPasswordNotification::$createUrlCallback = null;

        parent::tearDown();
    }

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

    public function test_the_mail_links_to_the_reset_page_of_leap(): void
    {
        Notification::fake();
        $user = $this->createUser();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('submit');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            return $notification->toMail($user)->actionUrl === route('leap.password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ]);
        });
    }

    public function test_a_reset_link_of_the_host_does_not_change_the_one_of_leap(): void
    {
        // A host with a password reset of its own on the frontend customises Laravel's mail.
        LaravelResetPassword::createUrlUsing(fn ($user, string $token): string => url('/account/reset/'.$token));
        $user = $this->createUser();

        $url = (new ResetPasswordNotification('abc'))->toMail($user)->actionUrl;

        $this->assertStringStartsWith(route('leap.password.reset', ['token' => 'abc']), $url);
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
