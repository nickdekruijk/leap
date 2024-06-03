<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use NickDeKruijk\Leap\Traits\CanLog;
use NickDeKruijk\Leap\Traits\NavigationItem;

class Module extends Component
{
    use CanLog;
    use NavigationItem;

    /**
     * The default permissions for this module, a global or organization role with permissions to this module will overrule these.
     * When set to an empty array [] users won't be able to access unless permissions are set by a role.
     * Example of a valid permissions array: ['create', 'read', update', 'delete']
     *
     * @var array
     */
    public $default_permissions = [];

    /**
     * Return the default_permissions for this module.
     *
     * @return array
     */
    public function getDefaultPermissions(): array
    {
        return $this->default_permissions;
    }

    public function __construct($options = [])
    {
        Auth::shouldUse(config('leap.guard'));
        // Overide default options
        foreach ($options as $option => $value) {
            $this->$option = $value;
        }
    }

    public function boot()
    {
        // Add this module to the context so we can use it during the request
        Context::add('leap.module', $this::class);

        // If the user has no read permission to this module raise a 404 error because we want to hide the fact that this module exists
        abort_if(Gate::denies('leap::read'), 404);
    }
}
