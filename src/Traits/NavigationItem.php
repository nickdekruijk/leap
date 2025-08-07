<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

trait NavigationItem
{
    /**
     * Return the navigation icon of the module
     *
     * @return string
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * The navigation priority of the module
     *
     * @var integer|null
     */
    public $priority;

    /**
     * Return the navigation priority of the module
     *
     * @return integer
     */
    public function getPriority(): int
    {
        return $this->priority ?: 1;
    }

    /**
     * Return the slug of the module (slugified title by default)
     *
     * @return string
     */
    public function getSlug(): ?string
    {
        return $this->slug ?? Str::slug($this->getTitle());
    }

    /**
     * The title of the module
     *
     * @var string|null
     */
    public $title;

    /**
     * Return the title of the module (pluralized class name by default)
     *
     * @return string
     */
    public function getTitle(): string
    {
        // If the title is a string and starts with 'leap::', we assume it's a translation key
        if (is_string($this->title) && str_starts_with($this->title, 'leap::')) {
            return __($this->title);
        }

        $plural = __(Str::plural(class_basename(static::class)));
        if (is_array($this->title)) {
            $this->title = $this->title[app()->getLocale()] ?? $plural;
        }
        return $this->title ?: $plural;
    }

    /**
     * The html output to show in the navigation menu
     *
     * @return string|null
     */
    public function getOutput(): ?string
    {
        return null;
    }
}
