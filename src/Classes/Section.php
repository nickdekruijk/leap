<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Str;

class Section
{
    public static $_instance = null;

    public string $name;

    public ?string $view;

    public string $label;

    public array $attributes = [];

    /**
     * Make a new Section instance and set default view based on name.
     */
    public static function make(string $name): Section
    {
        self::$_instance = new self;
        self::$_instance->name = $name;
        self::$_instance->view = 'sections.'.$name;

        self::$_instance->label = Str::headline($name);

        return self::$_instance;
    }

    /**
     * Override the default view of the section
     *
     * @param  string  $name
     */
    public function view(?string $view): Section
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Don't set _view for the section
     */
    public function withoutView(): Section
    {
        return $this->view(null);
    }

    /**
     * Set the label for the section
     *
     * The label is a nice name for the attribute, it will be shown in the index and form.
     * By default index label is the original attribute name passed thru Str::headline().
     * With this method you can change the label and index label.
     *
     * @param  string  $label
     */
    public function label(string|array $label): Section
    {
        // Accept a per-locale array (['nl' => '…', 'en' => '…']) and resolve to the current locale
        $this->label = is_array($label) ? ($label[app()->getLocale()] ?? (reset($label) ?: '')) : $label;

        return $this;
    }

    /**
     * Add one or more attributes to the section
     */
    public function attributes(Attribute ...$attributes): Section
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * The textual inputs whose content is worth translating. A field is only
     * auto-marked translatable by translatableExcept() when it is one of these
     * AND its type is 'text' -- so plain text, textarea and rich-text are covered,
     * while selects, file/media pickers, switches, dates, numbers, etc. are not.
     */
    private const TRANSLATABLE_INPUTS = ['input', 'textarea', 'tinymce'];

    /**
     * Mark only the named sub-attributes as translatable (edited per locale).
     *
     * The safe, explicit counterpart to calling ->translatable() on each field:
     * list exactly the fields that hold translatable content. Must be chained
     * after attributes().
     */
    public function translatableOnly(string ...$names): Section
    {
        foreach ($this->attributes as $attribute) {
            if (in_array($attribute->name, $names, true)) {
                $attribute->translatable();
            }
        }

        return $this;
    }

    /**
     * Mark every textual sub-attribute translatable except the named ones.
     *
     * Convenient when most of a section is translatable copy and only a few
     * structural fields (a switch, an image, a layout select) are not. Only
     * text/textarea/rich-text fields are ever auto-marked (see TRANSLATABLE_INPUTS);
     * selects, media, switches, dates and the like are skipped automatically, so
     * you usually only need to name a translatable-looking field you want to keep
     * shared. Must be chained after attributes(). For full control use
     * translatableOnly().
     */
    public function translatableExcept(string ...$names): Section
    {
        foreach ($this->attributes as $attribute) {
            if (in_array($attribute->name, $names, true)) {
                continue;
            }
            if ($attribute->type === 'text' && in_array($attribute->input, self::TRANSLATABLE_INPUTS, true)) {
                $attribute->translatable();
            }
        }

        return $this;
    }
}
