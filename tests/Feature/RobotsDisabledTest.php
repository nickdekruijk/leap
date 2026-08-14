<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Leap claims an address at the root of the site, which is a thing to be able to take
 * back: a project that answers /robots.txt itself, or that keeps a file in public/,
 * should be able to switch the route off rather than work around it.
 */
class RobotsDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.robots.enabled', false);
    }

    public function test_no_route_is_registered(): void
    {
        $this->assertFalse(Route::has('leap.robots'));
    }

    /**
     * Off means the project's own route is the one that answers, not that leap's is
     * still there and merely says something else.
     */
    public function test_the_project_can_answer_it_instead(): void
    {
        Route::get('robots.txt', fn () => 'mine');

        $this->assertSame('mine', $this->get('/robots.txt')->getContent());
    }
}
