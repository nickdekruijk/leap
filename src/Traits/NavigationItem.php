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
        return $this->title ?: __(Str::plural(class_basename(static::class)));
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
        return $this->getSlug() ? route('leap.module.' . $this->getSlug(), session('leap.user.role.organization.slug')) == url()->current() : false;
    }

    /**
     * Return the active class if the current route is the module route
     *
     * @return string
     */
    public function navigationClass()
    {
        return $this->isActive() ? 'active' : '';
    }
}
