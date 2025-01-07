<?php

namespace NickDeKruijk\Leap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Livewire\Toasts;

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
    #[Url(as: 'id', history: false)]
    public ?int $selectedRow = null;

    /**
     * When set shows the file browser
     *
     * @var array
     */
    #[Locked]
    public array|false $browse = false;

    public array|false $translatable;

    /**
     * Update this to recalculate the column widths
     *
     * @var integer
     */
    public int $setColumnWidths = 0;

    /**
     * Open or close the file browser for the file(s) or media attribute
     *
     * @param [type] $attribute
     * @return void
     */
    #[On('selectBrowsedFiles')]
    #[On('selectMediaFiles')]
    public function fileBrowser($attribute = null, $files = null)
    {
        $this->browse = $attribute && !$files ? array_merge(['attribute' => $attribute], $this->getAttribute($attribute)->options) : false;
    }

    public function hasTranslation(Attribute $attribute): bool
    {
        if ($this->translatable) {
            return in_array($attribute->name, $this->translatable);
        }
        return false;
    }

    public function sortable(): Attribute|false
    {
        return collect($this->attributes())->where('type', 'sortable')->first() ?: false;
    }

    public function treeview(): Attribute|false
    {
        return collect($this->attributes())->where('type', 'tree')->first() ?: false;
    }

    /**
     * Undocumented function
     *
     * @param integer $parent_id
     * @param integer $item_id
     * @param integer $position
     * @return void
     */
    public function sortableDone(int $parent_id, int $item_id, int $position): void
    {
        // Check if item is valid
        $item = $this->getModel()->find($item_id);
        if (!$item) {
            $this->dispatch('toast-error', 'Item not found')->to(Toasts::class);
            return;
        }

        if ($this->treeview()) {
            if ($parent_id == 0) {
                $parent_id = null;
                $position--; // Since leap-index-header is at position 0 we need to decrease the position by 1
            } else {
                // Check if parent exists
                $parent = $this->getModel()->find($parent_id);
                if (!$parent) {
                    $this->dispatch('toast-error', 'Parent item not found')->to(Toasts::class);
                    return;
                }
            }

            // Move item to different parent if required
            if ($parent_id != $item->{$this->treeview()->name}) {
                $item->{$this->treeview()->name} = $parent_id;
                $item->save();
            }

            $orderItems = $this->getModel()->where($this->treeview()->name, $parent_id);
        } else {
            $orderItems = $this->getModel();
        }

        // Move item to new position within parent
        foreach ($orderItems->where('id', '!=', $item_id)->orderBy($this->sortable()->name)->get() as $index => $row) {
            $row->{$this->sortable()->name} = $index >= $position ? $index + 1 : $index;
            $row->save();
        }
        $item->{$this->sortable()->name} = $position;
        $item->save();

        $this->dispatch('toast', __('leap::resource.moved', ['title' => $item->{$this->indexAttributes()->first()->name}]))->to(Toasts::class);

        $this->setColumnWidths++;
    }

    /**
     * Return a model instance
     *
     * @return Model
     */
    public function getModel(): Model
    {
        $model = $this->model ?: 'App\\' . (is_dir(app_path('Models')) ? 'Models\\' : '') . class_basename(static::class);
        $model = new $model;
        $this->translatable = in_array(\Spatie\Translatable\HasTranslations::class, class_uses($model)) ? $model->getTranslatableAttributes() : false;
        return $model;
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
     * @return void
     */
    public function order(string $attribute)
    {
        if ($this->orderBy == $attribute && !$this->orderDesc) {
            // If currently ascending sorted by this attribute, change to descending
            $this->orderDesc = true;
        } elseif ($this->orderBy == $attribute && $this->orderDesc) {
            // If currently descending sorted by this attribute set orderBy to null for default sorting
            $this->orderBy = null;
        } else {
            // Set new orderBy attribute
            $this->orderBy = $attribute;
            $this->orderDesc = false;
        }

        $this->setColumnWidths++;
    }

    /**
     * Return an array of all rows with the id and the index attributes
     *
     * @return array
     */
    public function indexRows(int $parent_id = null): array
    {
        $data = $this->getModel();

        if ($this->treeview()) {
            $data = $data->where($this->treeview()->name, $parent_id);
        }

        // Check if data needs to be sorted by a foreign attribute, in that case we can't use orderBy on the model but manually sort the array later
        $sortForeign = $this->orderBy && $this->indexAttributes()->where('name', $this->orderBy)->first()->type == 'foreign';

        if ($this->orderBy && !$sortForeign) {
            $data = $data->orderBy($this->orderBy, $this->orderDesc ? 'desc' : 'asc');
        }

        // Get all index columns including the id and the treeview parent id if required
        $data = $data->get(array_merge($this->treeview() ? ['id', $this->treeview()->name] : ['id'], $this->indexAttributes()->pluck('name')->toArray()))->toArray();

        // Replace all foreign keys with their value
        foreach ($this->indexAttributes()->where('type', 'foreign') as $foreignAttribute) {
            $values = $foreignAttribute->getValues();
            foreach ($data as $id => $row) {
                $data[$id][$foreignAttribute->name] = $values[$row[$foreignAttribute->name]] ?? null;
            }
        }

        if ($sortForeign) {
            Leap::sortBy($data, $this->orderBy, $this->orderDesc);
        }

        return $data;
    }

    /**
     * Rerender the component when updateIndex event is triggered
     *
     * This is mostly used after updating a model.
     *
     * @return void
     */
    #[On('updateIndex')]
    public function updateIndex(int $id = null)
    {
        $this->setColumnWidths++;
        $this->selectedRow = $id;
    }

    public function render()
    {
        $this->log('read');
        return view('leap::livewire.resource')->layout('leap::layouts.app');
    }
}
