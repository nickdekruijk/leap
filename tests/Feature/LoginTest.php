<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NickDeKruijk\Leap\Livewire\Login;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class LoginTest extends TestCase
{
    private function user(array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => Hash::make('secret123'),
        ], $extra));
    }

    public function test_login_with_the_default_email_credentials(): void
    {
        $user = $this->user();

        Livewire::test(Login::class)
            ->set('credentials.email', $user->email)
            ->set('credentials.password', 'secret123')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('leap.home'));

        $this->assertTrue(Auth::guard(config('leap.guard'))->check());
    }

    public function test_wrong_password_reports_on_the_password_field(): void
    {
        $user = $this->user();

        Livewire::test(Login::class)
            ->set('credentials.email', $user->email)
            ->set('credentials.password', 'wrong')
            ->call('submit')
            ->assertHasErrors(['credentials.password']);

        $this->assertFalse(Auth::guard(config('leap.guard'))->check());
    }

    public function test_empty_form_is_validated_with_readable_labels(): void
    {
        Livewire::test(Login::class)
            ->call('submit')
            ->assertHasErrors(['credentials.email', 'credentials.password'])
            ->assertSee(__('validation.required', ['attribute' => __('leap::auth.email')]));
    }

    public function test_login_with_another_credential_column(): void
    {
        // A host that authenticates on a username names it in config; the form follows.
        Schema::table('users', fn (Blueprint $table) => $table->string('username')->nullable());
        config()->set('leap.credentials', ['username', 'password']);
        $user = $this->user();
        $user->forceFill(['username' => 'tester'])->save();

        Livewire::test(Login::class)
            ->assertSee(__('leap::auth.username'))
            ->set('credentials.username', 'tester')
            ->set('credentials.password', 'secret123')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('leap.home'));

        $this->assertTrue(Auth::guard(config('leap.guard'))->check());
    }
}
