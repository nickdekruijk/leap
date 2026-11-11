<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $media_id
 * @property string $mediable_type
 * @property int $mediable_id
 * @property string|null $mediable_attribute
 * @property array<string, mixed>|null $meta
 * @property int|null $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Mediable extends Model
{
    protected $casts = [
        'meta' => 'array',
    ];

    protected $fillable = [
        'media_id',
        'mediable_type',
        'mediable_id',
        'mediable_attribute',
        'sort',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('leap.table_prefix').'mediables');
    }
}
