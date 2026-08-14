<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Traits\HasReadingTime;
use NickDeKruijk\Leap\Traits\HasSections;
use Spatie\Translatable\HasTranslations;

/**
 * An article-shaped model for testing HasReadingTime: a translatable intro, sections
 * beside it, and the optional reading_time column a project may add as an override.
 *
 * "blocks" is here so a test can prove the count reads a differently named sections
 * column, the same way SectionsModel does for HasSections.
 */
class ReadingTimeModel extends Model
{
    use HasReadingTime;
    use HasSections;
    use HasTranslations;

    protected $table = 'reading_time_models';

    protected $guarded = [];

    /** @var array<int, string> */
    public array $translatable = ['title', 'intro'];

    protected $casts = [
        'sections' => 'array',
        'blocks' => 'array',
    ];
}
