<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use NickDeKruijk\Leap\Leap;

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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Leap::userModel()::class, config('leap.table_prefix') . 'role_user');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(config('leap.organization_model'));
    }
}
