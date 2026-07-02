<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use NickDeKruijk\Leap\Traits\HasRoles;

class User extends Authenticatable implements PasskeyUser
{
    use HasRoles;
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_email_confirmed_at' => 'datetime',
    ];
}
