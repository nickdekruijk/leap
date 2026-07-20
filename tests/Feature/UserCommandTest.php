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

    public function test_the_role_option_assigns_a_role_without_a_prompt(): void
    {
        // The whole point of --role: an installer or a script has nobody to answer the
        // y/n question, and a user without a role cannot open the admin panel at all.
        $this->artisan('leap:user', [
            'email' => 'nick@example.com',
            '--role' => null,
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('the "superuser" role')
            ->assertExitCode(0);

        $user = User::where('email', 'nick@example.com')->firstOrFail();

        $this->assertSame(1, $user->roles()->wherePivot('accepted', true)->count());
    }

    public function test_the_role_option_accepts_a_name_and_an_id(): void
    {
        $this->artisan('leap:user', ['email' => 'byname@example.com', '--role' => 'superuser', '--no-interaction' => true])
            ->assertExitCode(0);
        $this->artisan('leap:user', ['email' => 'byid@example.com', '--role' => '1', '--no-interaction' => true])
            ->assertExitCode(0);

        foreach (['byname@example.com', 'byid@example.com'] as $email) {
            $this->assertSame(1, User::where('email', $email)->firstOrFail()->roles()->wherePivot('accepted', true)->count());
        }
    }

    public function test_an_unknown_role_fails_instead_of_leaving_a_useless_account(): void
    {
        $this->artisan('leap:user', [
            'email' => 'nick@example.com',
            '--role' => 'editors',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('No role "editors" found')
            ->assertExitCode(1);

        $this->assertSame(0, User::where('email', 'nick@example.com')->firstOrFail()->roles()->count());
    }

    public function test_the_role_option_accepts_a_pending_invitation(): void
    {
        // A pivot row with accepted = false is invisible to RequireRole, so the user is
        // still locked out — and it already holds the composite primary key, so a plain
        // attach would collide.
        $user = User::create(['name' => 'Nick', 'email' => 'nick@example.com', 'password' => Hash::make('secret')]);
        $user->roles()->attach(Role::findOrFail(1), ['accepted' => false]);

        $this->artisan('leap:user', [
            'email' => 'nick@example.com',
            '--role' => null,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, $user->roles()->wherePivot('accepted', true)->count());
    }

    public function test_it_fails_clearly_when_run_without_an_email_and_without_a_prompt(): void
    {
        // Prompts throws NonInteractiveValidationException on a required question it
        // cannot ask. Say what is missing instead of dumping a stack trace.
        $this->artisan('leap:user', ['--no-interaction' => true])
            ->expectsOutputToContain('No e-mail address given')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_the_name_defaults_to_the_email_when_not_prompted(): void
    {
        $this->artisan('leap:user', [
            'email' => 'nick@example.com',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertSame('Nick', User::where('email', 'nick@example.com')->firstOrFail()->name);
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
