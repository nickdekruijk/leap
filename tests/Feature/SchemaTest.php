<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\TestCase;

class SchemaTest extends TestCase
{
    public function test_leap_role_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('leap_roles'));
        $this->assertTrue(Schema::hasTable('leap_role_user'));
    }

    public function test_role_tables_have_no_organization_columns(): void
    {
        $this->assertFalse(
            Schema::hasColumn('leap_roles', 'organization_id'),
            'leap_roles should not have an organization_id column after removing organization support'
        );
        $this->assertFalse(
            Schema::hasColumn('leap_role_user', 'organization_id'),
            'leap_role_user should not have an organization_id column after removing organization support'
        );
    }

    public function test_superuser_role_is_seeded_with_full_permissions(): void
    {
        $role = Role::find(1);

        $this->assertNotNull($role);
        $this->assertSame('superuser', $role->name);
        $this->assertSame('all_modules', $role->permissions[0]['_name']);
        $this->assertTrue($role->permissions[0]['all_permissions']);
    }
}
