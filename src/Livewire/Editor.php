<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Attributes\On;
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
     * Show the editor for the given id
     *
     * @param int $id the id of the Model to update
     * @return void
     */
    #[On('openEditor')]
    public function openEditor(int $id)
    {
        // Check if the user has read permission to this module
        Gate::authorize('leap::read', $id);

        $this->log('read', ['id' => $id]);

        // Set the editing id and open the editor
        $this->editing = $id;

        // We only want the attributes that are shown in the editor
        $attributes = $this->attributes()->pluck('name')->toArray();

        // Get the model data
        $this->data = $this->getModel($id)->only($attributes);

        // Reformat datetime attributes
        foreach ($this->attributes()->where('type', 'datetime-local') as $attribute) {
            $this->data[$attribute->name] = $this->data[$attribute->name]?->isoFormat('YYYY-MM-DD HH:mm:ss');
        }

        // Make checkbox values boolean when model $casts attribute is not set to boolean
        foreach ($this->attributes()->where('type', 'checkbox') as $attribute) {
            if (($this->getModel()->getCasts()[$attribute->name] ?? null) !== 'boolean') {
                $this->data[$attribute->name] = $this->data[$attribute->name] ? true : false;
            }
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
                            $attribute->validate[$key] = str_replace($old, $new, $value);
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

    public function updated($field, $value)
    {
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
            } else {
                $model->{$attribute->name} = $this->data[$attribute->name] ?: null;
            }
        }
    }

    /**
     * Save or create the edited model
     *
     * @return void
     */
    public function save()
    {
        Gate::authorize($this->editing == self::CREATE_NEW ? 'leap::create' : 'leap::update', $this->editing);

        if ($this->isValid($this->editing)) {
            // Get current model with data
            $model = $this->getModel($this->editing);

            $this->updateAttributes($model);

            // Check if anything changed
            if ($model->isDirty()) {
                if ($this->editing == self::CREATE_NEW) {
                    $model->save();
                    $this->log('create', ['id' => $model->id]);
                    $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name] . ' (' . $model->id . ') ' . __('created'))->to(Toasts::class);
                    $this->dispatch('updateIndex', $model->id);
                    $this->editing = $model->id;
                } else {
                    foreach ($model->getDirty() as $attribute => $value) {
                        $this->dispatch('toast', ucfirst($this->validationAttributes()['data.' . $attribute]) . ' ' . __('updated'))->to(Toasts::class);
                    }
                    $this->log('update', ['id' => $this->editing]);
                    $model->save();
                    $this->dispatch('updateIndex', $model->id);
                }
                // Force reload of editor data
                $this->openEditor($model->id);
            } else {
                $this->dispatch('toast-alert', __('no-changes'))->to(Toasts::class);
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
        Gate::authorize('leap::create');

        if ($this->isValid()) {
            // Create new model
            $model = $this->getModel();

            $this->updateAttributes($model);

            $model->save();
            $this->log('create', ['clone' => $this->editing . ' -> ' . $model->id]);
            // Force reload of editor data
            $this->openEditor($model->id);
            $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name] . ' (' . $model->id . ') ' . __('created'))->to(Toasts::class);
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
        Gate::authorize('leap::delete', $this->editing);
        $model = $this->getModel($this->editing);
        $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name] . ' (' . $model->id . ') ' . __('deleted'))->to(Toasts::class);
        $this->log('delete', ['id' => $this->editing]);
        $model->delete();
        $this->editing = null;
        $this->dispatch('updateIndex');
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
    }

    public function render()
    {
        return view('leap::livewire.editor');
    }
}
