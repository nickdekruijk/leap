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
     * Sort the index by this attribute
     *
     * @var string|null
     */
    public $orderBy;

    /**
     * Enable descending index order
     *
     * @var boolean
     */
    public bool $orderDesc = false;

    /**
     * All the rows in the index
     *
     * @var Collection
     */
    public Collection $indexRows;

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
     * @return Collection
     */
    public function indexAttributes($attribute = null): Collection
    {
        return collect($this->attributes())->where('index')->sortBy('index');
    }

    public function getAttribute($attribute)
    {
        return $this->indexAttributes()->where('name', $attribute)->first();
    }

    /**
     * Sort the index by this attribute
     *
     * @param string $attribute
     * @return void
     */
    public function order(string $attribute, bool $desc = null)
    {
        // If currently sorted by this attribute, reverse the order
        $this->orderDesc = ($desc === true || $desc === false) ? $desc : $this->orderBy == $attribute && !$this->orderDesc;

        // Set new orderBy attribute
        $this->orderBy = $attribute;

        // If attribute is a number use natural sort order else use string sorting
        $options = $this->getAttribute($this->orderBy)->type == 'number' ? SORT_NATURAL | SORT_FLAG_CASE : SORT_STRING | SORT_FLAG_CASE;

        // Sort the rows according top the above options
        $this->indexRows = $this->indexRows->sortBy($attribute, $options, $this->orderDesc);
    }

    public function mount()
    {
        // Collect all the rows for the index
        $this->indexRows = collect($this->getModel()->all($this->indexAttributes()->pluck('name')->toArray())->toArray());

        // Order them if needed
        if ($this->orderBy) {
            $this->order($this->orderBy, false);
        }
    }

    public function render()
    {
        return view('leap::livewire.resource')->layout('leap::layouts.app');
    }
}
