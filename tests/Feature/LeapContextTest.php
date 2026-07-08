<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Context;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\LeapContext;
use NickDeKruijk\Leap\Tests\TestCase;

class LeapContextTest extends TestCase
{
    public function test_context_accessor_returns_the_scoped_instance(): void
    {
        $this->assertInstanceOf(LeapContext::class, Leap::context());
        $this->assertSame(Leap::context(), app(LeapContext::class));
    }

    public function test_module_is_stored_and_readable(): void
    {
        Leap::context()->setModule('App\\Leap\\Page');

        $this->assertSame('App\\Leap\\Page', Leap::context()->module());
    }

    public function test_permissions_for_defaults_to_the_active_module(): void
    {
        Leap::context()->setModule('App\\Leap\\Page');
        Leap::context()->setPermissions([
            'App\\Leap\\Page' => ['read' => true],
            'App\\Leap\\Other' => ['read' => false],
        ]);

        $this->assertSame(['read' => true], Leap::context()->permissionsFor());
        $this->assertSame(['read' => false], Leap::context()->permissionsFor('App\\Leap\\Other'));
        $this->assertSame([], Leap::context()->permissionsFor('App\\Leap\\Unknown'));
    }

    public function test_role_name_is_stored_and_readable(): void
    {
        Leap::context()->setRoleName('Superuser');

        $this->assertSame('Superuser', Leap::context()->roleName());
    }

    public function test_values_are_mirrored_to_the_legacy_context_keys_for_backward_compatibility(): void
    {
        Leap::context()->setModule('App\\Leap\\Page');
        Leap::context()->setPermissions(['App\\Leap\\Page' => ['read' => true]]);
        Leap::context()->setRoleName('Superuser');

        $this->assertSame('App\\Leap\\Page', Context::getHidden('leap.module'));
        $this->assertSame(['App\\Leap\\Page' => ['read' => true]], Context::getHidden('leap.permissions'));
        $this->assertSame('Superuser', Context::getHidden('leap.role.name'));
    }
}
