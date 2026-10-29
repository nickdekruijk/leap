<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource with a filterable select on an enum cast, a readonly one beside it, and
 * empty datetime fields, the way the booking requests of a rental site look.
 */
class EnumResource extends Resource
{
    public $slug = 'enums';

    public $icon = 'far-eye';

    public $model = EnumModel::class;

    /**
     * Redeclared without the parent's #[Locked] so a test can order the index.
     *
     * @var string|null
     */
    public $orderBy = 'id';

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1),
            Attribute::make('status')->select()->values([
                'draft' => 'Concept',
                'sent' => 'Verstuurd',
                'paid' => 'Betaald',
            ])->index(2)->filterable(),
            Attribute::make('locked_status')->select()->values([
                'draft' => 'Concept',
                'sent' => 'Verstuurd',
                'paid' => 'Betaald',
            ])->readonly(),
            Attribute::make('kind')->select()->values([
                'boat' => 'Boot',
                'sup' => 'SUP-board',
            ])->index(3)->filterable(),
            Attribute::make('consented_at')->datetime()->readonly(),
            Attribute::make('sent_at')->datetime(),
        ];
    }
}
