<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Support\Str;

/**
 * Generates stored, queryable slugs — per locale when the model's slug is
 * translatable. Runs on every save (so it also covers "save as copy"/clone and
 * seeders), keeping slugs unique among siblings (same parent) per locale.
 *
 * Conventions:
 * - An empty slug is generated from the title of that locale (falling back to
 *   the default-locale title when the localized title is empty).
 * - Collisions get a -2, -3, … suffix.
 * - The reserved homepage slug "/" is never slugified.
 *
 * Lives in the package so bugfixes reach projects via composer update. The
 * frontend template ships a thin App\Traits\HasSlug wrapper around this so the
 * application namespace stays stable.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function ($model): void {
            $model->generateSlugs();
        });
    }

    public function generateSlugs(): void
    {
        // Non-translatable slug: single scalar value
        if (! in_array('slug', $this->translatable ?? [], true)) {
            if ($this->slug !== '/') {
                $this->slug = $this->uniqueSlug((string) ($this->slug ?: Str::slug((string) $this->title)));
            }

            return;
        }

        // Translatable slug: one value per configured locale (or just the current locale)
        $locales = array_keys(config('leap.locales') ?? []) ?: [app()->getLocale()];
        $default = $locales[0];

        foreach ($locales as $locale) {
            $current = $this->getTranslation('slug', $locale, false);
            if ($current === '/') {
                continue;
            }

            $title = $this->getTranslation('title', $locale, false)
                ?: $this->getTranslation('title', $default, false);

            $slug = $current ?: Str::slug((string) $title);
            if ($slug === '') {
                continue;
            }

            $this->setTranslation('slug', $locale, $this->uniqueSlug($slug, $locale));
        }
    }

    protected function uniqueSlug(string $slug, ?string $locale = null): string
    {
        if ($slug === '/') {
            return $slug;
        }

        $base = $slug;
        $suffix = 1;
        while ($this->slugExists($slug, $locale)) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?string $locale): bool
    {
        $query = static::query()
            ->where('parent', $this->parent)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()));

        if ($locale) {
            $query->whereJsonContains('slug->'.$locale, $slug);
        } else {
            $query->where('slug', $slug);
        }

        return $query->exists();
    }
}
