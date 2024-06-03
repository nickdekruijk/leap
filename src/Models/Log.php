<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NickDeKruijk\Leap\Leap;

class Log extends Model
{
    use HasFactory;

    protected $casts = [
        'time' => 'datetime',
        'context' => 'array',
    ];

    protected $fillable = [
        'ip',
        'user_agent',
        'user_id',
        'module',
        'action',
        'context',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('leap.table_prefix') . 'logs');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Leap::userModel()::class);
    }
}
