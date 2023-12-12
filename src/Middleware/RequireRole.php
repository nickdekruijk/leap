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
        $user_id = auth(config('leap.guard'))->user()->id;

        // Find all roles for this user
        $roles = Role::has('users', $user_id)->get();

        // Find global role
        $role = $roles->whereNull('organization_id')->first();

        // If organizations are enabled check role for organization
        if (config('leap.organizations')) {
            // Find the organization
            $organization = (new (config('leap.organization_model')))->where('slug', $request->route()->organization)->first();

            // If the organization was not found, return 404
            abort_if(!$organization, 404);

            // Find the users role for this organization or keep global role
            $role = $roles->where('organization_id', $organization->id)->first() ?: $role;

            // If no role was found, return 404 because we want to hide the fact that the organization exists
            abort_if(!$role, 404);

            // Add the organization to the role so we can use it later
            $role->organization = $organization;
        } else {
            // If no role was found, return 403
            abort_if(!$role, 403, 'No role found for this user');
        }

        // Store the role in the session so we can use it in the controller
        session(['leap.role' => $role]);

        return $next($request);
    }
}
