<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

/**
 * A user model that casts its password the way a stock Laravel application does.
 * The plain User fixture deliberately does not, so the two together cover both
 * sides of the editor's password handling.
 */
class HashingUser extends User
{
    protected $casts = [
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_email_confirmed_at' => 'datetime',
    ];

    /**
     * Keep the parent's foreign key, so the shared role pivot table still applies.
     */
    public function getForeignKey(): string
    {
        return 'user_id';
    }
}
