<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
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
    }

    /**
     * Hide the editor
     *
     * @return void
     */
    public function close()
    {
        $this->editing = null;
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
