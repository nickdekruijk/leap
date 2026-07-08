<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request and determine if the user has a required role for the app and abort if not authorized.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Find all roles for this user
        $roles = Role::whereHas('users', function (Builder $query) {
            $query->where('user_id', Auth::getUser()->id)->where('accepted', true);
        })->get();

        // Find the user's role
        $role = $roles->first();

        // Set the role as context so we can use it during the request
        Leap::context()->setRoleName($role?->name);

        // If no role was found, return 403
        abort_if(!$role, 403, 'No role found for this user');

        // Make permissions collection for easier access
        $permissions_collection = collect($role->permissions);

        // Determine permissions for each module
        foreach (ModuleController::getAllModules() as $module) {
            $permissions[$module::class]
                = $permissions_collection->where('_name', $module::class)->first()
                ?? $permissions_collection->where('_name', 'all_modules')->first()
                ?? $module->getDefaultPermissions();
        }

        // Set the permissions as context so we can use it during the request
        Leap::context()->setPermissions($permissions);

        return $next($request);
    }
}
