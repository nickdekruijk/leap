<?php

namespace NickDeKruijk\Leap;

use BladeUI\Icons\Svg;
use Illuminate\Support\Str;

class Module
{
    /**
     * The model the module corresponds to.
     *
     * @var string
     */

    public string $model;

    /**
     * Modules are sorted by priority, higher priority modules are shown first
     *
     * @var int
     */
    public int $priority = 1;

    /**
     * The slug of the module, used in the url
     *
     * @var string|null
     */
    public ?string $slug = null;

    /**
     * Get the slug of the module or use the plural of the class name
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug ?: Str::slug(Str::plural(class_basename($this)));
    }

    /**
     * The blade icon name for the navigation
     *
     * @var string|null
     */
    public ?string $icon = null;

    /**
     * Get the icon for the navigation
     *
     * @return string|Svg|null
     */
    public function getNavigationIcon(): string|Svg|null
    {
        return $this->icon ? svg($this->icon, 'nav-icon') : null;
    }

    /**
     * The title of the module, used in the navigation
     *
     * @var string|null
     */
    public ?string $title = null;

    /**
     * Get the title of the module or use the plural of the class name
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?: Str::plural(class_basename($this));
    }

    /**
     * The livewire component to use for the module
     *
     * @var string|null
     */
    public ?string $component = 'leap.dashboard';
}
