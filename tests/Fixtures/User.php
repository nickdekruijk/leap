<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use NickDeKruijk\Leap\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = true;
}
