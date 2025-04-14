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
            $query->where('user_id', Auth::getUser()->id)->where('accepted', true);
        })->get();

        // Find global role
        $global_role = $roles->whereNull('organization_id')->first();

        // If organizations are enabled check role for organization
        if (config('leap.organizations')) {
            // Create organization model instance
            $organizations = new (config('leap.organization_model'));

            // Get navigation label, order and slug attributes
            $label = $organizations->leap_navigation_label ?? 'name';
            $order = $organizations->leap_navigation_order ?? $organizations->leap_navigation_label ?? 'name';
            $slug = $organizations->leap_slug ?? 'slug';

            // If user has a global role get all organizations otherwise only get organizations the user has a role for
            $organizations = $global_role
                ? $organizations->orderBy($order)->get([$slug, $label])
                : $organizations->orderBy($order)->whereIn('id', $roles->pluck('organization_id'))->get();

            // If no organization slug is given, redirect to the user home organization
            if (!$request->route()->organization) {
                abort_if($organizations->isEmpty(), 403, __('No active organization.'));
                return redirect()->route('leap.home', $organizations->first()->slug);
            }

            // Find the current organization
            $organization = $organizations->where($slug, $request->route()->organization)->first();

            // If the organization was not found, return 404
            abort_if(!$organization, 404);
            // Find the role for this organization
            $organization_role = $roles->where('organization_id', $organization->id)->first();

            // If no role was found, return 404 because we want to hide the fact that the organization exists
            abort_if(!$global_role && !$organization_role, 404);

            // Set the available organizations as context so we can use it during the request
            Context::add('leap.organization.slug', $organization->$slug);
            Context::add('leap.organization.label', $organization->$label);
            Context::add('leap.user.organizations', array_map(function ($org) use ($slug, $label) {
                return [
                    'slug' => $org[$slug],
                    'label' => $org[$label],
                ];
            }, $organizations->toArray()));

            // Set the role as context so we can use it during the request
            Context::add('leap.role.name', $global_role?->name . '/' . $organization_role?->name);
        } else {
            // Set the role as context so we can use it during the request
            Context::add('leap.role.name', $global_role?->name);

            // If no role was found, return 403
            abort_if(!$global_role, 403, 'No role found for this user');
        }

        // Determine permissions for each module
        foreach (ModuleController::getAllModules() as $module) {
            if (config('leap.permission_priority') === 'global') {
                $permissions[$module::class]
                    = $global_role->permissions[$module::class] ?? $global_role->permissions['*']
                    ?? $organization_role?->permissions[$module::class] ?? $organization_role?->permissions['*']
                    ?? $module->getDefaultPermissions();
            } elseif (config('leap.permission_priority') === 'organization') {
                $permissions[$module::class]
                    = $organization_role->permissions[$module::class] ?? $organization_role->permissions['*']
                    ?? $global_role?->permissions[$module::class] ?? $global_role?->permissions['*']
                    ?? $module->getDefaultPermissions();
            }
        }

        // Set the permissions as context so we can use it during the request
        Context::add('leap.permissions', $permissions);

        return $next($request);
    }
}
