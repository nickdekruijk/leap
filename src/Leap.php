<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Models\Role;

class Leap
{
    /**
     * Name of the binding in the IoC container
     *
     * @return string
     */
    public static function modules()
    {
        return ModuleController::getModules();
    }

    public static function userOrganizations(): Collection
    {
        $user = Auth::user();
        $roles = Role::has('users', $user->id)->get();
        foreach ($roles as $role) {
            if ($role->organization_id) {
                $organizations[$role->organization_id] = $role->organization;
            } else {
                return (new (config('leap.organization_model')))->all();
            }
        }
        return collect($organizations);
    }
}
