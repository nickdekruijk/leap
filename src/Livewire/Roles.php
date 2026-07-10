<?php

namespace NickDeKruijk\Leap\Livewire;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Resource;

class Roles extends Resource
{
    public function attributes()
    {
        return [
            Attribute::make('name')->index()->required()->label(__('leap::auth.name')),
            Leap::generatePermissionsSection(),
        ];
    }

    public $model = Role::class;

    public $priority = 901;

    public $icon = 'fas-user-lock';

    public $orderBy = 'name';

    public $showIndexGroups = false;

    public $title = 'leap::auth.user_roles';
}
