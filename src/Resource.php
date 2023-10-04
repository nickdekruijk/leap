<?php

namespace NickDeKruijk\Leap;


class Resource extends Module
{
    /**
     * The model the module corresponds to.
     *
     * @var string
     */
    public $model;

    /**
     * The livewire component to use for the module
     *
     * @var string|null
     */
    public $component = 'leap.resource';

    /**
     * The ModuleController will set permissions for this module in this variable
     * so we can use it in the Livewire component.
     *
     * @var array|null
     */
    public ?array $permissions;

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model ?: 'App\\' . (is_dir(app_path('Models')) ? 'Models\\' : '') . class_basename(static::class);
    }

    public function render()
    {
        return view('leap::livewire.resource')->layout('leap::layouts.app');
    }
}
