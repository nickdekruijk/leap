<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

class LeapAuth extends Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  string[]  ...$guards
     * @return mixed
     *
     * @throws AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, [config('leap.guard')]);

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        return $request->expectsJson() ? null : route('leap.login');
    }
}
