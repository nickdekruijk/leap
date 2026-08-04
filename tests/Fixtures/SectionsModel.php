<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Traits\HasSections;
use Spatie\Translatable\HasTranslations;

/**
 * A model with a sections JSON column, for testing HasSections.
 *
 * Translatable as well, because that is the shape leap generates: a title that Spatie
 * stores per locale, and sections beside it. The editor asks the model whether it is
 * translatable at all before it offers a language switcher, so a fixture without it would
 * be treated as monolingual no matter what leap.locales says.
 */
class SectionsModel extends Model
{
    use HasSections;
    use HasTranslations;

    protected $table = 'sections_models';

    protected $guarded = [];

    /** @var array<int, string> */
    public array $translatable = ['title'];

    /**
     * "blocks" is here so a test can prove sections() reads a differently named column.
     */
    protected $casts = [
        'sections' => 'array',
        'blocks' => 'array',
    ];
}
