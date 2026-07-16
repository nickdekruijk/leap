<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Traits\HasSections;

/**
 * A model with a sections JSON column, for testing HasSections.
 */
class SectionsModel extends Model
{
    use HasSections;

    protected $table = 'sections_models';

    protected $guarded = [];

    /**
     * "blocks" is here so a test can prove sections() reads a differently named column.
     */
    protected $casts = [
        'sections' => 'array',
        'blocks' => 'array',
    ];
}
