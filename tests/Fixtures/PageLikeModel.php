<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Traits\HasDocumentMeta;
use Spatie\Translatable\HasTranslations;

/**
 * A translatable model that carries a description but no intro at all — the shape of
 * the frontend template's Page, as opposed to a listed content item.
 *
 * It exists because asking a translatable model for a translation of an attribute it
 * does not translate throws AttributeIsNotTranslatable, so metaDescription()'s reach
 * for an intro must not assume every model has one.
 */
class PageLikeModel extends Model
{
    use HasDocumentMeta;
    use HasTranslations;

    protected $table = 'page_like_models';

    protected $guarded = [];

    public array $translatable = ['title', 'html_title', 'description'];
}
