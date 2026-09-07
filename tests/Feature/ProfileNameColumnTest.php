<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Profile;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class ProfileNameColumnTest extends TestCase
{
    private function createUser(array $extra = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ], $extra));
        $user->roles()->attach(Role::find(1));

        return $user;
    }

    private function grantProfilePermissions(): void
    {
        Leap::context()->setPermissions([
            Profile::class => ['read' => true, 'update' => true],
        ]);
    }

    public function test_profile_shows_and_edits_the_name_column_by_default(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->grantProfilePermissions();

        Livewire::test(Profile::class)
            ->assertSet('title', 'Test User')
            ->assertSee(__('leap::auth.name'))
            ->set('data.name', 'Renamed User')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame('Renamed User', $user->fresh()->name);
    }

    public function test_profile_follows_a_configured_name_column(): void
    {
        // A host without a name column points the profile at another one.
        Schema::table('users', fn (Blueprint $table) => $table->string('username')->nullable());
        config()->set('leap.name_column', 'username');
        $user = $this->createUser();
        $user->forceFill(['username' => 'tester'])->save();
        $this->actingAs($user);
        $this->grantProfilePermissions();

        Livewire::test(Profile::class)
            ->assertSet('title', 'tester')
            ->assertSee(__('leap::auth.username'))
            ->assertDontSee(__('leap::auth.name'))
            ->set('data.name', 'tester2')
            ->call('submit')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('tester2', $user->username);
        $this->assertSame('Test User', $user->name);
    }
}
