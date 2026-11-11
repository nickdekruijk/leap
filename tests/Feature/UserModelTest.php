<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use LogicException;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Leap::userModel() is a new instance of the model the auth provider is configured with,
 * which only the Eloquent provider has.
 */
class UserModelTest extends TestCase
{
    public function test_it_is_an_instance_of_the_configured_model(): void
    {
        $this->assertInstanceOf(User::class, Leap::userModel());
    }

    public function test_a_database_provider_is_refused_with_a_message_that_says_why(): void
    {
        config()->set('auth.providers.users', ['driver' => 'database', 'table' => 'users']);
        app('auth')->forgetGuards();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Eloquent user provider');

        Leap::userModel();
    }
}
