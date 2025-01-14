<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;
use NickDeKruijk\Leap\Traits\CanLog;

class Editor extends Component
{
    use CanLog;

    const int CREATE_NEW = -1;

    /**
     * The id of the row currently being edited, also toggles editor
     *
     * @var integer
     */
    #[Locked]
    public ?int $editing;

    /**
     * The model data which can be updated by the editor
     *
     * @var array
     */
    public array $data;

    /**
     * The attribute placeholders will be overruled by these and will be the default value when the input is empty
     *
     * @var array
     */
    public array $placeholder = [];

    /**
     * The name of the parent Livewire component
     * 
     * The editor uses this to determine the model and attributes. This will be encrypted to prevent leaking sensitive class name
     *
     * @var string
     */
    #[Locked]
    public string $parentModuleEncrypted;

    /**
     * Keep track of updated media attributes
     *
     * @var array
     */
    public array $mediaUpdated = [];

    /**
     * A random number to append to some input elements to keep them unique after each sorting action
     * 
     * Mainly used as a workaround for tinymce editor issues after section sorting but causes flashing of the sections, looking for a more solid solution
     *
     * @var integer
     */
    public int $randomSortSeed;

    /**
     * Generate a new random sort seed number
     *
     * @return void
     */
    public function setRandomSortSeed()
    {
        $this->randomSortSeed = rand();
    }

    /**
     * Returns the parent Livewire component
     *
     * @return Component
     */
    private function parentModule(): Component
    {
        $decrypted = Crypt::decryptString($this->parentModuleEncrypted);
        return new $decrypted;
    }

    /**
     * Return the model attributes to show in the editor
     *
     * @return Collection
     */
    public function attributes(): Collection
    {
        // Get the attributes from the parent module
        $parentAttributes = $this->parentModule()->attributes();

        // Filter out the indexOnly attributes
        return collect($parentAttributes)->where('indexOnly', false);
    }

    /**
     * Return a model instance 
     *
     * @param [type] $id
     * @return Model
     */
    private function getModel($id = null): Model
    {
        // Get the model instance
        $model = $this->parentModule()->getModel();

        // Find the model if an id is passed
        return $id > 0 ? $model->find($id) : $model;
    }

    /**
     * Return the relationship pivot data for the given attribute
     *
     * @param Attribute $attribute
     * @return array
     */
    public function pivotData(Attribute $attribute): array
    {
        return $this->getModel($this->editing)->{$attribute->name}->pluck('id')->toArray();
    }

    public function pivotIsDirty(Attribute $attribute = null): array
    {
        $dirty = [];
        if ($attribute) {
            $attributes = [$attribute];
        } else {
            $attributes = $this->attributes()->where('type', 'pivot');
        }
        foreach ($attributes as $attribute) {
            if ($this->data[$attribute->name] != $this->pivotData($attribute)) {
                $dirty[$attribute->name] = $attribute->name;
            }
        }
        return $dirty;
    }

    /**
     * Show the editor for the given id
     *
     * @param int $id the id of the Model to update
     * @return void
     */
    #[On('openEditor')]
    public function openEditor(int $id)
    {
        // Check if the user has read permission to this module
        Leap::validatePermission('read');

        $this->log('read', ['id' => $id]);

        // Set the editing id and open the editor
        $this->editing = $id;

        // We only want the attributes that are shown in the editor
        $attributes = $this->attributes()->pluck('name')->toArray();

        // Get the model data
        $this->data = $this->getModel($id)->only($attributes);

        // Reformat date attributes
        foreach ($this->attributes()->where('type', 'date') as $attribute) {
            $this->data[$attribute->name] = $this->data[$attribute->name]?->isoFormat('YYYY-MM-DD');
        }
        // Reformat datetime attributes
        foreach ($this->attributes()->where('type', 'datetime-local') as $attribute) {
            $this->data[$attribute->name] = $this->data[$attribute->name]?->isoFormat('YYYY-MM-DD HH:mm:ss');
        }

        // Set the placeholders for slugify attributes
        foreach ($this->attributes()->where('slugify') as $attribute) {
            $this->placeholder[$attribute->slugify] = Str::slug($this->data[$attribute->name]);
        }

        // Obfuscate passwords
        foreach ($this->attributes()->where('type', 'password') as $attribute) {
            if ($this->data[$attribute->name]) {
                $this->data[$attribute->name] = null;
                if ($attribute->confirmed) {
                    $this->data[$attribute->confirmed] = null;
                }
            }
        }

        // Get the media attributes data
        $allMedia = $this->getModel($id)
            ->morphToMany(Media::class, 'model', config('leap.table_prefix') . 'mediables')
            ->withPivot('sort', 'model_attribute')
            ->orderBy(config('leap.table_prefix') . 'mediables.sort');
        foreach ($allMedia->get() as $media) {
            $this->data[$media->pivot->model_attribute][] = $media->id;
        }

        // Get pivot data
        foreach ($this->attributes()->where('type', 'pivot') as $attribute) {
            $this->data[$attribute->name] = $this->pivotData($attribute, $id);
        }

        // Make sure all section tinymce input values exist
        foreach ($this->attributes()->where('type', 'sections') as $sectionAttribute) {
            if ($this->data[$sectionAttribute->name]) {
                foreach ($this->data[$sectionAttribute->name] as $index => $section) {
                    if (isset($section['_name'])) {
                        foreach (collect(collect($sectionAttribute->sections)->where('name', $section['_name'])->first()?->attributes)->where('input', 'tinymce') as $input) {
                            $this->data[$sectionAttribute->name][$index][$input->name] = $this->data[$sectionAttribute->name][$index][$input->name] ?? '';
                        }
                    }
                    if (!isset($section['content'])) {
                        $section['content'] = '';
                    }
                }
            }
        }

        // Clear existing validation errors
        $this->resetValidation();
    }

