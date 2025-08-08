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
            // Attribute::make('organization_id')->foreign(scope: 'active', index: 'bedrijf')->index()->label('Organisatie')->placeholder('Selecteer organisatie'),
            Leap::generatePermissionsSection(),
        ];
    }

    public $model = Role::class;
    public $priority = 901;
    public $icon = 'fas-user-lock';

    public $title = 'leap::auth.user_roles';
}
