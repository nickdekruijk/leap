<?php

namespace App\Traits;

use ArrayObject;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Models\Mediable;

trait HasSections
{

    /**
     * Return the sections of a page as a collection
     *
     * @param string $attribute The model attribute that has the sections, usualy a json column in the database
     * @return Collection
     */
    public function sections($attribute = 'sections'): Collection
    {
        $sections = $this->$attribute;

        // Get all media for each section
        foreach (Mediable::with('media')->where('mediable_type', self::class)->where('mediable_id', $this->id)->get() as $media) {
            $modelAttribute = explode('.', $media->mediable_attribute);
            if ($modelAttribute[0] == $attribute) {
                $sections[$modelAttribute[1]][$modelAttribute[2]] = ($sections[$modelAttribute[1]][$modelAttribute[2]] ?? new Collection())->concat([$media->media]);
            }
        }

        // Convert each section to an ArrayObject
        foreach ($sections ?: [] as $key => $section) {
            $sections[$key] = new ArrayObject($section);
            $sections[$key]->setFlags(ArrayObject::STD_PROP_LIST | ArrayObject::ARRAY_AS_PROPS);
        }

        // Return sorted sections as collection
        return collect($sections)->sortBy('_sort');
    }
}
