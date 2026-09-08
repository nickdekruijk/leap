<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Livewire\Profile;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    private function createUser(array $extra = [], bool $superuser = true): User
    {
        $user = User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ], $extra));

        if ($superuser) {
            $user->roles()->attach(Role::find(1));
        }

        return $user;
    }

    private function grant(string $module): void
    {
        Leap::context()->setModule($module)->setPermissions([
            $module => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    // H-A: renaming may not step outside the upload allowlist

    public function test_rename_cannot_give_a_file_a_disallowed_extension(): void
    {
        config(['leap.filemanager.allowed_extensions' => ['pdf', 'jpg']]);
        Storage::fake('public');
        Storage::disk('public')->put('doc.pdf', '%PDF-1.4 <?php echo 1; ?>');
        $this->actingAs($this->createUser());
        $this->grant(FileManager::class);

        Livewire::test(FileManager::class)
            ->set('selectedFiles', ['doc.pdf'])
            ->set('newFileName', 'shell.php')
            ->call('renameSelectedFile')
            ->assertDispatched('toast-error');

        Storage::disk('public')->assertExists('doc.pdf');
        Storage::disk('public')->assertMissing('shell.php');
    }

    public function test_rename_to_an_allowed_extension_still_works(): void
    {
        config(['leap.filemanager.allowed_extensions' => ['pdf', 'jpg']]);
        Storage::fake('public');
        Storage::disk('public')->put('doc.pdf', 'x');
        $this->actingAs($this->createUser());
        $this->grant(FileManager::class);

        Livewire::test(FileManager::class)
            ->set('selectedFiles', ['doc.pdf'])
            ->set('newFileName', 'renamed.pdf')
            ->call('renameSelectedFile')
            ->assertNotDispatched('toast-error');

        Storage::disk('public')->assertExists('renamed.pdf');
    }

    // M-C: SMIL attribute injection survives a regex sanitiser

    public function test_sanitize_svg_strips_smil_injection(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            .'<set attributeName="onload" to="alert(1)"/>'
            .'<a><animate attributeName="href" values="javascript:alert(2)"/><text>x</text></a>'
            .'<rect width="10" height="10" fill="red"/>'
            .'</svg>';

        $clean = FileManager::sanitizeSvg($svg);

        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('fill="red"', $clean);
    }

    // H-B: passkey management needs a confirmed password and is throttled

    public function test_passkey_management_requires_a_confirmed_password(): void
    {
        $this->actingAs($this->createUser());

        $this->getJson('/user/passkeys/options')->assertStatus(423);
    }

    public function test_passkey_management_opens_after_confirming_the_password(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        Leap::context()->setPermissions([Profile::class => ['read' => true, 'update' => true]]);

        Livewire::test(Profile::class)
            ->set('confirmationPassword', 'wrong')
            ->call('confirmPassword')
            ->assertHasErrors(['confirmationPassword']);
        $this->assertNull(session('auth.password_confirmed_at'));

        Livewire::test(Profile::class)
            ->set('confirmationPassword', 'password')
            ->call('confirmPassword')
            ->assertHasNoErrors();
        $this->assertNotNull(session('auth.password_confirmed_at'));

        $this->getJson('/user/passkeys/options')->assertOk();
    }

    public function test_passkey_routes_are_throttled_even_without_a_fortify_limiter(): void
    {
        $this->assertSame('throttle:6,1', config('passkeys.throttle'));

        $middleware = collect(app('router')->getRoutes()->getByName('passkey.store')->gatherMiddleware());
        $this->assertTrue($middleware->contains(fn ($m) => str_starts_with($m, 'throttle:')), $middleware->implode(', '));
        $this->assertTrue($middleware->contains(fn ($m) => str_starts_with($m, 'password.confirm')), $middleware->implode(', '));
    }

    // L-8: every accepted role counts, permissions are combined

    public function test_permissions_are_combined_across_roles(): void
    {
        $reader = Role::create(['name' => 'reader', 'permissions' => [['_name' => Dashboard::class, 'read' => true, 'create' => false, 'update' => false, 'delete' => false]]]);
        $editor = Role::create(['name' => 'editor', 'permissions' => [['_name' => Dashboard::class, 'read' => false, 'create' => false, 'update' => true, 'delete' => false]]]);
        $user = $this->createUser(superuser: false);
        $user->roles()->attach([$reader->id, $editor->id]);

        // leap.home redirects to the first readable module; the middleware has run by then.
        $this->actingAs($user)->get(route('leap.home'));

        $permissions = Leap::context()->permissionsFor(Dashboard::class);
        $this->assertTrue($permissions['read']);
        $this->assertTrue($permissions['update']);
        $this->assertFalse($permissions['delete']);
        $this->assertSame('reader', Leap::context()->roleName());
    }

    // L-4: a name column that is also the login name stays unique

    public function test_profile_rejects_a_name_another_user_logs_in_with(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('username')->nullable());
        config()->set('leap.name_column', 'username');
        config()->set('leap.credentials', ['username', 'password']);
        $this->createUser()->forceFill(['username' => 'taken'])->save();
        $user = $this->createUser();
        $user->forceFill(['username' => 'mine'])->save();
        $this->actingAs($user);
        Leap::context()->setPermissions([Profile::class => ['read' => true, 'update' => true]]);

        Livewire::test(Profile::class)
            ->set('data.name', 'taken')
            ->call('submit')
            ->assertHasErrors(['data.name']);

        $this->assertSame('mine', $user->fresh()->username);
    }
}
