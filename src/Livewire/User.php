<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Resource;

class User extends Resource
{
    /**
     * The panel's users are whoever the configured auth provider authenticates,
     * not a guess at App\Models\User. Resource::getModel() derives the model from
     * the class name, which breaks the moment a project authenticates something
     * else (App\Models\Admin, or a User outside App\Models) — the module then
     * points at a class that does not exist.
     */
    public function getModel(): Model
    {
        $this->model ??= Leap::userModel()::class;

        return parent::getModel();
    }

    public function attributes()
    {
        return [
            Attribute::make('name')->index()->searchable()->required()->label(__('leap::auth.name')),
            Attribute::make('email')->index()->email()->searchable()->unique()->label(__('leap::auth.email')),
            Attribute::make('password')->password()->confirmed()->label(__('leap::auth.password')),
            Attribute::make('roles')->index()->pivot(model: Role::class, index: 'name', orderBy: 'name')->required()->label(__('leap::auth.roles')),
        ];
    }

    // public $model = 'App\Models\User';
    public $priority = 900;

    public $icon = 'fas-users';

    public $with = 'roles';

    public $title = [
        'nl' => 'Gebruikers',
    ];
}
