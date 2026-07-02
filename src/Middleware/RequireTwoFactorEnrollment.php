<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Http\Request;
use NickDeKruijk\Leap\Leap;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorEnrollment
{
    /**
     * Handle an incoming request.
     *
     * When two factor authentication is required and the authenticated user
     * has no method configured yet, block every named Leap route except the
     * profile module (where enrollment happens) and redirect there instead.
     *
     * Livewire's own AJAX update endpoint is intentionally left alone here
     * (its route name never matches a leap.* route); Module::boot() enforces
     * this same rule per-component for that traffic, since it can identify
     * which component is actually being interacted with.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (
            Leap::mustEnrollTwoFactor()
            && $routeName
            && str_starts_with($routeName, 'leap.')
            && $routeName !== 'leap.module.profile'
        ) {
            return redirect()->route('leap.module.profile');
        }

        return $next($request);
    }
}
