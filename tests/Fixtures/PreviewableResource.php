<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Contracts\Previewable;
use NickDeKruijk\Leap\Resource;

/**
 * The module that owns PreviewableModel: the slug the preview route is addressed by, the
 * permission it is checked against, and the view one of its records renders as.
 */
class PreviewableResource extends Resource implements Previewable
{
    public $slug = 'previews';

    public $icon = 'far-eye';

    public $model = PreviewableModel::class;

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1)->translatable(),
            Attribute::make('body')->textarea(),
            Attribute::make('published_at')->datetime()->nullable(),
            Attribute::make('active')->checkbox(),
        ];
    }

    public function previewResponse(Model $record): View
    {
        return view('previewfixture::preview', ['record' => $record]);
    }
}
