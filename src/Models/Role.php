<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use NickDeKruijk\Leap\Leap;

/**
 * @property int $id
 * @property string $name
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $permissions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Role extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('leap.table_prefix').'roles');
    }

    protected $casts = [
        'accepted' => 'boolean',
        'settings' => 'array',
        'permissions' => 'array',
        'invited_on' => 'datetime',
        'accepted_on' => 'datetime',
    ];

    protected $fillable = [
        'accepted',
        'name',
        'settings',
        'permissions',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Leap::userModel()::class, config('leap.table_prefix').'role_user');
    }
}
