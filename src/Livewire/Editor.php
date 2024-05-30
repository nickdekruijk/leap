<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
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
     * The name of the parent Livewire component
     * 
     * The editor uses this to determine the model and attributes
     *
     * @var string
     */
    #[Locked]
    public string $parentModule;

    #[On('openEditor')]
    public function openEditor($id)
    {
        $this->editing = $id;
    }

    public function close()
    {
        $this->editing = null;
    }

    public function booted()
    {
        // Add the parentModule to the context so we can use it during each request
        Context::add('leap.module', $this->parentModule);
    }

    public function mount()
    {
        $this->parentModule = Context::get('leap.module');
    }

    public function render()
    {
        return view('leap::livewire.editor');
    }
}
