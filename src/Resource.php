<?php

namespace NickDeKruijk\Leap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use NickDeKruijk\Leap\Classes\Attribute;

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
     * The currently selected index row
     *
     * @var integer
     */
    public int $selectedRow = 0;

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
    public function indexAttributes(): Collection
    {
        return collect($this->attributes())->where('index')->sortBy('index');
    }

    /**
     * Return the attribute details as defined by the Leap module
     *
     * @param string $attribute The attribute name
     * @return Attribute
     */
    public function getAttribute(string $attribute): Attribute
    {
        return collect($this->attributes())->where('name', $attribute)->first();
    }

    /**
     * Sort the index by this attribute
     *
     * @param string $attribute The attribute name
     * @param boolean|null $desc Sort in descending order
     * @return void
     */
    public function order(string $attribute, bool $desc = null)
    {
        // If currently sorted by this attribute, reverse the order
        $this->orderDesc = ($desc === true || $desc === false) ? $desc : $this->orderBy == $attribute && !$this->orderDesc;

        // Set new orderBy attribute
        $this->orderBy = $attribute;
    }

    /**
     * Return an array of all rows with the id and the index attributes
     *
     * @return array
     */
    public function indexRows(): array
    {
        $data = $this->getModel();

        if ($this->orderBy) {
            $data = $data->orderBy($this->orderBy, $this->orderDesc ? 'desc' : 'asc');
        }

        return $data->get(array_merge(['id'], $this->indexAttributes()->pluck('name')->toArray()))->toArray();
    }

    /**
     * Rerender the component when updateIndex event is triggered
     * 
     * This is mostly used after updating a model.
     *
     * @return void
     */
    #[On('updateIndex')]
    public function updateIndex(int $id = 0)
    {
        $this->selectedRow = $id;
        $this->render();
    }

    public function render()
    {
        return view('leap::livewire.resource')->layout('leap::layouts.app');
    }
}
