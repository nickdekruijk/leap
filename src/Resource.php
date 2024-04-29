<?php

namespace NickDeKruijk\Leap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
     * Sort the index by this attribute
     *
     * @var string|null
     */
    public ?string $sort;

    /**
     * Return a model instance
     *
     * @return Model
     */
    public function getModel(): Model
    {
        $model = $this->model ?: 'App\\' . (is_dir(app_path('Models')) ? 'Models\\' : '') . class_basename(static::class);
        return new $model;
    }

    /**
     * Return the model attributes to show in the index
     *
     * @return array
     */
    public function index(): Collection
    {
        return collect($this->attributes())->where('index')->sortBy('index');
    }

    public function getIndexData()
    {
        return $this->getModel()->all($this->index()->pluck('name')->toArray())->toArray();
    }

    public function render()
    {
        return view('leap::livewire.resource')->layout('leap::layouts.app');
    }
}
