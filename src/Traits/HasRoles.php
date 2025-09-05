<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use NickDeKruijk\Leap\Models\Role;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, config('leap.table_prefix') . 'role_user')->withTimestamps();
    }
}
