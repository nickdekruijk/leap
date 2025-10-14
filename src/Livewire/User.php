<?php

namespace NickDeKruijk\Leap\Livewire;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Resource;

class User extends Resource
{
    public function attributes()
    {
        return [
            Attribute::make('name')->index()->searchable()->required()->label(__('leap::auth.name')),
            Attribute::make('email')->index()->email()->searchable()->unique()->label(__('leap::auth.email')),
            Attribute::make('password')->password()->confirmed()->label(__('leap::auth.password')),
            Attribute::make('roles')->pivot(model: Role::class, index: 'name', orderBy: 'name')->required()->label(__('leap::auth.roles')),
        ];
    }

    // public $model = 'App\Models\User';
    public $priority = 900;
    public $icon = 'fas-users';

    public $title = [
        'nl' => 'Gebruikers'
    ];
}
