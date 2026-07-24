<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * Stands in for a module a package self-registers into leap.default_modules, the way
 * nickdekruijk/settings ships its own settings screen. Shares its title — and so its
 * slug — with ProjectSettingResource.
 */
class PackageSettingResource extends Resource
{
    public $model = TestModel::class;

    public $title = 'Settings';

    public $icon = 'fas-gears';

    public $priority = 100;

    public function attributes(): array
    {
        return [
            Attribute::make('title')->index(1),
        ];
    }
}
