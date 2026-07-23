<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;
use NickDeKruijk\Leap\Traits\CanLog;

class LogUserTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app['config'];
        $config->set('leap.logging.enabled', true);
        $config->set('leap.logging.skip_actions', []);
        $config->set('leap.logging.skip_modules', []);
    }

    private function logger(): object
    {
        return new class
        {
            use CanLog;
        };
    }

    public function test_log_records_the_authenticated_user(): void
    {
        $user = User::create(['name' => 'Nick', 'email' => 'nick@example.com', 'password' => 'secret']);
        Auth::login($user);

        $log = $this->logger()::log('test-action');

        $this->assertSame($user->id, $log->user_id);
    }

    public function test_log_user_id_is_null_when_not_authenticated(): void
    {
        $log = $this->logger()::log('test-action');

        $this->assertNull($log->user_id);
    }

    public function test_log_user_id_is_null_when_the_session_user_no_longer_exists(): void
    {
        // Simulate a stale session (logged in, then the user row is gone — e.g. after
        // a migrate:refresh). Without resolving against the provider this would write
        // the deleted id and violate the user_id foreign key.
        $user = User::create(['name' => 'Ghost', 'email' => 'ghost@example.com', 'password' => 'secret']);
        Auth::loginUsingId($user->id);
        $user->delete();
        Auth::forgetGuards();

        $log = $this->logger()::log('test-action');

        $this->assertNull($log->user_id);
    }

    public function test_log_accepts_a_string_context_while_another_module_is_active(): void
    {
        // Regression: the module-mismatch branch wrote $context['module'] onto a
        // string $context before the string-to-array normalisation, a PHP 8 fatal.
        Leap::context()->setModule('App\\Leap\\SomeOtherModule');

        $log = $this->logger()::log('test-action', 'a plain string context');

        $this->assertSame('App\\Leap\\SomeOtherModule', $log->module);
        $this->assertSame('a plain string context', $log->context['context']);
        $this->assertArrayHasKey('module', $log->context);
    }
}
