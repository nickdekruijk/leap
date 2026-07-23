<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Livewire\Roles as RolesModule;
use NickDeKruijk\Leap\Livewire\User as UserModule;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\HashingUser;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The two modules that hand out access to the panel itself. A mistake here is a
 * mistake about who may log in and what they may do, so the properties worth
 * pinning down are about the password never leaking and a role never being
 * optional — not about the form rendering.
 */
class UserManagementTest extends TestCase
{
    private function actingAsSuperuser(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        return $user;
    }

    /**
     * Give the request full rights on the module under test, as RequireRole would.
     */
    private function grantAll(string $module): void
    {
        Leap::context()->setModule($module);
        Leap::context()->setPermissions([
            $module => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    private function editor(): Editor
    {
        return Livewire::test(Editor::class)->instance();
    }

    private function attribute(object $module, string $name): Attribute
    {
        return collect($module->attributes())->firstWhere('name', $name);
    }

    /**
     * The module follows the configured auth provider rather than guessing at
     * App\Models\User, so a project authenticating some other class still gets a
     * working Users module.
     */
    public function test_the_user_module_edits_the_configured_auth_provider_model(): void
    {
        $this->actingAsSuperuser();

        $this->assertSame(Leap::userModel()::class, (new UserModule)->getModel()::class);
        $this->assertSame(User::class, (new UserModule)->getModel()::class);
    }

    /**
     * Opening a user must not hand the browser the stored hash. The editor blanks
     * every password attribute on load, so the field arrives empty and the hash
     * never appears in the Livewire payload.
     */
    public function test_opening_a_user_never_sends_the_password_hash_to_the_browser(): void
    {
        $this->actingAsSuperuser();
        $this->grantAll(UserModule::class);

        $target = User::create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('hunter2'),
        ]);

        $editor = $this->editor();
        $editor->openEditor($target->id);

        $this->assertNull($editor->data['password']);
        $this->assertStringNotContainsString('$2y$', json_encode($editor->data));
    }

    /**
     * Leaving the password blank on save means "leave it alone", not "set it to
     * empty" — otherwise every edit of someone's name would lock them out.
     */
    public function test_saving_with_an_empty_password_keeps_the_existing_one(): void
    {
        $this->actingAsSuperuser();
        $this->grantAll(UserModule::class);

        $target = User::create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('hunter2'),
        ]);
        $target->roles()->attach(Role::find(1));
        $originalHash = $target->password;

        $editor = $this->editor();
        $editor->openEditor($target->id);
        $editor->data['name'] = 'Editor Renamed';
        $editor->save();

        $target->refresh();

        $this->assertSame('Editor Renamed', $target->name);
        $this->assertSame($originalHash, $target->password);
        $this->assertTrue(Hash::check('hunter2', $target->password));
    }

    public function test_a_new_password_is_stored_hashed_and_never_in_plain_text(): void
    {
        $this->actingAsSuperuser();
        $this->grantAll(UserModule::class);

        $target = User::create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('hunter2'),
        ]);
        $target->roles()->attach(Role::find(1));

        $editor = $this->editor();
        $editor->openEditor($target->id);
        $editor->data['password'] = 'a-brand-new-secret';
        $editor->data['password_confirmation'] = 'a-brand-new-secret';
        $editor->save();

        $target->refresh();

        $this->assertNotSame('a-brand-new-secret', $target->password);
        $this->assertTrue(Hash::check('a-brand-new-secret', $target->password));
    }

    /**
     * A model that already casts 'password' => 'hashed' must be left to do it, or
     * the value would be hashed twice and nobody could log in with it.
     */
    public function test_a_model_that_casts_the_password_is_not_hashed_twice(): void
    {
        $this->actingAsSuperuser();
        $this->grantAll(UserModule::class);

        $target = HashingUser::create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => 'hunter2',
        ]);
        $target->roles()->attach(Role::find(1));

        config()->set('auth.providers.users.model', HashingUser::class);

        $editor = $this->editor();
        $editor->openEditor($target->id);
        $editor->data['password'] = 'a-brand-new-secret';
        $editor->data['password_confirmation'] = 'a-brand-new-secret';
        $editor->save();

        $target->refresh();

        $this->assertTrue(
            Hash::check('a-brand-new-secret', $target->password),
            'The password was hashed twice, so it can never be verified.'
        );
    }

    /**
     * A password field without confirmation lets a typo through, and the person
     * it belongs to is the one locked out by it.
     */
    public function test_the_password_attribute_requires_confirmation(): void
    {
        $this->actingAsSuperuser();

        $password = $this->attribute(new UserModule, 'password');

        $this->assertSame('password', $password->type);
        $this->assertNotNull($password->confirmed);
    }

    /**
     * Roles are required: a user saved without one can log in but reaches nothing,
     * which reads as a broken panel rather than as an account still being set up.
     */
    public function test_a_user_must_be_given_at_least_one_role(): void
    {
        $this->actingAsSuperuser();

        $roles = $this->attribute(new UserModule, 'roles');

        $this->assertSame('pivot', $roles->type);
        $this->assertContains('required', $roles->validate);
    }

    public function test_the_email_attribute_is_unique_so_two_accounts_cannot_share_a_login(): void
    {
        $this->actingAsSuperuser();

        $rules = $this->attribute(new UserModule, 'email')->validate;

        $this->assertNotEmpty(
            array_filter($rules, fn (string $rule): bool => str_starts_with($rule, 'unique:')),
            'Without a unique rule two accounts can claim the same login address.'
        );
    }

    public function test_saving_a_user_syncs_the_selected_roles(): void
    {
        $this->actingAsSuperuser();
        $this->grantAll(UserModule::class);

        $second = Role::create(['name' => 'Editors']);
        $target = User::create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('hunter2'),
        ]);
        $target->roles()->attach(Role::find(1));

        $editor = $this->editor();
        $editor->openEditor($target->id);
        $editor->data['roles'] = [$second->id];
        $editor->save();

        $this->assertSame([$second->id], $target->roles()->pluck('id')->all());
    }

    public function test_the_roles_module_edits_the_leap_role_model(): void
    {
        $this->actingAsSuperuser();

        $this->assertSame(Role::class, (new RolesModule)->getModel()::class);
    }

    /**
     * The permission matrix is generated from the registered modules rather than
     * stored as a fixed list, so a newly added module shows up on the role form
     * without a migration.
     */
    public function test_the_roles_module_generates_a_permissions_section(): void
    {
        $this->actingAsSuperuser();

        $names = collect((new RolesModule)->attributes())->pluck('name');

        $this->assertTrue($names->contains('name'));
        $this->assertGreaterThan(1, $names->count(), 'The generated permissions section is missing.');
    }

    public function test_a_role_name_is_required(): void
    {
        $this->actingAsSuperuser();

        $this->assertContains('required', $this->attribute(new RolesModule, 'name')->validate);
    }
}
