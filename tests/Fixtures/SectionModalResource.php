<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

/**
 * SectionListResource with its sections edited in a modal, or explicitly not, whatever
 * config('leap.sections.modal') says.
 */
class SectionModalResource extends SectionListResource
{
    public static bool $modal = true;

    public function attributes(): array
    {
        $attributes = parent::attributes();

        foreach ($attributes as $attribute) {
            if ($attribute->name === 'sections') {
                $attribute->inModal(static::$modal);
            }
        }

        return $attributes;
    }
}
