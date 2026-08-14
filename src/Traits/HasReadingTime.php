<?php

namespace NickDeKruijk\Leap\Traits;

use NickDeKruijk\Leap\Leap;

/**
 * How long an article takes to read, counted rather than stored.
 *
 * A single reading_time integer per row is wrong the moment an article exists in two
 * languages that are not the same length, and it ages silently as soon as someone adds a
 * paragraph. Counting instead means the number always describes the text that is actually
 * there, in the language it is asked for.
 *
 * The count follows the same locale rules the frontend renders with (Leap::localize() for
 * section fields, Spatie's own resolution for translatable attributes), so the reading time
 * describes what the visitor sees rather than what is stored under one key.
 *
 * A reading_time column still wins when the model has one, which stays a project's choice:
 * this package ships no migration for it, because an override nobody fills in is an empty
 * column, and $this->reading_time on a model without it is simply null.
 */
trait HasReadingTime
{
    /**
     * Reading time in whole minutes, or null when there is nothing to read in this locale
     * so a view can leave the line out instead of promising "0 min".
     *
     * @param  string|null  $locale  Defaults to the active locale.
     * @param  string  $attribute  The model attribute holding the sections, as in HasSections.
     */
    public function readingTime(?string $locale = null, string $attribute = 'sections'): ?int
    {
        // Asked for through getAttribute() rather than as a property: on a model without
        // the column that is null instead of a fatal, which is what lets the override stay
        // a project's own choice.
        if ($override = $this->getAttribute('reading_time')) {
            return (int) $override;
        }

        $words = $this->wordCount($locale, $attribute);

        // Rounded, but never down to nothing: a page with a paragraph on it still takes a
        // moment, and "0 min" reads as a bug rather than as brevity.
        return $words > 0 ? max(1, (int) round($words / $this->wordsPerMinute())) : null;
    }

    /**
     * The number of words a visitor reading this locale is given: the model's own text
     * fields plus every field of every active section.
     */
    public function wordCount(?string $locale = null, string $attribute = 'sections'): int
    {
        $locale ??= app()->getLocale();

        $text = '';

        // The model's own fields first: an intro, or a body column on a model without
        // sections. Only real attributes are read, because $this->{$field} on a name that happens
        // to be a method is a LogicException or an unexpected query, and counting words
        // may do neither.
        foreach ($this->readingTimeFields() as $field) {
            if (array_key_exists($field, $this->getAttributes())) {
                $text .= ' '.$this->readingTimeValue($field, $locale);
            }
        }

        foreach ((array) $this->getAttribute($attribute) as $section) {
            // A section switched off in the editor is not on the page and so is not read;
            // HasSections::sections() drops it for the same reason.
            if (! is_array($section) || ($section['active'] ?? true) === false) {
                continue;
            }

            foreach ($this->readingTimeFields() as $field) {
                // Leap::localize() is the exact rule HasSections uses to bring a field to
                // the view, including its fall back to the first translation. Using it here
                // keeps the count equal to what the visitor sees, multilingual and
                // monolingual alike (leap.locales null), where the seeders still write
                // every locale into the column.
                $value = Leap::localize($section[$field] ?? null, $locale);

                if (is_string($value) && $value !== '') {
                    $text .= ' '.$value;
                }
            }
        }

        // Tags out, then entities, then tags again, or "&nbsp;" would be counted as a word
        // and "caf&eacute;" as two. Words are runs of letters and digits, which keeps the
        // count the same in Dutch and English and does not turn an em-rule into a word.
        return preg_match_all('/[\p{L}\p{N}]+/u', strip_tags(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * The fields that hold text, both on the model itself and in each section. These are
     * this package's own section conventions (head/body); a model that names them
     * differently overrides this method.
     *
     * @return array<int, string>
     */
    protected function readingTimeFields(): array
    {
        return ['intro', 'head', 'body', 'text'];
    }

    /**
     * Words per minute. A convention rather than a measurement: Brysbaert (2019) puts
     * silent reading of non-fiction around 238, screen reading sits lower, and 200 to 250
     * is the range usually quoted. 225 is the middle of it. It is a round number by
     * nature, so the result is presented as one: "4 min", never "3.7".
     */
    protected function wordsPerMinute(): int
    {
        return 225;
    }

    /**
     * A model attribute in the requested locale, with Spatie's fallback, because that is
     * what $this->intro gives the view as well. The attribute is checked against the
     * model's translatable set rather than only for the presence of getTranslation(): a
     * translatable model asked for an attribute it does not translate throws
     * AttributeIsNotTranslatable and would take the page down over one field.
     */
    protected function readingTimeValue(string $attribute, string $locale): string
    {
        if (method_exists($this, 'isTranslatableAttribute') && $this->isTranslatableAttribute($attribute)) {
            return (string) $this->getTranslation($attribute, $locale);
        }

        $value = $this->getAttribute($attribute);

        return is_string($value) ? $value : '';
    }
}