    /**
     * Hide the editor
     *
     * @return void
     */
    #[On('closeEditor')]
    public function close()
    {
        $this->editing = null;
    }

    /**
     * Return the validation rules from the attributes
     *
     * @param integer|null $id the id of the model to update or null if creating, usedto replace {id} in rules (usualy the unique rule)
     * @return array
     */
    public function rules(int $id = null): array
    {
        // The Attribute class sets some placeholders in the validation rules that needs to be replaced with actual values, this array defines those replacements
        $replace = [
            '{id}' => $id,
            '{table}' => $this->getModel()->getTable(),
        ];

        $rules = [];

        foreach ($this->attributes() as $attribute) {
            // Walk thru the validation rules of each attribute
            if ($attribute->validate) {
                // Replace placeholders
                foreach ($replace as $old => $new) {
                    foreach ($attribute->validate as $key => $value) {
                        if (!is_object($value)) {
                            $attribute->validate[$key] = str_replace($old, $new ?: '', $value);
                        }
                    }
                }
                // Add the validation rule
                $rules['data.' . $attribute->name] = $attribute->validate;
            }
        }

        return $rules;
    }

    /**
     * Return the labels for each attribute for nice validation messages
     *
     * @return array
     */
    public function validationAttributes(): array
    {
        $attributes = [];
        foreach ($this->attributes() as $attribute) {
            $attributes['data.' . $attribute->name] = $attribute->label;
        }
        return $attributes;
    }

    /**
     * Move a section to a different position in the array
     *
     * @param string $attribute
     * @param integer $index
     * @param integer $position
     * @return void
     */
    public function sortSection(string $attribute, int $index, int $position)
    {
        // Pick the item to move and remove it from the array
        $itemToMove = [$index => $this->data[$attribute][$index]];
        unset($this->data[$attribute][$index]);

        // Add the item to the new position
        $this->data[$attribute] =
            array_slice($this->data[$attribute], 0, $position, true)
            + $itemToMove
            + array_slice($this->data[$attribute], $position, null, true);

        // Add _sort to all sections
        $sort = 0;
        foreach ($this->data[$attribute] as $index => $section) {
            $this->data[$attribute][$index]['_sort'] = $sort;
            $sort++;
        }

        $this->setRandomSortSeed();
    }

