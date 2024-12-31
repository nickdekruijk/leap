<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mediable extends Model
{
    protected $casts = [
        'meta' => 'array',
    ];

    protected $fillable = [
        'media_id',
        'model_type',
        'model_id',
        'model_attribute',
        'sort',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('leap.table_prefix') . 'mediables');
    }
}
