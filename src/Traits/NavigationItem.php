<?php

namespace NickDeKruijk\Leap\Traits;

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
     * Return the navigation priority of the module
     *
     * @return integer
     */
    public function getPriority(): int
    {
        return $this->priority ?? 1;
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
     * Return the title of the module (pluralized class name by default)
     *
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title ?? __(Str::plural(class_basename(static::class)));
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

    /**
     * Return true if the current route is the module route
     *
     * @return boolean
     */
    public function isActive(): bool
    {
        return $this->getSlug() ? route('leap.module.' . $this->getSlug(), session('leap.role.organization.slug')) == url()->current() : false;
    }
}
