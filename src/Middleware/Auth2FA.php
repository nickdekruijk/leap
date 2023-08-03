<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Http\Request;
use NickDeKruijk\Leap\Controllers\Auth2FAController;
use Symfony\Component\HttpFoundation\Response;

class Auth2FA
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth2FAController::mustValidate()) {
            return redirect()->route('leap.auth_2fa');
        }
        return $next($request);
    }
}
