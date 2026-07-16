<?php

namespace NickDeKruijk\Leap\Traits;

/**
 * Head/document metadata for a routable content model: the <title>, the meta
 * description and the Open Graph / Twitter image URL.
 *
 * Lives in the package so the frontend template's <head> logic is shared and
 * fixable via composer update. Designed to degrade gracefully: it works on any
 * model, using Spatie\Translatable\HasTranslations when present (so an empty
 * html_title in the active locale never borrows another locale's), and only
 * inspects media/sections when the model actually uses HasMedia / HasSections.
 */
trait HasDocumentMeta
{
    /**
     * The document <title>: a custom html_title is used verbatim; a plain page
     * title gets the site name appended. config('app.name') is only added when
     * there is no html_title.
     */
    public function documentTitle(): string
    {
        // Read html_title without translation fallback: an empty html_title in the
        // active locale must fall through to the page title, not borrow another
        // locale's html_title. Non-translatable models read the plain attribute.
        $htmlTitle = $this->documentMetaValue('html_title');

        if ($htmlTitle !== '') {
            return $htmlTitle;
        }

        // $this->title resolves to the active locale on a translatable model.
        $title = (string) ($this->title ?? '');

        return trim(($title !== '' ? $title.' — ' : '').config('app.name'));
    }

    /**
     * OG/Twitter image URL from the model's own image, then its first section
     * image or background. Null when there is none (the layout can then fall back
     * to a site-wide og_image setting).
     */
    public function ogImageUrl(): ?string
    {
        $file = method_exists($this, 'mediaFor')
            ? $this->mediaFor('images')->first()?->file_name
            : null;

        if (! $file && method_exists($this, 'sections')) {
            foreach ($this->sections() as $section) {
                $file = ($section['image'] ?? null)?->first()?->file_name
                    ?? ($section['background'] ?? null)?->first()?->file_name;
                if ($file) {
                    break;
                }
            }
        }

        return $file ? url('storage/'.$file) : null;
    }

    /**
     * The meta/OG description: the model's own description, falling back to the
     * intro that a listed content item already carries as its card text. Empty
     * when there is neither, so the layout can skip the tag. A page has no intro
     * and simply gets its description.
     */
    public function metaDescription(): string
    {
        $description = $this->documentMetaValue('description');

        return $description !== '' ? $description : $this->documentMetaValue('intro');
    }

    /**
     * Read a meta attribute in the active locale without translation fallback,
     * or the plain attribute value when the attribute is not translatable.
     *
     * The attribute is checked against the model's translatable set rather than
     * only for the presence of getTranslation(): a translatable model asked for an
     * attribute it does not translate throws AttributeIsNotTranslatable, and a
     * model is free to translate some of its meta attributes and not others.
     */
    protected function documentMetaValue(string $attribute): string
    {
        if (method_exists($this, 'isTranslatableAttribute') && $this->isTranslatableAttribute($attribute)) {
            return (string) $this->getTranslation($attribute, app()->getLocale(), false);
        }

        return (string) ($this->{$attribute} ?? '');
    }
}
