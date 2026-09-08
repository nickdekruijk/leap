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
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Find all roles for this user
        $roles = Role::whereHas('users', function (Builder $query) {
            $query->where('user_id', Auth::getUser()->id)->where('accepted', true);
        })->get();

        // Set the role as context so we can use it during the request. The first
        // role names the user; every accepted role counts for permissions below.
        Leap::context()->setRoleName($roles->first()?->name);

        // If no role was found, return 403
        abort_if($roles->isEmpty(), 403, 'No role found for this user');

        // Determine permissions for each module: a permission is granted when any
        // of the user's roles grants it, so a second role can only add access.
        $modules = ModuleController::getAllModules();
        $permissions = [];
        foreach ($roles as $role) {
            $permissions_collection = collect($role->permissions);
            foreach ($modules as $module) {
                $granted = $permissions_collection->where('_name', $module::class)->first()
                    ?? $permissions_collection->where('_name', 'all_modules')->first()
                    ?? $module->getDefaultPermissions();
                foreach ($granted as $ability => $allowed) {
                    if ($ability === '_name') {
                        continue;
                    }
                    $permissions[$module::class][$ability] = ($permissions[$module::class][$ability] ?? false) || (bool) $allowed;
                }
            }
        }

        // Set the permissions as context so we can use it during the request
        Leap::context()->setPermissions($permissions);

        return $next($request);
    }
}
