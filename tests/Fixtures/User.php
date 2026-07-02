<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NickDeKruijk\Leap\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'two_factor_confirmed_at' => 'datetime',
    ];
}
