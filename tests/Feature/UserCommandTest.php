<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class UserCommandTest extends TestCase
{
    public function test_it_creates_a_user_and_shows_the_generated_password(): void
    {
        // --no-interaction answers the password prompt blank, so the command falls
        // back to a random password. It has to print it: nothing else stores it in
        // the clear, and without it the new account is simply unreachable.
        $this->artisan('leap:user', [
            'email' => 'nick@example.com',
            'name' => 'Nick',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Generated password:')
            ->assertExitCode(0);

        $user = User::where('email', 'nick@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Nick', $user->name);
        $this->assertNotEmpty($user->password);
    }

    public function test_the_shown_password_actually_works(): void
    {
        Artisan::call('leap:user', [
            'email' => 'nick@example.com',
            'name' => 'Nick',
            '--no-interaction' => true,
        ]);

        preg_match('/Generated password: (\S+)/', Artisan::output(), $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'The command did not print a password');

        $user = User::where('email', 'nick@example.com')->firstOrFail();

        $this->assertTrue(Hash::check($matches[1], $user->password), 'The printed password does not open the account');
    }

    public function test_it_warns_when_the_new_user_has_no_role(): void
    {
        Role::create(['name' => 'superuser', 'permissions' => []]);

        // The role prompt defaults to "no", so a non-interactive run leaves a user
        // who cannot see anything in the admin panel. Say so instead of staying mute.
        $this->artisan('leap:user', [
            'email' => 'nick@example.com',
            'name' => 'Nick',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('cannot use the admin panel')
            ->assertExitCode(0);
    }

    public function test_it_survives_having_no_roles_at_all(): void
    {
        Role::query()->delete();

        $this->artisan('leap:user', [
            'email' => 'nick@example.com',
            'name' => 'Nick',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('no roles exist yet')
            ->assertExitCode(0);
    }

    public function test_updating_an_existing_user_leaves_the_password_alone(): void
    {
        $user = User::create([
            'name' => 'Old name',
            'email' => 'nick@example.com',
            'password' => Hash::make('keep-me'),
        ]);

        $this->artisan('leap:user', [
            'email' => 'nick@example.com',
            'name' => 'New name',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $user->refresh();

        $this->assertSame('New name', $user->name);
        $this->assertTrue(Hash::check('keep-me', $user->password), 'A blank prompt must leave the password unchanged');
    }
}
