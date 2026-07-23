<?php

namespace NickDeKruijk\Leap\Traits;

use ArrayObject;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Mediable;

/**
 * The read side of the sections editor: turns the JSON column an Attribute::sections()
 * writes back into something a template can render.
 *
 * It lives here rather than in the frontend template because everything it knows is
 * this package's own: the shape Attribute::sections() stores, the Mediable rows the
 * media uploads land in, the _sort/_name keys the editor adds, and config('leap.locales').
 * Change the editor and this has to change with it, so the two belong together — and a
 * fix reaches every site through composer update instead of waiting for each one to
 * re-copy a stub.
 */
trait HasSections
{
    /**
     * Return the sections of a page as a collection
     *
     * @param  string  $attribute  The model attribute that has the sections, usualy a json column in the database
     */
    public function sections($attribute = 'sections'): Collection
    {
        $sections = $this->$attribute;

        // Get all media for each section
        foreach (Mediable::with('media')->where('mediable_type', self::class)->where('mediable_id', $this->id)->get() as $media) {
            $modelAttribute = explode('.', $media->mediable_attribute);
            if ($modelAttribute[0] == $attribute) {
                $sections[$modelAttribute[1]][$modelAttribute[2]] = ($sections[$modelAttribute[1]][$modelAttribute[2]] ?? new Collection)->concat([$media->media]);
            }
        }

        // Convert each section to an ArrayObject, resolving per-locale fields to the current locale
        $locales = config('leap.locales');
        $localeKeys = $locales ? array_keys($locales) : null;
        foreach ($sections ?: [] as $key => $section) {
            foreach ($section as $field => $value) {
                // A per-locale array (['nl' => …, 'en' => …]); media fields are Collections
                // (objects, not arrays) and are skipped. With leap.locales set the keys must
                // be known locales; when it is null (monolingual) any associative array is
                // treated as a translation set — the seeders still ship every locale, so the
                // extras are collapsed to the current locale rather than rendered raw.
                if (! is_array($value) || $value === []) {
                    continue;
                }
                $isPerLocale = $localeKeys !== null
                    ? ! array_diff(array_keys($value), $localeKeys)
                    : array_keys($value) !== range(0, count($value) - 1);
                if ($isPerLocale) {
                    $section[$field] = Leap::localize($value) ?? '';
                }
            }
            $sections[$key] = new ArrayObject($section);
            $sections[$key]->setFlags(ArrayObject::STD_PROP_LIST | ArrayObject::ARRAY_AS_PROPS);
        }

        // Sort sections as collection, dropping the ones switched off in the editor.
        // They have to go before _first/_last are determined: a template filtering them
        // out afterwards loses the opening or closing tag of a wrapper whenever the
        // edge of a run is the inactive one — the sections below then end up inside it.
        $sections = collect($sections)
            ->filter(fn ($section) => ! isset($section['active']) || $section['active'])
            ->sortBy('_sort');

        // Determine _first and _last values
        $previousName = null;
        $previousKey = null;
        foreach ($sections as $key => $section) {
            $sections[$key]['_first'] = $section['_name'] != $previousName;
            $sections[$key]['_last'] = true;
            if ($previousName && ! $sections[$key]['_first']) {
                $sections[$previousKey]['_last'] = false;
            }
            $previousName = $section['_name'];
            $previousKey = $key;
        }

        return $sections;
    }
}
