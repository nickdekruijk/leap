<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Str;

class Section
{
    public static $_instance = null;

    public string $name;
    public string|null $view;
    public string $label;
    public array $attributes = [];

    /**
     * Make a new Section instance and set default view based on name.
     *
     * @param string $name
     * @return Section
     */
    public static function make(string $name): Section
    {
        self::$_instance = new self;
        self::$_instance->name = $name;
        self::$_instance->view = 'sections.' . $name;

        self::$_instance->label = Str::headline($name);

        return self::$_instance;
    }

    /**
     * Override the default view of the section
     *
     * @param string $name
     * @return Section
     */
    public function view(string|null $view): Section
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Don't set _view for the section
     *
     * @return Section
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
     * @param string $label
     * @return Section
     */
    public function label(string $label): Section
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Add one or more attributes to the section
     *
     * @param Attribute ...$attributes
     * @return Section
     */
    public function attributes(Attribute ...$attributes): Section
    {
        $this->attributes = $attributes;
        return $this;
    }
}
