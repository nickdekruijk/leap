<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Http\Request;
use NickDeKruijk\Leap\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Find all roles the user has
        $roles = auth(config('leap.guard'))->user()->belongsToMany(Role::class, config('leap.table_prefix') . 'role_user');

        // If organizations are enabled, only allow roles with an organization_id
        if (config('leap.organizations')) {
            $role = $roles->whereNotNull('organization_id')->first();
        } else {
            $role = $roles->whereNull('organization_id')->first();
        }

        // If no role was found, return 403
        abort_if(!$role, 403, 'No role found for this user');

        // Store the role in the request attributes so we can use it in the controller
        $request->attributes->add(['leap_role' => $role]);

        return $next($request);
    }
}
