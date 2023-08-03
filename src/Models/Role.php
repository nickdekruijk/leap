<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Helpers;

class Role extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('leap.table_prefix') . 'roles');
    }

    protected $casts = [
        'settings' => 'array',
        'permissions' => 'array',
    ];

    protected $fillable = [
        'name',
        'settings',
        'permissions',
    ];

    public function users()
    {
        return $this->belongsToMany(Helpers::userModel()::class, config('leap.table_prefix') . 'role_user');
    }
}
