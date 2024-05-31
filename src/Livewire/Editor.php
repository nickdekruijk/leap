<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Attributes\On;

class Editor extends Component
{
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
     * @return array
     */
    public function attributes(): array
    {
        // Get the attributes from the parent module
        $parentAttributes = $this->parentModule()->attributes();

        // Filter out the indexOnly attributes
        return collect($parentAttributes)->where('indexOnly', false)->toArray();
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
        return $id ? $model->find($id) : $model;
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

        // Set the editing id and open the editor
        $this->editing = $id;

        // We only want the attributes that are shown in the editor
        $attributes = collect($this->attributes())->pluck('name')->toArray();

        // Get the model data
        $this->data = $this->getModel($id)->only($attributes);

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
     * @return array
     */
    public function rules(): array
    {
        // The Attribute class sets some placeholders in the validation rules that needs to be replaced with actual values, this array defines those replacements
        $replace = [
            '{id}' => $this->editing,
            '{table}' => $this->getModel()->getTable(),
        ];

        $rules = [];

        foreach ($this->attributes() as $attribute) {
            // Walk thru the validation rules of each attribute
            if ($attribute->validate) {
                // Replace placeholders
                foreach ($replace as $old => $new) {
                    $attribute->validate = str_replace($old, $new, $attribute->validate);
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
        $this->validateOnly($field);
    }

    /**
     * Save the edited model
     *
     * @return void
     */
    public function save()
    {
        Gate::authorize('leap::update', $this->editing);

        // First validate the data
        $validator = Validator::make(['data' => $this->data], $this->rules(), [], $this->validationAttributes());
        if ($validator->fails()) {
            // Show validation errors as toasts
            foreach ($validator->messages()->keys() as $fieldKey) {
                $this->dispatch('toast-error', $validator->messages()->first($fieldKey), $fieldKey)->to(Toasts::class);
            }
            // Show validation errors
            $validator->validate();
        } else {
            // Get current model with data
            $model = $this->getModel($this->editing);

            // Update each attribute
            foreach ($this->attributes() as $attribute) {
                $model->{$attribute->name} = $this->data[$attribute->name];
            }

            // Check if anything changed
            if ($model->isDirty()) {
                $model->update();
                $this->dispatch('updateIndex');
                $this->dispatch('toast', (__('saved')))->to(Toasts::class);
            } else {
                $this->dispatch('toast-alert', __('no-changes'))->to(Toasts::class);
            }
        }
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
