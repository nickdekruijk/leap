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
     * The available permissions for this module and their default values.
     * A global or organization role with permissions to this module will overrule these.
     *
     * @var array
     */
    protected $default_permissions = [
        'create' => false,
        'read' => false,
        'update' => false,
        'delete' => false,
    ];

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
        Context::addHidden('leap.module', $this::class);

        // If the user has no read permission to this module raise a 404 error because we want to hide the fact that this module exists
        Leap::validatePermission('read', 404);
    }
}
