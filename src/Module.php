<?php

namespace NickDeKruijk\Leap;

use BladeUI\Icons\Svg;
use Illuminate\Support\Facades\Auth;
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
     * The blade icon name for the navigation
     *
     * @var string|null
     */
    public ?string $icon = null;

    /**
     * The title of the module, used in the navigation
     *
     * @var string|null
     */
    public ?string $title = null;

    /**
     * The livewire component to use for the module
     *
     * @var string|null
     */
    public ?string $component = 'leap.dashboard';

    /**
     * The ModuleController will set permissions for this module in this variable
     * so we can use it in the Livewire component.
     *
     * @var array|null
     */
    public ?array $permissions;

    public function __construct($options = [])
    {
        // Use the proper authentication guard
        Auth::shouldUse(config('leap.guard'));

        // If no model is set use the plural class name
        $this->title = $this->title ?: Str::plural(class_basename($this->model));

        // If no slug is set use the title
        $this->slug = $this->slug ?: Str::slug($this->title);

        // Overide default options
        foreach ($options as $option => $value) {
            $this->$option = $value;
        }
    }
}
