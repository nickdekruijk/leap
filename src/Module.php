<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;


class Module extends Component
{
    public $component;
    public $icon;
    public $priority;
    public $slug;
    public $title;

    /**
     * Return the navigation priority of the module (1 by default)
     *
     * @return integer
     */
    public function getPriority(): int
    {
        return $this->priority ?? 1;
    }

    /**
     * Return the slug of the module (lowercase class name by default)
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug ?? strtolower($this->title ?? Str::plural(class_basename(static::class)));
    }

    /**
     * Return the title of the module (pluralized class name by default)
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?? __(Str::plural(class_basename(static::class)));
    }

    public function __construct($options = [])
    {
        // Use the proper authentication guard
        Auth::shouldUse(config('leap.guard'));

        // Overide default options
        foreach ($options as $option => $value) {
            $this->$option = $value;
        }
    }
}