    /**
     * Remove a section 
     *
     * @param string $field
     * @param string $index
     * @return void
     */
    public function removeSection(string $field, string $index)
    {
        unset($this->data[$field][$index]);

        // Delete section media from data
        $prefix = $field . '.' . $index . '.';
        foreach ($this->data as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                unset($this->data[$key]);
                $this->mediaUpdated[$key] = $key;
            }
        }
    }

    /**
     * Add a new section
     *
     * @param string $field
     * @param string $name
     * @return void
     */
    public function addSection(string $field, string $name)
    {
        $field = rtrim($field, ':add');
        /** @var object */
        $attribute = collect($this->attributes())->where('name', ltrim($field, 'data.'))->first();
        $this->data[$attribute->name][] = [
            '_name' => $name,
        ];
        $this->data[ltrim($field, 'data.') . ':add'] = null;
    }

    public function sectionAttribute(Attribute $sectionAttribute, string $name, int $index, $sectionName): Attribute
    {
        $newAttribute = clone $sectionAttribute;
        $newAttribute->dataName = 'data.' . $name . '.' . $index . '.' . $sectionAttribute->name;
        $newAttribute->name = $name . '.' . $index . '.' . $sectionAttribute->name;
        $newAttribute->sectionName = $sectionName;
        return $newAttribute;
    }

    public function updated($field, $value)
    {
        // Check if :add field is used, if so add section
        if (str_ends_with($field, ':add')) {
            return $this->addSection($field, $value);
        }

        // Get the full attribute, the @var docblock is only here as a workaround for an intelephense bug, may not be needed later
        /** @var object */
        $attribute = collect($this->attributes())->where('name', ltrim($field, 'data.'))->first();

        // Update slug placeholder if needed
        if ($attribute?->slugify) {
            $this->placeholder[$attribute->slugify] = Str::slug($value);
        }

        $this->validateOnly($field);
    }

    /**
     * Check if the data is valid, if not show validation error and toasts
     *
     * @param integer|null $id the id of the model to update or null if creating, passed to the rules method to replace {id} in rules (usualy the unique rule)
     * @return boolean
     */
    public function isValid(int $id = null): bool
    {
        // Replace empty values with placeholders if present in temporary variable
        $data = $this->data;
        foreach ($this->placeholder as $name => $placeholder) {
            if (empty($data[$name])) {
                $data[$name] = $placeholder;
            }
        }

        $validator = Validator::make(['data' => $data], $this->rules($id), [], $this->validationAttributes());
        if ($validator->fails()) {
            // Show validation errors as toasts
            foreach ($validator->messages()->keys() as $fieldKey) {
                $this->dispatch('toast-error', $validator->messages()->first($fieldKey), $fieldKey)->to(Toasts::class);
            }
            // Show validation errors
            $validator->validate();
            return false;
        } else {
            // Validation passed so use data from temporary variable
            $this->data = $data;
            $this->resetValidation();
            return true;
        }
    }

    private function updateAttributes(Model &$model)
    {
        // Update each attribute
        foreach ($this->attributes() as $attribute) {
            if ($attribute->type == 'password' && !$this->data[$attribute->name]) {
                // Ignore empty passwords
            } elseif ($attribute->type == 'media') {
                // Ignore media files
            } elseif ($attribute->type == 'pivot') {
                // Ignore pivot data
            } else {
                $model->{$attribute->name} = $this->data[$attribute->name] ?: null;
            }
        }
    }

    public function syncMedia(Model $model)
    {
        $mediables = $model->morphToMany(Media::class, 'model', config('leap.table_prefix') . 'mediables')->withTimestamps();
        foreach ($this->mediaUpdated as $attribute) {
            Mediable::where('model_id', $model->id)->where('model_attribute', $attribute)->delete();
            foreach ($this->data[$attribute] as $sort => $media_id) {
                $mediables->attach($media_id, [
                    'model_attribute' => $attribute,
                    'sort' => $sort,
                ]);
            }
        }
        $this->mediaUpdated = [];
    }

    public function syncPivot(Model $model)
    {
        foreach ($this->attributes()->where('type', 'pivot') as $attribute) {
            $model->{$attribute->name}()->sync($this->data[$attribute->name]);
        }
    }

    /**
     * Save or create the edited model
     *
     * @return void
     */
    public function save()
    {
        Leap::validatePermission($this->editing == self::CREATE_NEW ? 'create' : 'update');

        if ($this->isValid($this->editing)) {
            // Get current model with data
            $model = $this->getModel($this->editing);

            $this->updateAttributes($model);

            // Check if anything changed
            if ($model->isDirty() || $this->mediaUpdated || $this->pivotIsDirty()) {
                if ($this->editing == self::CREATE_NEW) {
                    $model->save();
                    $this->syncMedia($model);
                    $this->syncPivot($model);

                    $this->log('create', ['id' => $model->id]);
                    $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name] . ' (' . $model->id . ') ' . __('leap::resource.created'))->to(Toasts::class);
                    $this->dispatch('updateIndex', $model->id);
                    $this->editing = $model->id;
                } else {
                    if (count($model->getDirty()) + count($this->mediaUpdated) + count($this->pivotIsDirty()) > 3) {
                        $this->dispatch('toast', count($model->getDirty()) + count($this->mediaUpdated) . ' ' . __('leap::resource.columns') . ' ' . __('leap::resource.updated'))->to(Toasts::class);
                    } else {
                        foreach (array_merge($model->getDirty(), $this->mediaUpdated, $this->pivotIsDirty()) as $attribute => $value) {
                            $this->dispatch('toast', ucfirst($this->validationAttributes()['data.' . explode('.', $attribute)[0]]) . ' ' . __('leap::resource.updated'))->to(Toasts::class);
                        }
                    }
                    $model->save();
                    $this->syncMedia($model);
                    $this->syncPivot($model);

                    $this->log('update', ['id' => $this->editing]);
                    $this->dispatch('updateIndex', $model->id);
                }
                // Force reload of editor data
                $this->openEditor($model->id);
            } else {
                $this->dispatch('toast-alert', __('leap::resource.no_changes'))->to(Toasts::class);
            }
        }
    }

    /**
     * Clone the edited model as a new model
     *
     * @return void
     */
    public function clone()
    {
        Leap::validatePermission('create');

        if ($this->isValid()) {
            // Create new model
            $model = $this->getModel();

            $this->updateAttributes($model);

            $model->save();
            $this->syncMedia($model);
            $this->syncPivot($model);

            $this->log('create', ['clone' => $this->editing . ' -> ' . $model->id]);
            // Force reload of editor data
            $this->openEditor($model->id);
            $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name] . ' (' . $model->id . ') ' . __('leap::resource.created'))->to(Toasts::class);
            $this->dispatch('updateIndex', $model->id);
        }
    }

    /**
     * Delete the model being edited
     *
     * @return void
     */
    public function delete()
    {
        Leap::validatePermission('delete');
        $model = $this->getModel($this->editing);
        $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name] . ' (' . $model->id . ') ' . __('leap::resource.deleted'))->to(Toasts::class);
        $this->log('delete', ['id' => $this->editing]);
        $model->delete();
        $this->editing = null;
        $this->dispatch('updateIndex');
    }

    /**
     * Add the selected files from the file browser to the attribute value
     *
     * @param [type] $files
     * @return void
     */
    #[On('selectBrowsedFiles')]
    public function selectBrowsedFiles(string $attribute, array $files)
    {
        $this->data[$attribute] = trim($this->data[$attribute] . PHP_EOL . implode(PHP_EOL, $files));
    }

    /**
     * Add the selected media files from the file browser to the mediable attribute
     *
     * @param [type] $files
     * @return void
     */
    #[On('selectMediaFiles')]
    public function selectMediaFiles(string $attribute, array $files)
    {
        $this->mediaUpdated[$attribute] = $attribute;
        foreach ($files as $file) {
            $media = Media::forFile($file);
            $this->data[$attribute][] = $media->id;
        }
    }

    public function unselectMedia($attribute, $id)
    {
        $this->mediaUpdated[$attribute] = $attribute;
        unset($this->data[$attribute][$id]);
        $this->data[$attribute] = array_values($this->data[$attribute]);
    }

    public function unselectFile($attribute, $id)
    {
        $data = explode(PHP_EOL, $this->data[$attribute]);
        unset($data[$id]);
        $this->data[$attribute] = implode(PHP_EOL, $data);
    }

    public function sortData($attribute, $old, $new)
    {
        if (is_array($this->data[$attribute])) {
            $out = array_splice($this->data[$attribute], $old, 1);
            array_splice($this->data[$attribute], $new, 0, $out);
            $this->mediaUpdated[$attribute] = $attribute;
        } else {
            $data = explode(PHP_EOL, $this->data[$attribute]);
            $out = array_splice($data, $old, 1);
            array_splice($data, $new, 0, $out);
            $this->data[$attribute] = implode(PHP_EOL, $data);
        }
    }

    public function media(string $attribute)
    {
        $media = Media::find($this->data[$attribute]);

        $return = [];
        foreach ($this->data[$attribute] as $sort => $id) {
            $return[] = $media->where('id', $id)->first();
        }
        return $return;
    }

    public function hydrate()
    {
        // Add the parentModule to the context so we can use it during each request
        Context::add('leap.module', Crypt::decryptString($this->parentModuleEncrypted));
    }

    public function mount()
    {
        // Encrypt the parent module class name
        $this->parentModuleEncrypted = Crypt::encryptString(Context::get('leap.module'));

        $this->setRandomSortSeed();
    }

    public function render()
    {
        return view('leap::livewire.editor');
    }
}
