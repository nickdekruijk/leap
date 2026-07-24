<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * Stands in for the copy a project keeps of a module a package also ships — the same
 * screen under the same title, and therefore the same slug, as PackageSettingResource.
 */
class ProjectSettingResource extends Resource
{
    public $model = TestModel::class;

    public $title = 'Settings';

    public $icon = 'fas-cog';

    public $priority = 100;

    public function attributes(): array
    {
        return [
            Attribute::make('title')->index(1),
        ];
    }
}
