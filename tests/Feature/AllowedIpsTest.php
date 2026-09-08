<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class AllowedIpsTest extends TestCase
{
    private function superuser(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin'.uniqid().'@example.com', 'password' => bcrypt('password')]);
        $user->roles()->attach(Role::find(1));

        return $user;
    }

    public function test_no_list_means_no_restriction(): void
    {
        config()->set('leap.allowed_ips', []);

        $this->get(route('leap.login'))->assertOk();
    }

    public function test_addresses_outside_the_list_get_a_404_even_on_the_login_screen(): void
    {
        config()->set('leap.allowed_ips', ['10.0.0.0/8', '203.0.113.4']);

        $this->get(route('leap.login'))->assertNotFound();
        $this->actingAs($this->superuser())->get(route('leap.home'))->assertNotFound();
    }

    public function test_addresses_in_the_list_pass(): void
    {
        config()->set('leap.allowed_ips', ['10.0.0.0/8', '127.0.0.1']);

        $this->get(route('leap.login'))->assertOk();
        $this->actingAs($this->superuser())->get(route('leap.home'))->assertRedirect();
    }

    public function test_the_env_value_is_split_and_trimmed(): void
    {
        $list = array_values(array_filter(array_map('trim', explode(',', ' 203.0.113.4, 198.51.100.0/24 ,'))));

        $this->assertSame(['203.0.113.4', '198.51.100.0/24'], $list);
    }
}
