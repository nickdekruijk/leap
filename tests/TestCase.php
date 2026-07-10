<?php

namespace NickDeKruijk\Leap\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Laravel\ServiceProvider as ImageServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passkeys\PasskeysServiceProvider;
use Livewire\LivewireServiceProvider;
use NickDeKruijk\Leap\ServiceProvider;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FortifyServiceProvider::class,
            PasskeysServiceProvider::class,
            ImageServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        // Use an in-memory sqlite database for the test suite
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Point the default auth provider at the test User fixture so
        // Leap::userModel() resolves to a model backed by the users table.
        $config->set('auth.providers.users.model', User::class);

        $config->set('leap.migrations', true);

        // The email 2FA method defaults to disabled in the shipped config
        // (it depends on mail being configured); the test suite exercises
        // the feature regardless of that default.
        $config->set('leap.auth_2fa.email.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Create the users table before running the package migrations, which
        // reference it (the seeding migration attaches the first user to the
        // default role).
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        $this->artisan('migrate')->run();
    }
}
