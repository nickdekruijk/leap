<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Contracts\Previewable;
use NickDeKruijk\Leap\Resource;
use Symfony\Component\HttpFoundation\Response;

/**
 * A preview that answers with a plain Symfony response instead of a view, which the
 * Previewable contract allows. Laravel's header() helper does not exist on it.
 */
class PlainResponsePreviewResource extends Resource implements Previewable
{
    public $slug = 'plain-previews';

    public $icon = 'far-eye';

    public $model = PreviewableModel::class;

    public function attributes(): array
    {
        return [
            Attribute::make('id')->index(),
            Attribute::make('body')->textarea(),
        ];
    }

    public function previewResponse(Model $record): Response
    {
        return new Response('Plain preview of '.$record->getKey());
    }
}
