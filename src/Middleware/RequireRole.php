<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request and determine if the user has a required role for the app or requested organization and abort if not authorized.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Find all roles for this user
        $roles = Role::whereHas('users', function (Builder $query) {
            $query->where('user_id', Auth::getUser()->id);
        })->get();

        // Find global role
        $role = $roles->whereNull('organization_id')->first();

        // If organizations are enabled check role for organization
        if (config('leap.organizations')) {
            // Create organization model instance
            $organizations = new (config('leap.organization_model'));

            // If user has a global role get all organizations otherwise only get organizations the user has a role for
            $organizations = $role
                ? $organizations->all()
                : $organizations->whereIn('id', $roles->pluck('organization_id'));

            // If no organization slug is given, redirect to the user home organization
            if (!$request->route()->organization) {
                return ModuleController::home($organizations->first()->slug);
            }

            // Find the current organization
            $organization = $organizations->where('slug', $request->route()->organization)->first();

            // If the organization was not found, return 404
            abort_if(!$organization, 404);

            // If user doesn't have a global role find the role for this organization
            if (!$role) {
                $role = $roles->where('organization_id', $organization->id)->first();
            }

            // If no role was found, return 404 because we want to hide the fact that the organization exists
            abort_if(!$role, 404);

            // Set the available organizations as context so we can use it during the request
            Context::add('leap.user.organizations', $organizations);
            Context::add('leap.organization', $organization);
        } else {
            // If no role was found, return 403
            abort_if(!$role, 403, 'No role found for this user');
        }

        // Set the role as context so we can use it during the request
        Context::add('leap.user.role', $role);

        return $next($request);
    }
}
