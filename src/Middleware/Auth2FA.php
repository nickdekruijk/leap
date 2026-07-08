<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Http\Request;
use NickDeKruijk\Leap\Leap;
use Symfony\Component\HttpFoundation\Response;

class Auth2FA
{
    /**
     * Handle an incoming request.
     *
     * When two factor authentication is enabled and the authenticated user has
     * confirmed two factor authentication, they must pass the challenge before
     * accessing any Leap route.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Leap::mustValidateTwoFactor()) {
            return redirect()->route('leap.auth_2fa');
        }

        return $next($request);
    }
}
