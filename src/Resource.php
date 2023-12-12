<?php

namespace NickDeKruijk\Leap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Resource extends Module
{
    /**
     * The model the module corresponds to.
     *
     * @var string
     */
    public $model;

    /**
     * The model instance for internal use
     *
     * @var Model
     */
    protected Model $_model;

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
     * The columns to show in the list view
     *
     * @var array|null
     */
    public ?array $listColumns;

    public $listview;

    /**
     * Return a model instance
     *
     * @return Model
     */
    public function getModel(): Model
    {
        if (empty($this->_model)) {
            $model = $this->model ?: 'App\\' . (is_dir(app_path('Models')) ? 'Models\\' : '') . class_basename(static::class);
            $this->_model = new $model;
        }
        return $this->_model;
    }

    /**
     * Return the columns of the model that can be edited
     *
     * @return array
     */
    public function columns(): array
    {
        // First try to get the fillable columns from the model
        $columns = $this->getModel()->getFillable();

        // If no fillable then get all columns from the database table and remove the guarded ones
        if (empty($columns)) {
            $guarded = $this->getModel()->getGuarded();
            if ($guarded != ['*']) {
                // Get all columns
                $columns = Schema::getColumnListing($this->getModel()->getTable());
                // Remove the ones we don't want
                $columns = array_diff($columns, ['id', 'created_at', 'updated_at', 'deleted_at']);
                // Remove the guarded ones
                $columns = array_diff($columns, $guarded);
            }
        }

        // Return the columns
        return $columns;
    }

    /**
     * Return the columns of the model that should be shown in the listview
     *
     * @return array
     */
    public function listColumns(): array
    {
        // Take the first 3 columns
        return array_slice($this->columns(), 0, 3);
    }


    public function getListData()
    {
        return $this->getModel()->all($this->listColumns())->toArray();
    }

    public function mount()
    {
        $this->listview = $this->getListData();
        // dd($this->listview);
    }

    public function render()
    {
        return view('leap::livewire.resource')->layout('leap::layouts.app');
    }
}
